<?php

namespace Drupal\ra_article;

/**
 * Interface SellerArticlesServiceInterface.
 */
interface SellerArticlesServiceInterface {

  /**
   * Fetch open articles from seller.
   *
   * @return void
   */
  public function fetchSellerArticles(): void;

  /**
   * Fill queue with seller page urls to fetch.
   */
  public function createSellerArticleQueue(): void;

}
