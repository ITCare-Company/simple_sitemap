<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;

/**
 * Interface SitemapGeneratorInterface
 */
interface SitemapGeneratorInterface {

  public function setSitemapVariant(SimpleSitemapInterface $sitemap): SitemapGeneratorInterface;

  public function getChunkXml(array $links): string;

  public function getIndexXml(): string;
}
