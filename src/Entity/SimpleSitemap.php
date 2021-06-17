<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\SimpleSitemapStorage;
use Drupal\simple_sitemap\Exception\SitemapNotExistsException;

/**
 * Defines the simple_sitemap entity.
 *
 * @ConfigEntityType(
 *   id = "simple_sitemap",
 *   label = @Translation("Simple XML sitemap"),
 *   handlers = {
 *     "storage" = "Drupal\simple_sitemap\SimpleSitemapStorage",
 *   },
 *   config_prefix = "sitemap",
 *   admin_permission = "administer sitemap settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "weight",
 *   },
 * )
 *
 * @todo Implement dependency injection after https://www.drupal.org/project/drupal/issues/2142515 is fixed.
 */
class SimpleSitemap extends ConfigEntityBase implements SimpleSitemapInterface {

  public const SITEMAP_UNPUBLISHED = 0;
  public const SITEMAP_PUBLISHED = 1;
  public const SITEMAP_PUBLISHED_GENERATING = 2;

  public const FETCH_BY_STATUS_PUBLISHED = 1;
  public const FETCH_BY_STATUS_UNPUBLISHED = 0;
  public const FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED = NULL;

  /**
   * @var int
   */
  protected $fetchByStatus;

  /**
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface
   */
  protected $sitemapType;

  public function __toString(): string {
    try {
      return $this->toString();
    }
    catch (SitemapNotExistsException $e) {
      return '';
    }
  }

  public function published(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_PUBLISHED;
    return $this;
  }

  public function unpublished(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_UNPUBLISHED;
    return $this;
  }

  public function publishedAndUnpublished(): SimpleSitemapInterface {
    $this->fetchByStatus = self::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED;
    return $this;
  }

  public function getType(): SimpleSitemapTypeInterface {
    if ($this->sitemapType === NULL) {
      $this->sitemapType = \Drupal::entityTypeManager()->getStorage('simple_sitemap_type')->load($this->get('type'));
    }

    return $this->sitemapType;
  }

  public function getWeight(): int {
    return (int) $this->get('weight');
  }

  public function toString(int $delta = NULL): string {
    $status = $this->fetchByStatus ?? self::FETCH_BY_STATUS_PUBLISHED;
    $storage = \Drupal::entityTypeManager()->getStorage('simple_sitemap');

    if ($delta) {
      try {
        return $storage->getChunk($this, $status, $delta);
      }
      catch (SitemapNotExistsException $e) {
        return $storage->getChunk($this, $status);
      }
    }

    if ($storage->hasIndex($this, $status)) {
      return $storage->getIndex($this, $status);
    }

    return $storage->getChunk($this, $status);
  }

  public function publish(): SimpleSitemapInterface {
    \Drupal::entityTypeManager()->getStorage('simple_sitemap')->publish($this);
    return $this;
  }

  public function deleteContent(): SimpleSitemapInterface {
    \Drupal::entityTypeManager()->getStorage('simple_sitemap')->deleteContent($this);
    return $this;
  }

  public function addChunk(array $links): SimpleSitemapInterface {
    $xml = $this->getType()->getSitemapGenerator()->setSitemapVariant($this)->getChunkXml($links); //todo automatically set variant
    \Drupal::entityTypeManager()->getStorage('simple_sitemap')->addChunk($this, $xml, count($links));

    return $this;
  }

  public function generateIndex(): SimpleSitemapInterface {
    if ($this->isIndexable()) {
      $xml = $this->getType()->getSitemapGenerator()->setSitemapVariant($this)->getIndexXml(); //todo automatically set variant
      \Drupal::entityTypeManager()->getStorage('simple_sitemap')->generateIndex($this, $xml);
    }

    return $this;
  }

  public function getChunk(int $delta = SimpleSitemapStorage::SITEMAP_CHUNK_FIRST_DELTA): string {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getChunk($this, $this->fetchByStatus, $delta);
  }

  public function getChunkCount(): int {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getChunkCount($this, $this->fetchByStatus);
  }

  public function hasIndex(): bool {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->hasIndex($this, $this->fetchByStatus);
  }

  protected function isIndexable(): bool {
    try {
      \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getChunk($this, self::FETCH_BY_STATUS_UNPUBLISHED, 2);
      return TRUE;
    }
    catch (SitemapNotExistsException $e) {
      return FALSE;
    }
  }

  public function getIndex(): string {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getIndex($this, $this->fetchByStatus);
  }

  public function status(): int {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->status($this);
  }

  public function getCreated(): ?string {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getCreated($this, $this->fetchByStatus);
  }

  public function getLinkCount(): int {
    return \Drupal::entityTypeManager()->getStorage('simple_sitemap')->getLinkCount($this, $this->fetchByStatus);
  }

  /**
   * @todo: Should this be parents ::url instead?
   */
  public function getUrl(int $delta = NULL): string {
    $parameters = NULL !== $delta ? ['page' => $delta] : [];
    $settings = [
      'absolute' => TRUE,
      'base_url' => \Drupal::service('simple_sitemap.settings')->getSetting('base_url') ?: $GLOBALS['base_url'],
      'language' => \Drupal::languageManager()->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE),
    ];

    $url = $this->isDefault()
      ? Url::fromRoute(
        'simple_sitemap.sitemap_default',
        $parameters,
        $settings)
      : Url::fromRoute(
        'simple_sitemap.sitemap_variant',
        $parameters + ['variant' => $this->id()],
        $settings);

    return $url->toString();
  }

  public function isDefault(): bool {
    return $this->id() === \Drupal::service('simple_sitemap.settings')->getSetting('default_variant');
  }

  /**
   * Determines if the sitemap is to be a multilingual sitemap based on several
   * factors.
   *
   * A hreflang/multilingual sitemap is only wanted if there are indexable
   * languages available and if there is a language negotiation method enabled
   * that is based on URL discovery. Any other language negotiation methods
   * should be irrelevant, as a sitemap can only use URLs to guide to the
   * correct language.
   *
   * @see https://www.drupal.org/project/simple_sitemap/issues/3154570#comment-13730522
   *
   * @return bool
   */
  public function isMultilingual(): bool {
    if (!\Drupal::service('module_handler')->moduleExists('language')) {
      return FALSE;
    }

    $url_negotiation_method_enabled = FALSE;
    $language_negotiator = \Drupal::service('language_negotiator');
    foreach ($language_negotiator->getNegotiationMethods(LanguageInterface::TYPE_URL) as $method) {
      if ($language_negotiator->isNegotiationMethodEnabled($method['id'])) {
        $url_negotiation_method_enabled = TRUE;
        break;
      }
    }

    $has_multiple_indexable_languages = count(
        array_diff_key(\Drupal::languageManager()->getLanguages(),
          \Drupal::service('simple_sitemap.settings')->getSetting('excluded_languages', []))
      ) > 1;

    return $url_negotiation_method_enabled && $has_multiple_indexable_languages;
  }

}
