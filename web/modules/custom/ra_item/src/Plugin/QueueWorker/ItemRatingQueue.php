<?php

namespace Drupal\ra_item\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Plugin implementation of the item_rating_queue queue worker.
 *
 * @QueueWorker (
 *   id = "item_rating_queue",
 *   title = @Translation("Item rating"),
 *   cron = {"time" = 30}
 * )
 */
class ItemRatingQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Process item operations.
  }

}
