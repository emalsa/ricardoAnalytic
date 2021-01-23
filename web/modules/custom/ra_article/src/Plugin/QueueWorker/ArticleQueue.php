<?php

namespace Drupal\ra_article\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_article\ArticleCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the article_queue queueworker.
 *
 * @QueueWorker (
 *   id = "article_queue",
 *   title = @Translation("Article"),
 *   cron = {"time" = 55}
 * )
 */
class ArticleQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The article crawler services.
   *
   * @var \Drupal\ra_article\ArticleCrawlerInterface
   */
  protected $articleCrawler;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ArticleCrawlerInterface $articleCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->articleCrawler = $articleCrawler;
  }

  /**
   * {@inheritDoc}
   */
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
