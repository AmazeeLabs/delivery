<?php

namespace Drupal\workspaces_negotiator_path\Utils;

/**
 * Utility class for different path prefix helper methods.
 */
class PathPrefixHelper {

  /**
   * Helper function to find the best path prefix fit, given a path and a set of
   * prefixes.
   *
   * @param string $path
   *   The source string to be checked.
   * @param array $prefixes
   *   An array of prefixes. Each item in the array must have a 'path_prefix'
   *   attribute.
   *
   * @return mixed|null
   */
  public static function findBestPathPrefixFit($path, array $prefixes) {
    $best_fit = NULL;
    foreach ($prefixes as $prefix) {
      // We update the best_fit prefix only if we can find a, well, better fit.
      // This means that the prefix matches the path and the length is bigger
      // than the current match.
      if (self::pathPrefixMatch($path, $prefix['path_prefix']) && (empty($best_fit) || strlen($best_fit['path_prefix']) < strlen($prefix['path_prefix']))) {
        $best_fit = $prefix;
      }
    }
    return $best_fit;
  }

  /**
   * Helper function to determine if a prefix matches a path.
   *
   * @param $path
   * @param $prefix
   *
   * @return bool
   */
  public static function pathPrefixMatch($path, $prefix) {
    // A prefix matches the path if any of the following is true:
    // 1. The path prefix to check is '/'. This one matches every path.
    // 2. The path prefix is identical with the source.
    // 3. The source starts with the path prefix concatenated with '/'. For
    // example: "/stage" is a prefix for a path like "/stage/node/1", but not
    // for a path like "/stage_env/node/1".
    if ($prefix === '/' || $path === $prefix || strpos($path, $prefix . '/') === 0) {
      return TRUE;
    }
    return FALSE;
  }

}
