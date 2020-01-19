<?php

namespace Drupal\ra_seller\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Plugin implementation of the seller_queue queueworker.
 *
 * @QueueWorker (
 *   id = "seller_queue",
 *   title = @Translation("Seller Informations"),
 *   cron = {"time" = 30}
 * )
 */
class SellerQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Process item operations.
  }

}
