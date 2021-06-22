<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface SimpleSitemapInterface extends ConfigEntityInterface {

  public function __toString(): string;

  public function published(): SimpleSitemapInterface;

  public function unpublished(): SimpleSitemapInterface;

  public function publishedAndUnpublished(): SimpleSitemapInterface;

  public function getType(): SimpleSitemapTypeInterface;

  public function getWeight(): int;

  public function toString(int $delta = NULL): string;

  public function publish(): SimpleSitemapInterface;

  public function deleteContent(): SimpleSitemapInterface;

  public function addChunk(array $links): SimpleSitemapInterface;

  public function generateIndex(): SimpleSitemapInterface;

  public function getChunk(int $delta = SimpleSitemapStorage::SITEMAP_CHUNK_FIRST_DELTA): string;

  public function getChunkCount(): int;

  public function hasIndex(): bool;

  public function getIndex(): string;

  public function status(): int;

  public function getCreated(): ?string;

  public function getLinkCount(): int;

  public function toUrlString(): string;

  public function isDefault(): bool;

  public function isMultilingual(): bool;
}
