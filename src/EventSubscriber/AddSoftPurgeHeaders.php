<?php

/**
 * @file
 * Contains \Drupal\Fastly\EventSubscriber\CacheTagsHeaderLimitDetector.
 */

namespace Drupal\fastly\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\fastly\Form\FastlySettingsForm;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddSoftPurgeHeaders implements EventSubscriberInterface {

  /**
   * The Fastly logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a new CacheTagsHeaderLimitDetector object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The Fastly logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    $this->logger = $logger;
    $this->config = $config_factory;
  }

  /**
   * Adds Cache-Control headers if soft purging is enabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onRespond(FilterResponseEvent $event) {
    // Get the fastly settings from configuration.
    $config = $this->config->get('fastly.settings');

    // Only modify the master request.
    if ((!$event->isMasterRequest()) || (!($config->get('purge_method') == FastlySettingsForm::FASTLY_SOFT_PURGE))) {
      return;
    }

    // Get response.
    $response = $event->getResponse();

    // Build the modified Cache-Control header.
    $cache_control_header = $response->headers->get('Cache-Control');
    $cache_control_header .= ', stale-while-revalidate=' . $config->get('stale_while_revalidate_value');

    if ((bool) $config->get('stale_if_error')) {
      $cache_control_header .= ', stale-if-error=' . $config->get('stale_if_error_value');
    }

    // Set the modified Cache-Control header.
    $response->headers->set('Cache-Control', $cache_control_header);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
