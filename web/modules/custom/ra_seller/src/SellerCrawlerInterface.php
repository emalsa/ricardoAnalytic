<?php

namespace Drupal\ra_seller;

/**
 * Interface SellerCrawlerInterface.
 */
interface SellerCrawlerInterface {

  /**
   * Init crawler and get sellers page.
   *
   * @param int $nid
   *   The sellers nid.
   */
  public function initSellerCrawling(int $nid): void;

}
