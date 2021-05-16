<?php

namespace Drupal\ra_rating;

/**
 * Interface RatingCrawlerInterface.
 */
interface RatingCrawlerInterface {

  /**
   * Inits the crawler.
   *
   * @param int $sellerNodeId
   *   The node id.
   */
  public function initRatingsCrawler(int $sellerNodeId);

}
