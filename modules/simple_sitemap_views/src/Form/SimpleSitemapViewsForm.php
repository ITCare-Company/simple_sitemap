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
use Drupal\Core\Url;

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
    ];

    $indexable_views = $this->sitemapViews->getIndexableViews();
    if (empty($indexable_views)) {
      $form['simple_sitemap_views']['views']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No displays are set to be indexed yet.'),
      ];
    }
    else {
      $table = [
        '#type' => 'table',
        '#header' => [
          $this->t('View'),
          $this->t('Display'),
          $this->t('Arguments'),
          $this->t('Operations'),
        ],
      ];
      foreach ($indexable_views as $index => $view) {
        $table[$index]['view'] = ['#markup' => $view->storage->label()];
        $table[$index]['display'] = ['#markup' => $view->display_handler->display['display_title']];
        // Determine whether view display arguments are indexed.
        $arguments_status = $this->sitemapViews->getIndexableArguments($view) ? $this->t('Yes') : $this->t('No');
        $table[$index]['arguments'] = ['#markup' => $arguments_status];

        // Link to view display edit form.
        $display_edit_url = Url::fromRoute('entity.view.edit_display_form', [
          'view' => $view->id(),
          'display_id' => $view->current_display,
        ]);
        $table[$index]['operations'] = [
          '#type' => 'operations',
          '#links' => [
            'display_edit' => [
              'title' => $this->t('Edit'),
              'url' => $display_edit_url,
            ],
          ],
        ];
      }

      // Show information about indexed displays.
      $form['simple_sitemap_views']['views']['info'] = [
        '#type' => 'details',
        '#title' => $this->t('Displays set to be indexed'),
        'table' => $table,
      ];
    }

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

}
