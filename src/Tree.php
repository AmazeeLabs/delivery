<?php

namespace Drupal\delivery;

class Tree implements TreeInterface {

  /**
   * @var \Drupal\delivery\TreeNode
   * The root of the tree.
   */
  protected $root;

  /**
   * {@inheritdoc}
   */
  public function addNode(TreeNode $node, TreeNode $parent = NULL) {
    // When there is no parent, the node will become the root of the tree.
    // Otherwise, just add a new child to the parent.
    if (is_null($parent)) {
      $this->root = $node;
    }
    else {
      $parent->addChild($node);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRoot() {
    return $this->root;
  }

  /**
   * {@inheritdoc}
   */
  public function search($identifier) {
    return $this->doSearch($identifier, $this->root);
  }

  /**
   * Performs the actual search for an identifier by recursively traversing the
   * tree.
   *
   * @param string $identifier
   *   The identifier to search for.
   * @param \Drupal\delivery\TreeNode $node
   *   The current tree node being processed.
   *
   * @return \Drupal\delivery\TreeNode | NULL
   *   If the identifier was found, returns the tree node. Otherwise returns
   *   NULL.
   */
  protected function doSearch($identifier, TreeNode $node) {
    if ($node->getId() === $identifier) {
      return $node;
    }
    foreach ($node->getChildren() as $child) {
      $found = $this->doSearch($identifier, $child);
      if (!empty($found)) {
        return $found;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildTree(\DOMDocument $document) {
    $xpath = new \DOMXPath($document);
    // The root of the tree has to be a div with the 'root' id.
    $items = iterator_to_array($xpath->query("//body/*", $document));
    if (empty($items)) {
      return NULL;
    }

    $tree_root = new TreeNode('root', $items[0]);

    // Create the tree and add the first node, which will be the root.
    $tree = new Tree();
    $tree->addNode($tree_root);
    self::_buildTree($items[0], $tree_root, $tree);
    return $tree;
  }

  /**
   * Recursively builds a tree.
   *
   * @param \DOMNode $node
   *   The current DOM node to process.
   * @param \Drupal\delivery\TreeNode $parent
   *   The tree node parent to which we want to add the new nodes.
   * @param \Drupal\delivery\Tree $tree
   *   The entire tree object.
   */
  protected static function _buildTree(\DOMNode $node, TreeNode $parent, Tree $tree) {
    if (!empty($node->childNodes)) {
      foreach (iterator_to_array($node->childNodes) as $index => $child) {
        // Process everything that has a class.
        // TODO: Find a more solid approach. This won't hold for media elements
        //       or formatted content that contains classes.
        if ($child instanceof \DOMElement && $child->hasAttribute('class')) {
          $identifier = self::getIdentifierForNode($child);
          // Prepend the identifier of the parent. If we want to be able to
          // detect movements between different tree branches, then we should
          // not add the parent id.
          $identifier = $parent->getId() . '-' . $identifier;
          $tree_node = new TreeNode($identifier, $child);
          // We could basically just add the child to the parent directly, but
          // we do this via the tree in case we want to perform any other logic
          // in the Tree class when adding an child.
          $tree->addNode($tree_node, $parent);
          self::_buildTree($child, $tree_node, $tree);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function cloneSubtree(TreeNode $root) {
    $tree = new Tree();
    self::_cloneSubtree($root, $tree);
    return $tree;
  }

  /**
   * Recursively clones a subtree.
   *
   * @param \Drupal\delivery\TreeNode $node
   *   The current node to be processed.
   * @param \Drupal\delivery\Tree $tree
   *   The tree to add the node to.
   * @param \Drupal\delivery\TreeNode $parent
   *   The parent to be set for the current node.
   */
  protected static function _cloneSubtree(TreeNode $node, Tree $tree, TreeNode $parent = NULL) {
    // The cloned node will actually reference to the exact same domNode, but
    // will have different parent and of course different children references.
    $cloned_node = new TreeNode($node->getId(), $node->getDomNode());
    // Add first the cloned node to the tree.
    $tree->addNode($cloned_node, $parent);
    // Iterate over all the children and clone their branches. Each child will
    // have as parent the current cloned node.
    foreach ($node->getChildren() as $child) {
      self::_cloneSubtree($child, $tree, $cloned_node);
    }
  }

  /**
   * Generates the identifier of a node to be used in the tree.
   *
   * If the node contains the id attribute, it will just use that. Otherwise it
   * will use the tag name and the hash of the class attribute (the classes are
   * first sorted alphabetically).
   *
   * @param \DOMNode $node
   *   The DOM node for which to get the identifier.
   *
   * @return string
   */
  protected static function getIdentifierForNode(\DOMNode $node) {
    $identifier = $node->getAttribute('id');
    if (empty($identifier)) {
      $identifier = $node->getAttribute('class');
      if (!empty($identifier)) {
        $words = explode(' ', $identifier);
        asort($words);
        $identifier = md5(implode(' ', $words));
      }
      $identifier = $node->tagName . '-' . $identifier;
    }
    return $identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathFromRoot(TreeNode $node) {
    // We start with the position of the current node.
    $path = [$node->getPosition()];
    // While the node has parents, go up in the tree and add the position of the
    // nodes to the path array.
    while ($node = $node->getParent()) {
      array_unshift($path, $node->getPosition());
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeFromPath(array $path) {
    if (empty($path)) {
      return NULL;
    }

    // We ignore the first item in the $path,because this should actually be the
    // root.
    return array_reduce(array_slice($path, 1), function ($carry, $index) {
      if ($carry instanceof TreeNodeInterface) {
        return !empty($carry->getChildren()[$index]) ? $carry->getChildren()[$index] : NULL;
      }
      return NULL;
    }, $this->root);
  }

}
