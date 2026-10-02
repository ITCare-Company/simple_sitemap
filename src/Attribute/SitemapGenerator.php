<?php

declare(strict_types=1);

namespace Drupal\simple_sitemap\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a SitemapGenerator attribute object.
 *
 * @see \Drupal\simple_sitemap\Annotation\SitemapGenerator
 * @see \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class SitemapGenerator extends Plugin {

  /**
   * Constructs a SitemapGenerator attribute.
   *
   * @param string $id
   *   The generator ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the generator.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A short description of the generator.
   * @param array $settings
   *   Default generator settings.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly array $settings = [],
  ) {}

}
