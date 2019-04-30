<?php

namespace Drupal\delivery;

interface TreeNodeInterface {

  /**
   * Adds a child to the current node.
   *
   * @param \Drupal\delivery\TreeNode $child
   *  The child to be added.
   */
  public function addChild(TreeNode $child);

  /**
   * Removes a child from a specific index.
   *
   * @param int $index
   *  The position of the child to be removed
   */
  public function removeChild($index);

  /**
   * Sets the parent of the current node.
   *
   * @param \Drupal\delivery\TreeNode $parent
   *  The parent to be set.
   */
  public function setParent(TreeNode $parent);

  /**
   * Returns the parent of the current node.
   *
   * @return \Drupal\delivery\TreeNode | NULL
   */
  public function getParent();

  /**
   * Returns the identifier of the node.
   *
   * @return string
   */
  public function getId();

  /**
   * Returns all the children of this node.
   *
   * @return \Drupal\delivery\TreeNode[]
   */
  public function getChildren();

  /**
   * Returns the DOM node of this tree node.
   * @return \DOMNode | NULL
   */
  public function getDomNode();

  /**
   * Flags the current node using a flag name and a value.
   *
   * @param string $flag_name
   *  The name of the flag.
   * @param string $flag_value
   *  The value of the flag.
   * @parm bool $flag_children
   *  If the flag should also apply, recursively, to all the children.
   */
  public function flag($flag_name, $flag_value, $flag_children = FALSE);

  /**
   * Removes a flag from the node.
   *
   * @param string $flag_name
   *  The flag name.
   */
  public function unflag($flag_name);

  /**
   * Retrieves the value of a flag.
   *
   * @param string $flag_name
   *  The flag name.
   */
  public function getFlag($flag_name);

  /**
   * Returns the position of the elements within its siblings.
   *
   * @return int
   */
  public function getPosition();

  /**
   * Returns TRUE if the current node is a leaf (has no children), FALSE
   * otherwise
   * @return bool
   */
  public function isLeaf();

  /**
   * Matches the content of the current node to another one.
   *
   * @param \Drupal\delivery\TreeNode $node
   *  The tree node to compare with.
   * @return bool
   */
  public function matchContent(TreeNode $node);

  /**
   * Matches the position of the current node to another one. This usually means
   * that they have the same parent id and the same index within the siblings.
   *
   * @param \Drupal\delivery\TreeNode $node
   *  The tree node to compare with.
   * @return bool
   */
  public function matchPosition(TreeNode $node);

  /**
   * Matches the attributes of the current node to another one.
   *
   * @param \Drupal\delivery\TreeNode $node
   * @return bool
   */
  public function matchAttributes(TreeNode $node);
}
