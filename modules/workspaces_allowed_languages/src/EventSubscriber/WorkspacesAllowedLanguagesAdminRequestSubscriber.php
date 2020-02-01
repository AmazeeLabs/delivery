<?php

namespace Drupal\workspaces_allowed_languages\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class WorkspacesAllowedLanguagesAdminRequestSubscriber implements EventSubscriberInterface {

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  protected $config;

  /**
   * Constructs a new SmartCustomSubscriber object.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   *   The workspace manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(WorkspaceManagerInterface $workspaceManager, LanguageManagerInterface $languageManager, MessengerInterface $messenger, ConfigFactoryInterface $configFactory) {
    $this->workspaceManager = $workspaceManager;
    $this->languageManager = $languageManager;
    $this->messenger = $messenger;
    $this->config = $configFactory->get('workspaces_allowed_languages.settings');
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    // Run before the Dynamic Page Cache subscriber.
    $events['kernel.request'] = ['onRequest', 31];

    return $events;
  }

  /**
   * This method is called whenever the kernel.request event is
   * dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   */
  public function onRequest(Event $event) {
    if (!$event instanceof GetResponseEvent || !$event->isMasterRequest()) {
      return;
    }

    // Bail out if the request subscriber setting is not active.
    $requestSubscriberSettings = $this->config->get('admin_request_subscriber');
    if (empty($requestSubscriberSettings['active'])) {
      return;
    }

    // Bail out if the current active workspace couldn't be determined or
    // we're in the middle of switching to another one.
    $route = $event->getRequest()->attributes->get('_route');
    $workspace = $this->workspaceManager->getActiveWorkspace();
    if (!$workspace || $route == 'entity.workspace.activate_form') {
      return;
    }

    $contentLanguage = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $availableLanguages = [];
    if (!$workspace->get('primary_language')->isEmpty()) {
      $availableLanguages[] = $workspace->get('primary_language')->value;
    }
    foreach ($workspace->get('secondary_languages') as $item) {
      $availableLanguages[] = $item->value;
    }

    $anyLanguageSet = !empty($availableLanguages);
    $contentLanguageAllowed = in_array($contentLanguage->getId(), $availableLanguages);
    if (!$anyLanguageSet || $contentLanguageAllowed) {
      return;
    }

    try {
      /** @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $event->getRequest();
      $options = [
        'absolute' => TRUE,
        'language' => $this->languageManager->getLanguage($availableLanguages[0]),
      ];
      $uri = $request->getRequestUri();
      $url = Url::fromUri('internal:' . $uri, $options);

      if (in_array($url->getRouteName(), $requestSubscriberSettings['routes'])) {
        $request->query->remove('destination');
        $event->setResponse(new TrustedRedirectResponse($url->toString()));

        $this->messenger->addStatus(new TranslatableMarkup(
          'The %language language is not available in the current workspace. You have been redirected.',
          ['%language' => $contentLanguage->getName()]));
      }
    } catch (\Exception $ex) {
      $this->messenger->addStatus(new TranslatableMarkup(
        'The %language language is not available in the current workspace.',
        ['%language' => $contentLanguage->getName()]));
    }
  }

}
