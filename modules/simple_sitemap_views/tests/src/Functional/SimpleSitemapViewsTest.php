<?php

/**
 * @file
 * Contains functional tests for Simple XML Sitemap (Views).
 */

namespace Drupal\Tests\simple_sitemap_views\Functional;

/**
 * Tests Simple XML Sitemap (Views) functional integration.
 *
 * @group simple_sitemap_views
 */
class SimpleSitemapViewsTest extends SimpleSitemapViewsTestBase {

  /**
   * Tests Views URL generator availability.
   */
  public function testViewsUrlGeneratorAvailability() {
    $sitemap_types = $this->generator->getSitemapManager()->getSitemapTypes();
    $this->assertContains('views', $sitemap_types['default_hreflang']['urlGenerators']);
  }

  /**
   * Tests status of sitemap support for views.
   */
  public function testSitemapSupportForViews() {
    // Views support must be enabled after module installation.
    $this->assertTrue($this->sitemapViews->isEnabled());

    $this->sitemapViews->disable();
    $this->assertFalse($this->sitemapViews->isEnabled());

    $this->sitemapViews->enable();
    $this->assertTrue($this->sitemapViews->isEnabled());
  }

}
