<?php

namespace Drupal\ra_item;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;

/**
 * Class ItemRatingCrawler.
 */
class ItemRatingCrawler implements ItemRatingCrawlerInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs a new ItemRatingCrawler object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
  }

}
