<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SimpleSitemapTypeStorage extends ConfigEntityStorage {

  protected $entityTypeManager;

  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var SimpleSitemapInterface $entity */
    if (!preg_match('/^[\w\-_]+$/', $id)) {
      throw new \InvalidArgumentException("The sitemap ID can only include alphanumeric characters, dashes and underscores.");
    }

    if ($entity->get('sitemap_generator') === NULL || $entity->get('sitemap_generator') === '') {
      throw new \InvalidArgumentException("The sitemap type must define its sitemap generator.");
    }

    if (empty($entity->get('url_generators'))) {
      throw new \InvalidArgumentException("The sitemap type must define its URL generators");
    }

    if ($entity->label() === NULL || $entity->label() === '') {
      $entity->set('label', $id);
    }

    return parent::doSave($id, $entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface[] $entities */
    $sitemap_storage = $this->entityTypeManager->getStorage('simple_sitemap');
    $sitemaps_by_type = [];
    foreach ($entities as $entity) {
      $sitemaps_by_type[] = $sitemap_storage->loadByProperties(['type' => $entity->id()]);
    }
    $sitemap_storage->delete(array_merge([], ...$sitemaps_by_type));

    parent::doDelete($entities);
  }

}
