<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;

/**
 * Interface UrlGeneratorInterface
 */
interface UrlGeneratorInterface {

  public function setSitemapVariant(SimpleSitemapInterface $sitemap_variant): UrlGeneratorInterface;

  public function getDataSets(): array;

  public function generate($data_set): array;
}
