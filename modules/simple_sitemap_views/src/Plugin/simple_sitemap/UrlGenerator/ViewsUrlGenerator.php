<?php

/**
 * @file
 * Contains Views URL generator.
 */

namespace Drupal\simple_sitemap_views\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simple_sitemap_views\SimpleSitemapViews;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Database\Query\Condition;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\Logger;
use Drupal\views\Views;
use Drupal\Core\Url;

/**
 * Views URL generator plugin.
 *
 * @UrlGenerator(
 *   id = "views",
 *   label = @Translation("Views URL generator"),
 *   description = @Translation("Generates URLs for views."),
 * )
 */
class ViewsUrlGenerator extends UrlGeneratorBase {

  /**
   * An associative array of languages, keyed by the language code.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * An account implementation representing an anonymous user.
   *
   * @var \Drupal\Core\Session\AnonymousUserSession
   */
  protected $anonUser;

  /**
   * Views sitemap data.
   *
   * @var \Drupal\simple_sitemap_views\SimpleSitemapViews
   */
  protected $sitemapViews;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * ViewsUrlGenerator constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   *   The simple_sitemap.generator service.
   * @param \Drupal\simple_sitemap\Logger $logger
   *   The simple_sitemap.logger service.
   * @param \Drupal\simple_sitemap_views\SimpleSitemapViews $sitemap_views
   *   Views sitemap data.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    Logger $logger,
    SimpleSitemapViews $sitemap_views,
    RouteProviderInterface $route_provider,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $generator, $logger);
    $this->languages = $language_manager->getLanguages();
    $this->anonUser = new AnonymousUserSession();
    $this->sitemapViews = $sitemap_views;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.views'),
      $container->get('router.route_provider'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets() {
    $data_sets = [];

    // Get data sets.
    foreach ($this->sitemapViews->getIndexableViews() as $view) {
      $settings = $this->sitemapViews->getSitemapSettings($view);
      if ($settings['variant'] != $this->sitemapVariant) {
        // Destroy a view instance.
        $view->destroy();
        continue;
      }

      $base_data_set = [
        'view_id' => $view->id(),
        'display_id' => $view->current_display,
      ];
      // View path without arguments.
      $data_sets[] = $base_data_set + ['arguments' => NULL];

      // Process indexed arguments.
      if ($args_ids = $this->sitemapViews->getIndexableArguments($view)) {
        // Form the condition according to the variants of the
        // indexable arguments.
        $args_ids = $this->sitemapViews->getArgumentsStringVariations($args_ids);
        $condition = new Condition('AND');
        $condition->condition('view_id', $view->id());
        $condition->condition('display_id', $view->current_display);
        $condition->condition('arguments_ids', $args_ids, 'IN');
        // Get the arguments values from the index.
        $max_links = is_numeric($settings['max_links']) ? $settings['max_links'] : NULL;
        $indexed_arguments = $this->sitemapViews->getArgumentsFromIndex($condition, $max_links, TRUE);
        // Add the arguments values for processing.
        foreach ($indexed_arguments as $index_id => $arguments_info) {
          $data_sets[] = $base_data_set + [
            'index_id' => $index_id,
            'arguments' => $arguments_info['arguments'],
          ];
        }
      }
      // Destroy a view instance.
      $view->destroy();
    }
    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set) {
    // Get information from data set.
    $view_id = $data_set['view_id'];
    $display_id = $data_set['display_id'];
    $args = $data_set['arguments'];

    try {
      // Trying to get an instance of the view.
      $view = Views::getView($view_id);
      if (empty($view) || !$view->setDisplay($display_id)) {
        throw new \UnexpectedValueException('Failed to get an instance of the view.');
      }
      // Trying to get the sitemap settings.
      $settings = $this->sitemapViews->getSitemapSettings($view);
      if (empty($settings)) {
        throw new \UnexpectedValueException('Failed to get the sitemap settings.');
      }

      // Trying to get the view URL.
      $url = $view->getUrl($args);
      $url->setAbsolute();

      if (is_array($args)) {
        $params = array_merge([$view_id, $display_id], $args);
        $view_result = call_user_func_array('views_get_view_result', $params);
        // Do not include paths on which the view returns an empty result.
        if (empty($view_result)) {
          throw new \UnexpectedValueException('The view returned an empty result.');
        }
        // Remove empty arguments from URL.
        $this->cleanRouteParameters($url, $args);
      }
      $path = $url->getInternalPath();
      // Destroy a view instance.
      $view->destroy();
    }
    catch (\Exception $e) {
      // Delete records about arguments that are not added to the sitemap.
      if (!empty($data_set['index_id'])) {
        $condition = new Condition('AND');
        $condition->condition('id', $data_set['index_id']);
        $this->sitemapViews->removeArgumentsFromIndex($condition);
      }
      return FALSE;
    }

    return [
      'url' => $url,
      'lastmod' => NULL,
      'priority' => isset($settings['priority']) ? $settings['priority'] : NULL,
      'changefreq' => !empty($settings['changefreq']) ? $settings['changefreq'] : NULL,
      'images' => [],
      // Additional info useful in hooks.
      'meta' => [
        'path' => $path,
        'view_info' => [
          'view_id' => $view_id,
          'display_id' => $display_id,
          'arguments' => $args,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function generate($data_set) {
    $path_data = $this->processDataSet($data_set);
    if (isset($path_data['url']) && $path_data['url'] instanceof Url) {
      $url_object = $path_data['url'];
      unset($path_data['url']);
      return $this->getUrlVariants($path_data, $url_object);
    }
    else {
      return FALSE !== $path_data ? [$path_data] : [];
    }
  }

  /**
   * Clears the URL from parameters that are not present in the arguments.
   *
   * @param \Drupal\Core\Url $url
   *   The URL object.
   * @param array $args
   *   Array of arguments.
   *
   * @throws \UnexpectedValueException.
   *   If this is a URI with no corresponding route.
   */
  protected function cleanRouteParameters(Url $url, array $args) {
    $parameters = $url->getRouteParameters();
    // Check that the number of params does not match the number of arguments.
    if (count($parameters) != count($args)) {
      $route_name = $url->getRouteName();
      $route = $this->routeProvider->getRouteByName($route_name);
      $variables = $route->compile()->getVariables();
      // Remove params that are not present in the arguments.
      foreach ($variables as $variable_name) {
        if (empty($args)) {
          unset($parameters[$variable_name]);
        }
        else {
          array_shift($args);
        }
      }
      // Set new route params.
      $url->setRouteParameters($parameters);
    }
  }

  /**
   * Returns the URL variants for path data.
   *
   * @param array $path_data
   *   The sitemap path data.
   * @param \Drupal\Core\Url $url_object
   *   URL object associated with the path data.
   *
   * @return array
   *   An array of URL variants.
   */
  protected function getUrlVariants(array $path_data, Url $url_object) {
    $alternate_urls = [];
    $url_variants = [];

    // Get alternate URLs for all languages.
    if ($url_object->access($this->anonUser)) {
      foreach ($this->languages as $language) {
        if (!isset($this->settings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
          $url = $url_object->setOption('language', $language)->toString();
          $url = $this->replaceBaseUrlWithCustom($url);
          $alternate_urls[$language->getId()] = $url;
        }
      }
    }

    // Collect URL variants.
    foreach ($alternate_urls as $langcode => $url) {
      $url_variants[] = $path_data + [
          'langcode' => $langcode,
          'url' => $url,
          'alternate_urls' => $alternate_urls
        ];
    }
    return $url_variants;
  }

}
