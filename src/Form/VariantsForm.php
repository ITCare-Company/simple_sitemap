<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Entity\SimpleSitemapType;

/**
 * Class VariantsForm
 */
class VariantsForm extends SimpleSitemapFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_variants_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['simple_sitemap_variants'] = [
      '#title' => $this->t('Sitemap variants'),
      '#type' => 'fieldset',
      '#markup' => '<div class="description">' . $this->t('Define sitemap variants. A sitemap variant is a sitemap instance of a certain type (specific sitemap generator and URL generators) accessible under a certain URL.<br>Each variant can have its own entity bundle settings (to be defined on bundle edit pages).') . '</div>',
      '#prefix' => FormHelper::getDonationText(),
    ];

    $form['simple_sitemap_variants']['variants'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Variants'),
      '#default_value' => $this->variantsToString(SimpleSitemap::loadMultiple()),
      '#description' => $this->t("Please specify sitemap variants, one per line. <strong>Caution: </strong>Removing variants here will delete their bundle settings, custom links and corresponding sitemap instances.<br><br>A variant definition consists of the variant name (used as the variant's path), the sitemap type it belongs to (optional) and the variant label (optional). These three values have to be separated by the | pipe | symbol.<br><br><strong>Examples:</strong><br><em>default | default_hreflang | Default</em> -> variant of the <em>default_hreflang</em> sitemap type and <em>Default</em> as label; accessible under <em>/default/sitemap.xml</em><br><em>test</em> -> variant of the <em>default_hreflang</em> sitemap type and <em>test</em> as label; accessible under <em>/test/sitemap.xml</em><br><br><strong>Available sitemap types:</strong>"),
    ];

    if ($types = SimpleSitemapType::loadMultiple()) {
      foreach ($types as $sitemap_type) {
        $form['simple_sitemap_variants']['variants']['#description'] .= '<br>' . '<em>' . $sitemap_type->id() . '</em>' . (!empty($sitemap_type->getDescription()) ? (': ' . $sitemap_type->getDescription()) : '');
      }
    }
    else {
      $form['simple_sitemap_variants']['variants']['#description'] .= " ({$this->t('none')})";
    }


    $this->formHelper->displayRegenerateNow($form['simple_sitemap_variants']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $line = 0;
    $sitemap_types = SimpleSitemapType::loadMultiple();
    foreach ($this->stringToVariants($form_state->getValue('variants')) as $id => $variant_definition) {
      $placeholders = [
        '@line' => ++$line,
        '@id' => $id,
        '@type' => $variant_definition['type'],
      ];

      if (trim($id) === '') {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant ID cannot be empty.", $placeholders));
      }

      if (!preg_match('/^[\w\-_]+$/', $id)) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant ID <em>@id</em> can only include alphanumeric characters, dashes and underscores.", $placeholders));
      }

      if (is_numeric($id)) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant ID cannot be numeric.", $placeholders));
      }

      if ($variant_definition['type'] === NULL) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant type cannot be empty.", $placeholders));
      }

      if (!isset($sitemap_types[$variant_definition['type']])) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant <em>@id</em> is of a sitemap type <em>@type</em> that does not exist.", $placeholders));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $new_variants = $this->stringToVariants($form_state->getValue('variants'));
    $remove_variants = array_values(array_diff(
      array_keys(SimpleSitemap::loadMultiple()),
      array_keys($new_variants)
    ));
    $storage = \Drupal::entityTypeManager()->getStorage('simple_sitemap');
    $storage->delete($storage->loadMultiple($remove_variants));

    $weight = 0;
    foreach ($new_variants as $variant_definition) {
      $variant_definition['weight'] = $weight;
      SimpleSitemap::createOrUpdate(...array_values($variant_definition));
      $weight++;
    }

    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $this->generator->setVariants(TRUE)
        ->rebuildQueue()
        ->generateSitemap();
    }
  }

  /**
   * @param string $variant_string
   *
   * @return array
   */
  protected function stringToVariants(string $variant_string): array {

    // Unify newline characters and explode into array.
    $variants_string_lines = explode("\n", str_replace("\r\n", "\n", $variant_string));

    // Remove empty values and whitespaces from array.
    $variants_string_lines = array_filter(array_map('trim', $variants_string_lines));

    $variants = [];
    foreach ($variants_string_lines as &$line) {
      $variant_settings = explode('|', $line);
      $id = strtolower(trim($variant_settings[0]));
      $variants[$id]['id'] = $id;
      $variants[$id]['type'] = !empty($variant_settings[1]) ? trim($variant_settings[1]) : NULL;
      $variants[$id]['label'] = !empty($variant_settings[2]) ? trim($variant_settings[2]) : NULL;
    }

    return $variants;
  }

  /**
   * @param array $variants
   * @return string
   */
  protected function variantsToString(array $variants): string {
    $variants_string = '';
    foreach ($variants as $variant) {
      $variants_string .= $variant->id()
        . ' | ' . $variant->getType()->id()
        . ' | ' . $variant->label()
        . "\r\n";
    }

    return $variants_string;
  }
}
