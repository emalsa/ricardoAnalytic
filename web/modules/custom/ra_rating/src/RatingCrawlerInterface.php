<?php

namespace Drupal\ra_rating;

/**
 * Interface RatingCrawlerInterface.
 */
interface RatingCrawlerInterface {

  /**
   * Inits the crawler.
   *
   * @param int $nid
   *   The node id.
   */
  public function initRatingsCrawler(int $nid);

}
