<?php

namespace Drupal\delivery;

/**
 * Interface for the document merge service class.
 */
interface DocumentMergeInterface {

  /**
   * Performs a merge operation of the left document to the right document.
   *
   * @param string $source
   *  The lowest common ancestor of the two documents.
   * @param string $left
   *  The left document.
   * @param string $right
   *  The right document.
   * @return string
   *  The merged document.
   */
  public function merge($source, $left, $right);
}
