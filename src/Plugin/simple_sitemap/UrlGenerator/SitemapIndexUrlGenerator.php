<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Exception\SkipElementException;

/**
 * Class VariantIndexUrlGenerator.
 *
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "index",
 *   label = @Translation("Sitemap URL generator"),
 *   description = @Translation("Generates sitemap URLs for a sitemap index."),
 * )
 */
class SitemapIndexUrlGenerator extends UrlGeneratorBase {

  /**
   * {@inheritdoc}
   */
  public function getDataSets(): array {
    return \Drupal::entityTypeManager()
      ->getStorage('simple_sitemap')
      ->getQuery()
      ->sort('weight')
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set): array {
    if (($sitemap = SimpleSitemap::load($data_set))
      && $sitemap->status()
      && $sitemap->getType()->getSitemapGenerator()->getPluginId() !== 'index') {
      return [
        'loc' => $sitemap->toUrl()->setAbsolute()->toString(),
        'lastmod' => date('c', $sitemap->fromPublished()->getCreated()),
      ];
    }

    throw new SkipElementException();
  }

}
