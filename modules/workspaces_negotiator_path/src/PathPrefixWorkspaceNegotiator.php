<?php

namespace Drupal\workspaces_negotiator_path;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\workspaces\Negotiator\WorkspaceNegotiatorInterface;
use Drupal\workspaces\WorkspaceAccessException;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class PathPrefixWorkspaceNegotiator implements WorkspaceNegotiatorInterface {

  /**
   * The name of the private temp store.
   *
   * @var string
   */
  static $STORE = 'workspaces_negotiator_path';

  /**
   * The name of the key in the tempstore.
   *
   * @var string
   */
  static $KEY = 'new_workspace';

  /**
   * The workspace storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $workspaceStorage;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The workspaces' logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Return the path prefix for a given workspace, without the leading slash.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The given workspace.
   * @param bool $stripSlash
   *   Whether to remove the leading slash. Defaults to false.
   *
   * @return string|NULL
   */
  public static function getPrefix(WorkspaceInterface $workspace, $stripSlash = FALSE) {
    $prefix = trim($workspace->get('path_prefix')->value);
    if ($stripSlash) {
      $prefix = substr($prefix, 1);
    }
    return $prefix;
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The workspaces' logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $tempStoreFactory, LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->workspaceStorage = $entity_type_manager->getStorage('workspace');
    $this->tempStore = $tempStoreFactory->get(self::$STORE);
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspace(Request $request) {
    $valid_workspaces = array_map(function (WorkspaceInterface $workspace) {
      return [
        'id' => $workspace->id(),
        'path_prefix' => self::getPrefix($workspace),
      ];
    }, $this->getValidWorkspaces());
    $best_fit = PathPrefixHelper::findBestPathPrefixFit(urldecode($request->getPathInfo()), $valid_workspaces);
    if (!empty($best_fit)) {
      if ($best_fit['id'] && ($workspace = $this->workspaceStorage->load($best_fit['id']))) {
        return $workspace;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {
    $prefix = self::getPrefix($workspace, TRUE);
    if ($prefix === FALSE) {
      $message = 'The @name workspace doesn\'t have any path prefix set and thus is inactive.';
      $replacements = ['@name' => $workspace->label()];
      $this->messenger->addError(new TranslatableMarkup($message, $replacements));
      throw new WorkspaceAccessException();
    } else {
      $this->setRedirectPrefix($prefix);
    }
  }

  /**
   * Returns an array with all the workspaces which should be checked by the
   * path prefix negotiator.
   */
  protected function getValidWorkspaces() {
    $workspaces = $this->workspaceStorage->loadMultiple();
    return array_filter($workspaces, function ($workspace) {
      // Remove the workspaces which have an empty path_prefix field.
      $path_prefix = self::getPrefix($workspace);
      return !empty($path_prefix);
    });
  }

  /**
   * Returns the prefix of the workspace that is being activated.
   *
   * @return string|NULL
   *   The new workspace's prefix, without the leading slash.
   *
   * @see \Drupal\workspaces_negotiator_path\EventSubscriber\WorkspaceRedirectSubscriber::checkRedirection()
   */
  public function getRedirectPrefix() {
    return $this->tempStore->get(self::$KEY);
  }

  /**
   * Sets the redirect prefix.
   *
   * @param string $prefix
   *   The workspace prefix to set, without the leading slash.
   */
  public function setRedirectPrefix($prefix) {
    try {
      $this->tempStore->set(self::$KEY, $prefix);
    } catch (TempStoreException $e) {
      $this->logger->error('Could not save the redirection item to the tempstore.');
    }
  }

  /**
   * Clears the prefix, used when the redirection has taken place.
   *
   * @see \Drupal\workspaces_negotiator_path\EventSubscriber\WorkspaceRedirectSubscriber::checkRedirection()
   */
  public function deleteRedirectPrefix() {
    try {
      $this->tempStore->delete(self::$KEY);
    } catch (TempStoreException $e) {
      $this->logger->error('Could not delete the redirection item from the tempstore.');
    }
  }

  /**
   * Returns the given path without any workspace prefix.
   *
   * @param string $path
   *   The path as returned by parse_url, i.e. with the leading slash.
   *
   * @return string
   *   The path with workspace prefix stripped, starting with a slash.
   */
  public function stripWorkspacePrefix($path) {
    $pathParts = explode('/', $path);

    if (empty($pathParts)) {
      return '';
    }

    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    foreach ($this->getValidWorkspaces() as $workspace) {
      $prefix = self::getPrefix($workspace, TRUE);

      // Default workspaces don't have any prefix so there's nothing to strip.
      if (empty($prefix)) {
        continue;
      }

      if ($pathParts[1] === $prefix) {
        unset($pathParts[1]);
        break;
      }
    }

    // The path can consist solely of the workspace prefix which's been unset.
    if (empty($pathParts)) {
      return '';
    }

    return implode('/', $pathParts);
  }

}
