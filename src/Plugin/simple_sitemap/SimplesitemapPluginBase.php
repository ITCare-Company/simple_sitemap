<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UrlGeneratorBase
 */
abstract class SimplesitemapPluginBase extends PluginBase implements ContainerFactoryPluginInterface {

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimplesitemapPluginBase {
    return new static($configuration, $plugin_id, $plugin_definition);
  }
}
