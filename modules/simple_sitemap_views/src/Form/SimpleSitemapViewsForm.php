<?php

/**
 * @file
 * Contains \Drupal\simple_sitemap_views\Form\SimpleSitemapViewsForm.
 */

namespace Drupal\simple_sitemap_views\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simple_sitemap\Form\SimplesitemapFormBase;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Drupal\simple_sitemap\Form\FormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * Simple XML Sitemap Views settings form.
 */
class SimpleSitemapViewsForm extends SimplesitemapFormBase {

  /**
   * Views sitemap data.
   *
   * @var \Drupal\simple_sitemap_views\SimpleSitemapViews
   */
  protected $sitemapViews;

  /**
   * SimpleSitemapViewsForm constructor.
   *
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   *   The simple_sitemap.generator service.
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   *   Simple XML Sitemap form helper.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   */
  public function __construct(Simplesitemap $generator, FormHelper $form_helper, SimpleSitemapViews $sitemap_views) {
    parent::__construct($generator, $form_helper);
    $this->sitemapViews = $sitemap_views;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.form_helper'),
      $container->get('simple_sitemap.views')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_sitemap_views_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['simple_sitemap_views']['#prefix'] = $this->getDonationText();

    $form['simple_sitemap_views']['views'] = [
      '#title' => $this->t('Sitemap views'),
      '#type' => 'fieldset',
      '#markup' => '<div class="description">' . $this->t('Manage views support.') . '</div>',
    ];
    $form['simple_sitemap_views']['views']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable views support'),
      '#description' => $this->t('Sitemap settings for views can be set on the display editing pages.'),
      '#default_value' => $this->sitemapViews->isEnabled(),
      '#suffix' => $this->getViewsInfo(),
    ];
    $this->formHelper->displayRegenerateNow($form['simple_sitemap_views']['views']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->getValue('enabled') ? $this->sitemapViews->enable() : $this->sitemapViews->disable();
    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $this->generator->generateSitemap();
    }
  }

  /**
   * Returns information about indexed views.
   *
   * @return string
   *   An HTML string representing the output.
   */
  protected function getViewsInfo() {
    $indexed_views = [];
    // Collect information about indexed views.
    foreach ($this->sitemapViews->getIndexableViews() as $view) {
      if (!isset($indexed_views[$view->id()]['label'])) {
        $indexed_views[$view->id()]['label'] = $view->storage->label();
      }
      $indexed_views[$view->id()]['displays'][] = $view->current_display;
      // Destroy a view instance.
      $view->destroy();
    }

    // Form the output.
    if (empty($indexed_views)) {
      $views_info = $this->t('No displays are set to be indexed yet.');
    }
    else {
      $views_info = '';
      foreach ($indexed_views as $view_id => $view_info) {
        $views_info .= '<div id="indexed-view-displays-' . $view_id . '">';
        $views_info .= $this->t("<em>@view_label</em> displays set to be indexed: <em>@display_titles</em>", [
          '@view_label' => ucfirst(strtolower($view_info['label'])),
          '@display_titles' => implode(', ', $view_info['displays']),
        ]);
        $views_info .= '</div>';
      }
    }
    return $views_info;
  }

}
