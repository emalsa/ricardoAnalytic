<?php

namespace Drupal\ra_article\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Plugin implementation of the article_queue queueworker.
 *
 * @QueueWorker (
 *   id = "article_queue",
 *   title = @Translation("Article"),
 *   cron = {"time" = 30}
 * )
 */
class ArticleQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Process item operations.
  }

}
