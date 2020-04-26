<?php

namespace Drupal\ra_article\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_article\ArticleCrawler;
use Drupal\ra_article\ArticleCrawlerInterface;
use Drupal\ra_item\ItemRatingCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @var \Drupal\ra_article\ArticleCrawlerInterface
   */
  protected $articleCrawler;


  public function __construct(array $configuration, $plugin_id, $plugin_definition, ArticleCrawlerInterface $articleCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->articleCrawler = $articleCrawler;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ra_article.article_crawler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->articleCrawler->processArticle($data['article_id']);
  }

}
