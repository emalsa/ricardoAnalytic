<?php

namespace Drupal\ra_article;

/**
 * Interface ArticleCrawlerInterface.
 */
interface ArticleCrawlerInterface {

  /**
   * Get node from given id and init the crawler.
   *
   * @param string $articleId
   *   The ricardo article id.
   */
  public function processArticle(string $articleId);

}
