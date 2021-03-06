<?php

namespace Drupal\workspaces_allowed_languages;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Subscriber class that make sure to have a valid workspace and language
 * combination.
 */
class RedirectListener implements EventSubscriberInterface {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   *  The workspace manager service.
   */
  protected $workspaceManger;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   * The language manager service.
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   * The module handler service.
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Session\AccountProxy
   *  The current user.
   */
  protected $currentUser;

  /**
   * RedirectListener constructor.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, AccountProxy $current_user) {
    $this->workspaceManger = $workspace_manager;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => 'onKernelRequest'];
  }

  /**
   * Performs a redirect in case the workspace and the language of the site do
   * not match.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    // Skip any checks for subrequests
    if (!$event->isMasterRequest()) {
      return;
    }
    // Skip any checks if the current user can bypass the workspaces language
    // restrictions.
    if ($this->currentUser->hasPermission('bypass workspaces language restrictions')) {
      return;
    }
    $current_workspace = $this->workspaceManger->getActiveWorkspace();
    $current_language = $this->languageManager->getCurrentLanguage();

    // If the workspace has any language restrictions, make sure that we are on
    // one of the allowed languages.
    $primary_language = $current_workspace->get('primary_language')->getValue();
    if (!$primary_language) {
      return;
    }
    $secondary_languages = $current_workspace->get('secondary_languages')->getValue();
    $allowed_languages = array_merge($primary_language, $secondary_languages);

    if (!empty($allowed_languages)) {
      $language_found = FALSE;
      foreach ($allowed_languages as $allowed_language) {
        if ($allowed_language['value'] == $current_language->getId()) {
          $language_found = TRUE;
          break;
        }
      }
      if (!$language_found) {
        $event->setResponse(new TrustedRedirectResponse('/system/404'));
        // TODO: Re-enable the redirect. Did not work reliably so it was turned into a 404 response.
//        $request = $event->getRequest();
//        // Get the request query, we want to keep them.
//        parse_str($event->getRequest()->getQueryString(), $request_query);
//        try {
//          $url = Url::createFromRequest($request);
//        }
//        catch (ResourceNotFoundException $e) {
//          return FALSE;
//        }
//        $url->setOption('query', (array) $url->getOption('query') + $request_query);
//
//        // For now, we just pick the first allowed language. As an improvement
//        // for the future, we could run the language negotiation again.
//        $language = $primary_language[0]['value'];
//        $url->setOption('language', $this->languageManager->getLanguage($language));
//
//        // Give other modules a chance to alter the redirect url.
//        $this->moduleHandler->alter('workspaces_allowed_languages_redirect', $url, $request);
//
//        $response = new TrustedRedirectResponse($url->toString());
//        $event->setResponse($response);
      }
    }
  }

}
