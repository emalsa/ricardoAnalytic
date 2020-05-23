<?php

namespace Drupal\ra_article;

/**
 * Interface ArticleCrawlerInterface.
 */
interface ArticleCrawlerInterface {

  public function processArticle($articleId);

}
