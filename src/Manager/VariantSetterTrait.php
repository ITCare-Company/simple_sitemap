<?php

namespace Drupal\simple_sitemap\Manager;

use Drupal\simple_sitemap\Entity\SimpleSitemap;

/**
 * Provides a helper to setting/getting variants.
 */
trait VariantSetterTrait {

  /**
   * The currently set variants.
   *
   * @var array
   */
  protected $variants;

  /**
   * Sets the variants.
   *
   * @param array|string|true|null $variants
   *   array: Array of variants to be set.
   *   string: A particular variant to be set.
   *   null: Default variant will be set.
   *   true: All existing variants will be set.
   *
   * @return $this
   *
   * @todo Check if variants exist and throw exception.
   */
  public function setVariants($variants = NULL) {
    if (NULL === $variants) {
      $this->variants = !empty($default_variant = \Drupal::service('simple_sitemap.settings')
        ->get('default_variant', '')) ? [$default_variant] : [];
    }
    elseif ($variants === TRUE) {
      $this->variants = array_keys(SimpleSitemap::loadMultiple());
    }
    else {
      $this->variants = (array) $variants;
    }

    return $this;
  }

  /**
   * Gets the currently set variants, or all variants if none are set.
   *
   * @return array
   *   The currently set variants, or all variants if none are set.
   */
  protected function getVariants(): array {
    if (NULL === $this->variants) {
      $this->setVariants(TRUE);
    }

    return $this->variants;
  }

}
