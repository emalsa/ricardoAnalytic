<?php

namespace Drupal\ra_item\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_item\ItemRatingCrawler;
use Drupal\ra_item\ItemRatingCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the item_rating_queue queue worker.
 *
 * @QueueWorker (
 *   id = "item_rating_queue",
 *   title = @Translation("Item rating"),
 *   cron = {"time" = 90}
 * )
 */
class ItemRatingQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\ra_item\ItemRatingCrawlerInterface
   */
  protected $itemRatingCrawler;

  /**
   * ItemRatingQueue constructor.
   *
   * @param  array  $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param  \Drupal\ra_item\ItemRatingCrawlerInterface  $itemRatingCrawler
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ItemRatingCrawlerInterface $itemRatingCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->itemRatingCrawler = $itemRatingCrawler;
  }

  /**
   * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
   * @param  array  $configuration
   * @param  string  $plugin_id
   * @param  mixed  $plugin_definition
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\ra_item\Plugin\QueueWorker\ItemRatingQueue
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ra_item.item_rating_crawler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->itemRatingCrawler->initItemRatingsCrawler($data['seller_nid']);
  }

}
