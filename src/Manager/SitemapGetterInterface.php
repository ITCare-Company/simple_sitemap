<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\simple_sitemap\Entity\SimpleSitemap;

interface SitemapGetterInterface {

  /**
   * Gets the currently set sitemaps, or all compatible sitemaps if none are
   * set.
   *
   * @return SimpleSitemap[]
   *   The currently set sitemaps, or all compatible sitemaps if none are set.
   */
  public function getSitemaps(): array;

  /**
   * Sets the sitemaps.
   *
   * @param string[]|SimpleSitemap[]|string|SimpleSitemap|null $sitemaps
   *   SimpleSitemap[]: Array of sitemap objects to be set.
   *   string[]: Array of sitemap IDs to be set.
   *   SimpleSitemap: A particular sitemap object to be set.
   *   string: A particular sitemap ID to be set.
   *   null: All compatible sitemaps will be set.
   *
   * @return $this
   */
  public function setSitemaps($sitemaps = NULL): self;

}
