<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface;

/**
 * Defines the simple_sitemap entity.
 *
 * @ConfigEntityType(
 *   id = "simple_sitemap_type",
 *   label = @Translation("Simple XML sitemap type"),
 *   handlers = {
 *     "storage" = "Drupal\simple_sitemap\Entity\SimpleSitemapTypeStorage",
 *   },
 *   config_prefix = "type",
 *   admin_permission = "administer sitemap settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "sitemap_generator",
 *     "url_generators",
 *   },
 * )
 *
 * @todo Implement dependency injection after https://www.drupal.org/project/drupal/issues/2142515 is fixed.
 * @todo Now all sitemap types can be deleted. Deal with it.
 */
class SimpleSitemapType extends ConfigEntityBase implements SimpleSitemapTypeInterface {

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorInterface[]
   */
  protected $urlGenerators;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorInterface
   */
  protected $sitemapGenerator;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->get('description');
  }

  /**
   * {@inheritdoc}
   */
  public function getSitemapGenerator(): SitemapGeneratorInterface {
    if ($this->sitemapGenerator === NULL) {
      $this->sitemapGenerator = \Drupal::service('plugin.manager.simple_sitemap.sitemap_generator')
        ->createInstance($this->get('sitemap_generator'));
    }

    return $this->sitemapGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlGenerators(): array {
    if ($this->urlGenerators === NULL) {
      $url_generator_manager = \Drupal::service('plugin.manager.simple_sitemap.url_generator');
      foreach ($url_generator_manager->getDefinitions() as $id => $definition) {
        $instances[$id] = $url_generator_manager->createInstance($id);
      }
      $this->urlGenerators = $instances ?? [];
    }

    return $this->urlGenerators;
  }

}
