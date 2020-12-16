<?php

namespace Drupal\ra_rating\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_rating\RatingCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the item_rating_queue queue worker.
 *
 * @QueueWorker (
 *   id = "rating_queue",
 *   title = @Translation("Rating"),
 *   cron = {"time" = 90}
 * )
 */
class RatingQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\ra_rating\RatingCrawlerInterface
   */
  protected $ratingCrawler;

  /**
   * ItemRatingQueue constructor.
   *
   * @param  array  $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param  \Drupal\ra_rating\RatingCrawlerInterface  $ratingCrawler
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RatingCrawlerInterface $ratingCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ratingCrawler = $ratingCrawler;
  }

  /**
   * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
   * @param  array  $configuration
   * @param  string  $plugin_id
   * @param  mixed  $plugin_definition
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\ra_rating\Plugin\QueueWorker\RatingQueue
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ra_rating.rating_crawler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->ratingCrawler->initRatingsCrawler($data['seller_nid']);
  }

}
