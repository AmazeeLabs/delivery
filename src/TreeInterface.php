<?php

namespace Drupal\delivery;

interface TreeInterface {

  /**
   * Adds a node in the tree.
   *
   * @param \Drupal\delivery\TreeNode $node
   *  The node to be added.
   * @param \Drupal\delivery\TreeNode $parent
   *  Optional, the parent. If the parent is null, this node will be the root of
   *  the tree.
   */
  public function addNode(TreeNode $node, TreeNode $parent = NULL);

  /**
   * Returns the current root of the tree.
   *
   * @return \Drupal\delivery\TreeNode | NULL
   */
  public function getRoot();

  /**
   * Builds a tree for a document.
   *
   * @param \DOMDocument $document
   *  The DOM document.
   * @return \Drupal\delivery\Tree
   *  The tree object.
   */
  public static function buildTree(\DOMDocument $document);

  /**
   * Clones an entire subtree starting from a specific root node.
   *
   * @param \Drupal\delivery\TreeNode $root
   *  The root node to start with
   * @return Tree
   */
  public static function cloneSubtree(TreeNode $root);

  /**
   * Searches for a particular identifier in the tree.
   *
   * @param string $identifier
   *  The node identifier to search for.
   * @return \Drupal\delivery\TreeNode | NULL
   *  If the identifier has been found, it returns the node, NULL otherwise.
   */
  public function search($identifier);

  /**
   * Returns the path from the root of the tree to a specific node. The path is
   * an array of indices representing the position of each node between its
   * siblings, from the root of the tree to the current node.
   *
   * @param \Drupal\delivery\TreeNode $node
   *  The node to retrieve the path to.
   * @return array
   */
  public function getPathFromRoot(TreeNode $node);

  /**
   * Returns the node from a specific path. The path has the structure a defined
   * in the return value of ::getPathFromRoot().
   * @param array $path
   *  The path.
   * @return \Drupal\delivery\TreeNode | NULL
   */
  public function getNodeFromPath(array $path);
}
