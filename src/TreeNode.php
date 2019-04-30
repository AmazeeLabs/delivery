<?php

namespace Drupal\delivery;

class TreeNode implements TreeNodeInterface {

  /**
   * @var string
   * The unique identifier of the node.
   */
  protected $id;

  /**
   * @var \Drupal\delivery\TreeNode[]
   * The children list.
   */
  protected $children;

  /**
   * @var \DOMNode
   * The actual DOM node this tree node is referencing to.
   */
  protected $domNode;

  /**
   * @var \Drupal\delivery\TreeNode
   * The parent of this node.
   */
  protected $parent;

  /**
   * Various flags that can be added to a node.
   *
   * @var array
   */
  protected $flags;

  public function __construct($id, \DOMNode $dom_node = NULL, TreeNode $parent = NULL, $children = []) {
    $this->id = $id;
    $this->children = $children;
    $this->domNode = $dom_node;
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function addChild(TreeNode $child) {
    $this->children[] = $child;
    $child->setParent($this);
  }

  /**
   * {@inheritdoc}
   */
  public function removeChild($index) {
    if (!empty($this->children[$index])) {
      unset($this->children[$index]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setParent(TreeNode $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    return $this->children;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomNode() {
    return $this->domNode;
  }

  /**
   * {@inheritdoc}
   */
  public function flag($flag_name, $flag_value, $flag_children = FALSE) {
    $this->flags[$flag_name] = $flag_value;
    // Apply the flag to all the children if needed.
    if ($flag_children) {
      foreach ($this->getChildren() as $child) {
        $child->flag($flag_name, $flag_value, $flag_children);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unflag($flag_name) {
    if (isset($this->flags[$flag_name])) {
      unset($this->flags[$flag_name]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFlag($flag_name) {
    return $this->flags[$flag_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPosition() {
    // If the node has no parent, it is on the first position.
    if (empty($this->parent)) {
      return 0;
    }
    // The indexes of the children array are not safe to be used for getting the
    // position because children can be also removed from a node, in which case
    // we can have a array with indexes like: 0,1,3,5,9.
    $position = 0;
    foreach ($this->parent->getChildren() as $child) {
      if ($child->getId() === $this->getId()) {
        return $position;
      }
      $position++;
    }
    // This should not happen unless the node has a reference to a parent which
    // is not actually its parent (so the parent does not have it in the
    // children list), but if we are here just return 0, because this is still
    // a single node.
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isLeaf() {
    return empty($this->children);
  }

  /**
   * {@inheritdoc}
   */
  public function matchContent(TreeNode $node) {
    // Matching the content mean basically just comparing the html content of
    // the referencing DOM nodes.
    return $this->domNode->ownerDocument->saveHTML($this->domNode) === $node->domNode->ownerDocument->saveHTML($node->domNode);
  }

  /**
   * {@inheritdoc}
   */
  public function matchPosition(TreeNode $node) {
    // Two nodes have the same position (withing different trees) if:
    // 1. Their parents have the same ids.
    // 2. They have the same index (position) within their siblings.
    $nodeParent = $node->getParent();
    $currentParent = $this->getParent();
    // If only one of the parents is empty, then the positions don't match.
    if ((empty($nodeParent) && !empty($currentParent)) || (empty($currentParent) && !empty($nodeParent))) {
      return FALSE;
    }
    // If both parents are empty, then just return TRUE, they are both root.
    if (empty($nodeParent) && empty($currentParent)) {
      return TRUE;
    }
    return ($this->getParent()->getId() === $node->getParent()->getId() && $this->getPosition() === $node->getPosition());
  }

  /**
   * {@inheritdoc}
   */
  public function matchAttributes(TreeNode $node) {
    return serialize($this->getSortedAttributes()) === serialize($node->getSortedAttributes());
  }

  /**
   * Returns the attributes of the referencing DOMElement, sorted
   * alphabetically.
   *
   * @return array
   */
  protected function getSortedAttributes() {
    if (empty($this->domNode)) {
      return [];
    }
    $attributes = iterator_to_array($this->domNode->attributes);
    if (empty($attributes)) {
      return [];
    }
    foreach ($attributes as $key => $value) {
      $attributes[$key] = $value->value;
    }
    ksort($attributes);
    // The class attribute is also a bit special. We want to sort the class
    // values alphabetically so that we can easy use the class information for
    // comparison.
    if (!empty($attributes['class'])) {
      $words = explode(' ', $attributes['class']);
      asort($words);
      $attributes['class'] = implode(' ', $words);
    }
    return $attributes;
  }
}
