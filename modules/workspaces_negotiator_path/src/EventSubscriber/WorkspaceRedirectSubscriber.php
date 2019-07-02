<?php

namespace Drupal\workspaces_negotiator_path\EventSubscriber;

use Drupal\workspaces_negotiator_path\PathPrefixWorkspaceNegotiator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class EventSubscriber.
 */
class WorkspaceRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The workspace negotiator service for path prefix.
   *
   * @var PathPrefixWorkspaceNegotiator
   */
  protected $pathPrefixWorkspaceNegotiator;

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['kernel.response'] = ['checkRedirection'];

    return $events;
  }

  /**
   * Turns the result of parse_url back into a string.
   *
   * @param array $urlParts
   *   An array like the one parse_url returns.
   *
   * @return string
   *
   * @see https://pageconfig.com/post/unparse_url-reverse-of-parse_url-in-php
   */
  static function buildUrl($urlParts) {
    $parts = [];
    $parts['scheme'] = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
    $parts['host'] = isset($urlParts['host']) ? $urlParts['host'] : '';
    $parts['port'] = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
    $parts['user'] = isset($urlParts['user']) ? $urlParts['user'] : '';
    $parts['pass'] = isset($urlParts['pass']) ? ':' . $urlParts['pass'] : '';
    $parts['pass'] = ($parts['user'] || $parts['pass']) ? $parts['pass'] . "@" : '';
    $parts['path'] = isset($urlParts['path']) ? $urlParts['path'] : '';
    $parts['query'] = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
    $parts['fragment'] = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
    return implode('', $parts);
  }

  /**
   * Constructs a new EventSubscriber object.
   *
   * @param \Drupal\workspaces_negotiator_path\PathPrefixWorkspaceNegotiator $pathPrefixWorkspaceNegotiator
   *   The workspace negotiator service for path prefix.
   */
  public function __construct(PathPrefixWorkspaceNegotiator $pathPrefixWorkspaceNegotiator) {
    $this->pathPrefixWorkspaceNegotiator = $pathPrefixWorkspaceNegotiator;
  }

  /**
   * This method is called whenever the kernel.response event is dispatched.
   *
   * @param FilterResponseEvent $event
   *   The event object.
   */
  public function checkRedirection(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (($prefix = $this->pathPrefixWorkspaceNegotiator->getRedirectPrefix())
      && $response instanceOf RedirectResponse
    ) {
      $this->pathPrefixWorkspaceNegotiator->deleteRedirectPrefix();
      $url = $response->getTargetUrl();
      $urlParts = parse_url($url);
      $pathWithoutPrefix = $this->pathPrefixWorkspaceNegotiator
        ->stripWorkspacePrefix($urlParts['path']);
      $urlParts['path'] = "/$prefix$pathWithoutPrefix";
      $response->setTargetUrl(self::buildUrl($urlParts));
    }
  }

}
