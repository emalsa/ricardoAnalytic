<?php

namespace Drupal\ra_article;

/**
 * Interface ArticleDetailFetchServiceInterface.
 */
interface ArticleDetailFetchServiceInterface {

  /**
   * Get the article nids to fetch.
   *
   * @return array
   *   The article nids.
   */
  public function getArticleNidsToFetch(): array;

  /**
   * Fetch the articles details.
   *
   * @param  array  $articleNids
   *   The article node ids.
   */
  public function fetchArticleDetail(array $articleNids): void;

}
