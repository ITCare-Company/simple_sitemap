<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SimplesitemapPluginBase;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\SimpleSitemapSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * Class UrlGeneratorBase
 */
abstract class UrlGeneratorBase extends SimplesitemapPluginBase implements UrlGeneratorInterface {

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * @var \Drupal\simple_sitemap\SimpleSitemapSettings
   */
  protected $settings;

  /**
   * @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface
   */
  protected $sitemapVariant;

  /**
   * UrlGeneratorBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\SimpleSitemapSettings $settings
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    Logger $logger,
    SimpleSitemapSettings $settings
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->generator = $generator;
    $this->logger = $logger;
    $this->settings = $settings;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimplesitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings')
    );
  }

  /**
   * @param \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $sitemap_variant
   *
   * @return $this
   */
  public function setSitemapVariant(SimpleSitemapInterface $sitemap_variant): UrlGeneratorInterface {
    $this->sitemapVariant = $sitemap_variant;

    return $this;
  }

  /**
   * @param string $url
   * @return string
   */
  protected function replaceBaseUrlWithCustom($url): string {
    return !empty($base_url = $this->settings->getSetting('base_url'))
      ? str_replace($GLOBALS['base_url'], $base_url, $url)
      : $url;
  }

  /**
   * @return mixed
   */
  abstract public function getDataSets(): array;

  /**
   * @param $data_set
   * @return mixed
   */
  abstract protected function processDataSet($data_set): array;

  /**
   * @param $data_set
   * @return array
   */
  public function generate($data_set): array {
    $path_data = $this->processDataSet($data_set);

    return FALSE !== $path_data ? [$path_data] : [];
  }
}
