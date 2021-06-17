<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Simplesitemap as SimplesitemapOld;
use Drupal\Core\Database\Connection;

/**
 * Class SimpleSitemapSitemapsForm
 */
class SimpleSitemapSitemapsForm extends SimpleSitemapFormBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * SimpleSitemapSitemapsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param SimplesitemapOld $generator
   * @param \Drupal\simple_sitemap\Form\FormHelper $form_helper
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    SimplesitemapOld $generator,
    FormHelper $form_helper,
    Connection $database,
    DateFormatter $date_formatter
  ) {
    parent::__construct(
      $config_factory,
      $generator,
      $form_helper
    );
    $this->db = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.form_helper'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_sitemaps_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['simple_sitemap_settings']['#prefix'] = FormHelper::getDonationText();
    $form['simple_sitemap_settings']['#attached']['library'][] = 'simple_sitemap/sitemaps';
    $queue_worker = $this->generator->getQueueWorker();

    $form['simple_sitemap_settings']['status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sitemap status'),
      '#markup' => '<div class="description">' . $this->t('Sitemaps can be regenerated on demand here.') . '</div>',
      '#description' => $this->t('Variants can be configured <a href="@url">here</a>.', ['@url' => $GLOBALS['base_url'] . '/admin/config/search/simplesitemap/variants']),
    ];

    $form['simple_sitemap_settings']['status']['actions'] = [
      '#prefix' => '<div class="clearfix"><div class="form-item">',
      '#suffix' => '</div></div>',
    ];

    $form['simple_sitemap_settings']['status']['actions']['rebuild_queue_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild queue'),
      '#submit' => ['::rebuildQueue'],
      '#validate' => [],
    ];

    $form['simple_sitemap_settings']['status']['actions']['regenerate_submit'] = [
      '#type' => 'submit',
      '#value' => $queue_worker->generationInProgress()
        ? $this->t('Resume generation')
        : $this->t('Rebuild queue & generate'),
      '#submit' => ['::generateSitemap'],
      '#validate' => [],
    ];

    $form['simple_sitemap_settings']['status']['progress'] = [
      '#prefix' => '<div class="clearfix">',
      '#suffix' => '</div>',
    ];

    $form['simple_sitemap_settings']['status']['progress']['title']['#markup'] = $this->t('Progress of sitemap regeneration');

    $total_count = $queue_worker->getInitialElementCount();
    if (!empty($total_count)) {
      $indexed_count = $queue_worker->getProcessedElementCount();
      $percent = round(100 * $indexed_count / $total_count);

      // With all results processed, there still may be some stashed results to be indexed.
      $percent = $percent === 100 && $queue_worker->generationInProgress() ? 99 : $percent;

      $index_progress = [
        '#theme' => 'progress_bar',
        '#percent' => $percent,
        '#message' => $this->t('@indexed out of @total queue items have been processed.<br>Each sitemap variant is published after all of its items have been processed.', ['@indexed' => $indexed_count, '@total' => $total_count]),
      ];
      $form['simple_sitemap_settings']['status']['progress']['bar']['#markup'] = render($index_progress);
    }
    else {
      $form['simple_sitemap_settings']['status']['progress']['bar']['#markup'] = '<div class="description">' . $this->t('There are no items to be indexed.') . '</div>';
    }

    $sitemap_manager = $this->generator->getSitemapManager();
    foreach ($sitemap_manager->getSitemapTypes() as $type_id => $sitemap_type) {
      $variants = \Drupal::entityTypeManager()->getStorage('simple_sitemap')->loadByProperties(['type' => $type_id]);
      if (!empty($variants)) {

        $form['simple_sitemap_settings']['status']['types'][$type_id] = [
          '#type' => 'details',
          '#title' => '<em>' . $sitemap_type->label() . '</em> ' . $this->t('sitemaps'),
          '#open' => !empty($variants) && count($variants) <= 5,
          '#description' => !empty($sitemap_type->getDescription()) ? '<div class="description">' . $sitemap_type->getDescription() . '</div>' : '',
        ];
        $form['simple_sitemap_settings']['status']['types'][$type_id]['table'] = [
          '#type' => 'table',
          '#header' => [$this->t('Variant'), $this->t('Status'), $this->t('Link count')],
          '#attributes' => ['class' => ['form-item', 'clearfix']],
        ];
        foreach ($variants as $variant) {
          /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $variant */
          if (empty($variant->publishedAndUnpublished()->getChunkCount())) {
            $row['name']['data']['#markup'] = '<span title="' . $variant->id() . '">' . $this->t($variant->label()) . '</span>';
            $row['status'] = $this->t('pending');
            $row['count'] = '';
          }
          else {
            switch ($variant->status()) {

              case SimpleSitemap::SITEMAP_UNPUBLISHED:
                $row['name']['data']['#markup'] = '<span title="' . $variant->id() . '">' . $this->t($variant->label()) . '</span>';
                $row['status'] = $this->t('generating');
                $row['count'] = '';
                break;

              case SimpleSitemap::SITEMAP_PUBLISHED:
              case SimpleSitemap::SITEMAP_PUBLISHED_GENERATING:
                $row['name']['data']['#markup'] = $this->t('<a href="@url" target="_blank">@variant</a>',
                  ['@url' => $variant->getUrl(), '@variant' => $this->t($variant->label())]
                );
                $row['status'] = $this->t(($variant->status() === SimpleSitemap::SITEMAP_PUBLISHED
                  ? 'published on @time'
                  : 'published on @time, regenerating'
                ), ['@time' => $this->dateFormatter->format($variant->published()->getCreated())]);
                // Once the sitemap has been regenerated after
                // simple_sitemap_update_8305() there will always be a link
                // count.
                $row['count'] = $variant->published()->getLinkCount() > 0
                  ? $variant->published()->getLinkCount()
                  : $this->t('unavailable');
                break;
            }
          }
          $form['simple_sitemap_settings']['status']['types'][$type_id]['table']['#rows'][$variant->id()] = isset($row) ? $row : [];
        }
      }
    }
    if (empty($form['simple_sitemap_settings']['status']['types'])) {
      $form['simple_sitemap_settings']['status']['types']['#markup'] = $this->t('No variants have been defined');
    }

    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function generateSitemap(array &$form, FormStateInterface $form_state): void {
    $this->generator->generateSitemap();
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function rebuildQueue(array &$form, FormStateInterface $form_state): void {
    $this->generator->rebuildQueue();
  }

}
