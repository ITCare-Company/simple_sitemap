<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\Core\Url;

/**
 * Class SitemapWriter
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator
 */
class SitemapWriter extends \XMLWriter {

  /**
   * Adds the XML stylesheet to the XML page.
   */
  public function writeXsl() {
    $xsl_url = Url::fromRoute('simple_sitemap.sitemap_xsl')->toString();
    $this->writePI('xml-stylesheet', 'type="text/xsl" href="' . $xsl_url . '"');
  }

}
