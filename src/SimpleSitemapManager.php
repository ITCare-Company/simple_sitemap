<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;

/**
 * Class SimpleSitemapManager
 *
 * @todo Merge into Simplesitemap and rename Simplesitemap to SimpleSitemapManager
 */
class SimpleSitemapManager {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SimpleSitemapManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @todo Change for 4.x.
   */
  public function getSitemapTypes(): array {
    return $this->entityTypeManager->getStorage('simple_sitemap_type')
      ->loadMultiple();
  }

  /**
   * @todo Change for 4.x.
   */
  public function getSitemapVariant($id): ?SimpleSitemapInterface {
    return $this->entityTypeManager->getStorage('simple_sitemap')->load($id);
  }

  /**
   * @todo Change for 4.x.
   */
  public function getSitemapVariants(): array {
    return $this->entityTypeManager->getStorage('simple_sitemap')
      ->loadMultiple();
  }

  public function addSitemapVariant(string $id, array $definition = []): SimpleSitemapManager {
    $storage = $this->entityTypeManager->getStorage('simple_sitemap');

    $definition['label'] = !empty($definition['label']) ? $definition['label'] : $id;
    $definition['weight'] = isset($definition['weight']) ? (int) $definition['weight'] : 0;

    if ($old_variant = $storage->load($id)) {
      foreach ($definition as $field => $value) {
        $old_variant->set($field, $value);
      }
      $old_variant->save();
    }
    else {
      $storage->create(['id' => $id] + $definition)->save();
    }

    return $this;
  }

}
