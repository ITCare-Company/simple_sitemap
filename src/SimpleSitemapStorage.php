<?php

namespace Drupal\simple_sitemap;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simple_sitemap\Entity\SimpleSitemapInterface;
use Drupal\simple_sitemap\Exception\SitemapNotExistsException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo Interface.
 */
class SimpleSitemapStorage extends ConfigEntityStorage {
  public const SITEMAP_INDEX_DELTA = 0;
  public const SITEMAP_CHUNK_FIRST_DELTA = 1;

  protected const SITEMAP_PUBLISHED = 1;
  protected const SITEMAP_UNPUBLISHED = 0;

  protected $database;

  protected $time;

  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, Connection $database, TimeInterface $time) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager);
    $this->database = $database;
    $this->time = $time;
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
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove other bits.
   */
  protected function doDelete($entities) {
    /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface[] $entities */
    foreach ($entities as $entity) {
      $this->deleteContent($entity);
    }

    parent::doDelete($entities);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Sort by weight and other magic
   */
  protected function doLoadMultiple(array $ids = NULL) {
    return parent::doLoadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   *
   * @todo
   */
  protected function doSave($id, EntityInterface $entity) {
    return parent::doSave($id, $entity) ? SAVED_NEW : SAVED_UPDATED;
  }

  /*
   * @todo Costs too much.
   */
  protected function getChunkData(SimpleSitemapInterface $entity) {
    return \Drupal::database()->select('simple_sitemap', 's')
      ->fields('s', ['id', 'type', 'delta', 'sitemap_created', 'status', 'link_count'])
      ->condition('s.type', $entity->id())
      ->execute()
      ->fetchAllAssoc('id');
  }

  public function publish(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity): void {
    $unpublished_chunk = $this->database->query('SELECT MAX(id) FROM {simple_sitemap} WHERE type = :type AND status = :status', [
      ':type' => $entity->id(), ':status' => self::SITEMAP_UNPUBLISHED
    ])->fetchField();

    // Only allow publishing a sitemap variant if there is an unpublished
    // sitemap variant, as publishing involves deleting the currently published
    // variant.
    if (FALSE !== $unpublished_chunk) {
      $this->database->delete('simple_sitemap')->condition('type', $entity->id())->condition('status', self::SITEMAP_PUBLISHED)->execute();
      $this->database->query('UPDATE {simple_sitemap} SET status = :status WHERE type = :type', [':type' => $entity->id(), ':status' => self::SITEMAP_PUBLISHED]);
    }
  }

  public function deleteContent(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity): void {
    self::purgeContent($entity->id());
  }

//  protected function checkStatusSpecified($status): void {
//    if ($status === self::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED) {
//      throw new SitemapNotExistsException('Only a published or unpublished sitemap chunk can be retrieved, not both. Use SitemapContent::published or SitemapContent::unpublished before calling SitemapContent::getSitemapString.');
//    }
//  }

  public function addChunk(SimpleSitemapInterface $entity, string $xml, $link_count): void {
    $highest_delta = $this->database->query('SELECT MAX(delta) FROM {simple_sitemap} WHERE type = :type AND status = :status', [':type' => $entity->id(), ':status' => self::SITEMAP_UNPUBLISHED])
      ->fetchField();

    $this->database->insert('simple_sitemap')->fields([
      'delta' => NULL === $highest_delta ? self::SITEMAP_CHUNK_FIRST_DELTA : $highest_delta + 1,
      'type' =>  $entity->id(),
      'sitemap_string' => $xml,
      'sitemap_created' => $this->time->getRequestTime(),
      'status' => 0,
      'link_count' => $link_count,
    ])->execute();
  }

  public function generateIndex(SimpleSitemapInterface $entity, string $xml): void {
    $this->database->merge('simple_sitemap')
      ->keys([
        'delta' => self::SITEMAP_INDEX_DELTA,
        'type' => $entity->id(),
        'status' => 0
      ])
      ->insertFields([
        'delta' => self::SITEMAP_INDEX_DELTA,
        'type' =>  $entity->id(),
        'sitemap_string' => $xml,
        'sitemap_created' => $this->time->getRequestTime(),
        'status' => 0,
      ])
      ->updateFields([
        'sitemap_string' => $xml,
        'sitemap_created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  public function getChunkCount(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status = \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED): int {
    $query = $this->database->select('simple_sitemap', 's')
      ->condition('s.type', $entity->id())
      ->condition('s.delta', self::SITEMAP_INDEX_DELTA, '<>');

    if ($status !== \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED) {
      $query->condition('s.status', $status);
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * @todo Double query.
   */
  public function getChunk(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status, int $delta = SimpleSitemapStorage::SITEMAP_CHUNK_FIRST_DELTA): string {
    if ($delta === self::SITEMAP_INDEX_DELTA) {
      throw new SitemapNotExistsException('The sitemap chunk delta needs to be higher than 0.');
    }

    return $this->getSitemapString($entity, $this->getIdByDelta($entity, $delta, $status), $status);
  }

  public function hasIndex(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status): bool {
    try {
      $this->getIdByDelta($entity, self::SITEMAP_INDEX_DELTA, $status);
      return TRUE;
    }
    catch (SitemapNotExistsException $e) {
      return FALSE;
    }
  }

  /**
   * @todo Double query.
   */
  public function getIndex(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status): string {
    return $this->getSitemapString($entity, $this->getIdByDelta($entity, self::SITEMAP_INDEX_DELTA, $status), $status );
  }

  protected function getIdByDelta(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, int $delta, bool $status): int {
//    $this->checkStatusSpecified();
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($chunk->delta == $delta && $chunk->status == $status) {
        return $chunk->id;
      }
    }

    throw new SitemapNotExistsException();
  }

  protected function getSitemapString(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, int $id, bool $status): string {
    $chunk_data = $this->getChunkData($entity);
    if (!isset($chunk_data[$id])) {
      throw new SitemapNotExistsException();
    }

//    $this->checkStatusSpecified($status);

    if (empty($chunk_data[$id]->sitemap_string)) {
      $query = $this->database->select('simple_sitemap', 's')
        ->fields('s', ['sitemap_string'])
        ->condition('status', $status)
        ->condition('id', $id);

      $chunk_data[$id]->sitemap_string = $query->execute()->fetchField();
    }

    return $chunk_data[$id]->sitemap_string;
  }

  public function status(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity): int {
    foreach ($this->getChunkData($entity) as $chunk) {
      $status[$chunk->status] = $chunk->status;
    }

    if (!isset($status)) {
      return \Drupal\simple_sitemap\Entity\SimpleSitemap::SITEMAP_UNPUBLISHED;
    }

    if (count($status) === 1) {
      return (int) reset($status) === self::SITEMAP_UNPUBLISHED
        ? \Drupal\simple_sitemap\Entity\SimpleSitemap::SITEMAP_UNPUBLISHED
        : \Drupal\simple_sitemap\Entity\SimpleSitemap::SITEMAP_PUBLISHED;
    }

    return \Drupal\simple_sitemap\Entity\SimpleSitemap::SITEMAP_PUBLISHED_GENERATING;
  }

  public function getCreated(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status = NULL): ?string {
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($status === \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED || $chunk->status == $status) {
        return $chunk->sitemap_created;
      }
    }

    return NULL;
  }

  public function getLinkCount(\Drupal\simple_sitemap\Entity\SimpleSitemap $entity, bool $status = NULL): int {
    $count = 0;
    foreach ($this->getChunkData($entity) as $chunk) {
      if ($chunk->delta != self::SITEMAP_INDEX_DELTA
        && ($status === \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED || $chunk->status == $status)) {
        $count += (int) $chunk->link_count;
      }
    }

    return $count;
  }

  public static function purgeContent($variants = NULL, $status = \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED): void {
    $query = \Drupal::database()->delete('simple_sitemap');
    if ($status !== \Drupal\simple_sitemap\Entity\SimpleSitemap::FETCH_BY_STATUS_PUBLISHED_UNPUBLISHED) {
      $query->condition('status', $status);
    }
    if ($variants !== NULL) {
      $query->condition('type', (array) $variants, 'IN');
    }
    $query->execute();
  }

}
