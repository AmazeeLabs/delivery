<?php

namespace Drupal\delivery;

class DocumentMerge implements DocumentMergeInterface {

  /**
   * The source tree.
   *
   * @var \Drupal\delivery\Tree
   */
  protected $sourceTree;

  /**
   * The left tree.
   *
   * @var \Drupal\delivery\Tree
   */
  protected $leftTree;

  /**
   * The right tree.
   *
   * @var \Drupal\delivery\Tree
   */
  protected $rightTree;

  /**
   * The result (merge) tree.
   *
   * @var \Drupal\delivery\Tree
   */
  protected $resultTree;

  /**
   * Conflict options labels.
   *
   * @var array
   */
  protected $labels;

  /**
   * {@inheritdoc}
   */
  public function merge($source, $left, $right) {
    $this->sourceTree = $this->getTree($source);
    $this->leftTree = $this->getTree($left);
    $this->rightTree = $this->getTree($right);
    $this->resultTree = new Tree();
    $this->traverseRight($this->rightTree->getRoot(), $this->resultTree->getRoot());
    $this->traverseLeft($this->leftTree->getRoot());
    $this->removeDuplicatesFromTree($this->resultTree->getRoot());

    $result = new \DOMDocument();
    $result->formatOutput = TRUE;
    $element = $result->createElement('body');
    $result->appendChild($element);
    $this->buildDocumentFromTree($this->resultTree->getRoot(), $element, $result);

    $body = $result->getElementsByTagName('body')[0];
    $resultHmtl = $this->getDOMInnerHtml($body);
    return $resultHmtl;
  }

  /**
   * Returns the inner html of a DOM node.
   *
   * @param \DOMNode $node
   *   The DOM node.
   *
   * @return string
   *   The html result.
   */
  protected function getDOMInnerHtml(\DOMNode $node) {
    $innerHTML = "";
    foreach ($node->childNodes as $child) {
      $innerHTML .= $node->ownerDocument->saveHTML($child);
    }
    return $innerHTML;
  }

  /**
   * Traverses the right tree in preorder and searches all its elements in the
   * left tree. This is the heart of the algorithm. It will assemble most of the
   * parts in the result tree.
   *
   * @param \Drupal\delivery\TreeNode $currentNode
   *   The node being currently processed from the right tree.
   * @param \Drupal\delivery\TreeNode $resultParent
   *   The parent node in the result tree to which we will add the new items.
   */
  protected function traverseRight(TreeNode $currentNode, TreeNode $resultParent = NULL) {
    $stopTraversing = FALSE;
    $nextResultParent = NULL;
    // When processing the current node, the first thing we do is to actually
    // search it in the left tree.
    $leftNode = $this->leftTree->search($currentNode->getId());
    if (!empty($leftNode)) {
      // If we found the element in the left tree, we still have a few cases:
      // 1. The position is identical.
      // 2. The position is different.
      // Case 1: The same position.
      if ($currentNode->matchPosition($leftNode)) {
        // We found the same element, on the same position, so we now also have
        // other cases:
        // 1.1 The node is a leaf: if there is no conflict (content and
        // attributes) between the two nodes then we just add the node to the
        // result tree. If there is a conflict, then we need to check the value
        // from the source tree. If it is identical to the right, then this is
        // not actually a conflict, but an update from left. If it is identical
        // to the left, then it is an update from right. In both cases, we just
        // copy the updated element into the result tree. If the source is not
        // identical to either of them, then we add a conflict node which
        // contains the information from the source, the left and the right
        // nodes.
        // 1.2 The node is not a leaf: if there is no conflict between the
        // attributes, then we just add a new node in the result tree, having
        // the same identifier and pointing to a DOMNode constructed based on
        // the meta information from the DOMNode of the current node, but with
        // no content. If there is a conflict in the attributes, then basically
        // the entire subtree is in conflict, so we have to go again and compare
        // the nodes with the source ones, like in 1.1. If there is a node
        // identical with the right, then this is an updated from left. If it is
        // identical to the left, it is an update from right. In both cases,
        // just copy the updated element, with the entire subtree, in the
        // result. If there is no match, then this is a big conflict, so we will
        // create a node with references to the source, left and right subtree.

        // Case 1.1
        if ($currentNode->isLeaf() && $leftNode->isLeaf()) {
          // Check now if the content and attributes are the same. If yes, just
          // add the node to the result tree. Also, stop traversing, it does
          // not make sense to check any other children, and flag all the left
          // items as processed.
          if ($currentNode->matchAttributes($leftNode) && $currentNode->matchContent($leftNode)) {
            $clonedTree = Tree::cloneSubtree($currentNode);
            $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
          }
          else {
            // We have a conflict between left and right.
            $sourceNode = $this->sourceTree->search($currentNode->getId());
            if (!empty($sourceNode)) {
              // This is actually a real conflict. There is no match between
              // the source, left and right, except the position.
              $id = 'conflict-' . $currentNode->getId();
              $conflictNode = $this->createConflictNode($id, $currentNode->getDomNode()->ownerDocument, $sourceNode, $leftNode, $currentNode);
              $this->resultTree->addNode($conflictNode, $resultParent);
            }
            else {
              // Pretty unlikely to happen, the case when both, left and right,
              // add exactly the same content, including the id.
              $id = 'conflict-' . $currentNode->getId();
              $conflictNode = $this->createConflictNode($id, $this->sourceTree->getRoot()->getDomNode()->ownerDocument, $leftNode, $currentNode);
              $this->resultTree->addNode($conflictNode, $resultParent);
            }
          }
          // In any of the cases, the left node and its branch should be marked
          // as processed.
          $leftNode->flag('processed', TRUE, TRUE);
          // We also stop traversing here (anyway, if this is a leaf, there will
          // be no children to process).
          $stopTraversing = TRUE;
        }
        // Case 1.2: the left and right positions match, but the node is not a
        // leaf.
        else {
          if ($sourceNode = $this->sourceTree->search($currentNode->getId())) {
            // First try to merge attributes from left to right.
            // TODO: Implement actual attribute conflict resolution.
            $currentNode->mergeAttributes($leftNode, $sourceNode);
          }

          // Check first if the nodes have the same attributes. In that case,
          // the node can be added to the result tree, and we will continue
          // checking the branches for potential conflicts. The is actually the
          // only case when we don't stop traversing the children of the current
          // node.
          if ($currentNode->matchAttributes($leftNode)) {
            $resultNode = new TreeNode($currentNode->getId(), $currentNode->getDomNode()->cloneNode(FALSE));
            $this->resultTree->addNode($resultNode, $resultParent);
            $leftNode->flag('processed', TRUE);
            $nextResultParent = $resultNode;
          }
          else {
            // In this case we have a conflict in the attributes. So we have to
            // go back and check the source node. The scenario is actually
            // similar with 1.1, but we only check the attributes, not the
            // content.
            $sourceNode = $this->sourceTree->search($currentNode->getId());
            if (!empty($sourceNode)) {
              if ($currentNode->matchAttributes($sourceNode)) {
                $clonedTree = Tree::cloneSubtree($leftNode);
                $clonedTree->getRoot()->flag('added', 'left');
                $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
              }
              elseif ($leftNode->matchAttributes($sourceNode)) {
                $clonedTree = Tree::cloneSubtree($currentNode);
                $clonedTree->getRoot()->flag('added', 'right');
                $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
              }
              else {
                $id = 'conflict-' . $leftNode->getId() . '-' . $currentNode->getId();
                $conflictNode = $this->createConflictNode($id, $sourceNode->getDomNode()->ownerDocument, $sourceNode, $leftNode, $currentNode);
                $this->resultTree->addNode($conflictNode, $resultParent);
                $nextResultParent = $conflictNode;
              }
            }
            else {
              $id = 'conflict-' . $leftNode->getId() . '-' . $currentNode->getId();
              $conflictNode = $this->createConflictNode($id, $this->sourceTree->getRoot()->getDomNode()->ownerDocument, $leftNode, $currentNode);
              $this->resultTree->addNode($conflictNode, $resultParent);
              $nextResultParent = $conflictNode;
            }
            // In any of these case, we also stop traversing (because we just
            // cloned entire branches) and flag the left node and its branch as
            // processed.
            $stopTraversing = TRUE;
            $leftNode->flag('processed', TRUE, TRUE);
          }
        }
      }
      // Case 2: The node was found on a different position.
      else {
        // There are also some cases here:
        // 2.1 If the source position, attributes and content are the same as
        // the current node, it means that we have a move operation performed by
        // left. In this case the entire right subtree gets added to the result
        // and marked as 'removed by left'. And the element from the same
        // position in the left tree gets added to the result and marked
        // 'added by left'.
        // 2.2. If the source position, attributes and content is the same as
        // the left node, it means we have a move operation performed by right.
        // In this case, we add the entire right subtree to the result and mark
        // it as 'added by right'. And the element from the same position in the
        // left tree gets added to the result and marked 'removed by right'.
        // 2.3 The source position, attributes and content do not match any of
        // the left and right tree. In this case we have a move and edit
        // operation in both trees. And in such a case we apply the same logic
        // as for 2.2
        $sourceNode = $this->sourceTree->search($currentNode->getId());
        $clonedTree = Tree::cloneSubtree($currentNode);
        $path = $this->rightTree->getPathFromRoot($currentNode);
        $nodeFromLeft = $this->leftTree->getNodeFromPath($path);
        if (!empty($nodeFromLeft)) {
          $cloneTreeFromLeft = Tree::cloneSubtree($nodeFromLeft);
        }
        if (!empty($sourceNode)) {
          // Case 2.1: the source and the current node match exactly. This means
          // that we have a move operation performed by the left.
          if ($currentNode->matchPosition($sourceNode) && $currentNode->matchAttributes($sourceNode) && $currentNode->matchContent($sourceNode)) {
            $clonedTree->getRoot()->flag('removed', 'left');
            if (!empty($nodeFromLeft)) {
              $cloneTreeFromLeft->getRoot()->flag('added', 'left');
            }
          }
          // Case 2.2: the source and the left node match exactly. This means
          // that we have a move operation performed by right.
          // This also covers Case 2.3: a move and edit operation in both trees.
          else {
            $clonedTree->getRoot()->flag('added', 'right');
            if (!empty($nodeFromLeft)) {
              $cloneTreeFromLeft->getRoot()->flag('removed', 'right');
            }
          }
        } else {
          $clonedTree->getRoot()->flag('added', 'right');
          if (!empty($nodeFromLeft)) {
            $cloneTreeFromLeft->getRoot()->flag('added', 'left');
          }
        }
        // Add the cloned trees to the result.
        $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
        if (!empty($nodeFromLeft)) {
          $this->resultTree->addNode($cloneTreeFromLeft->getRoot(), $resultParent);
        }
        // In any of these case, again, we just stop traversing the children of
        // the current node and also mark the left node and its branch as
        // processed.
        $stopTraversing = TRUE;
        if (!empty($nodeFromLeft)) {
          $nodeFromLeft->flag('processed', TRUE, TRUE);
        }
      }
    }
    else {
      // If the element was not found at all in the left then:
      // 1. If the node is not found in the source either, then it was just
      // added by right. In this case we just add the entire right subtree to
      // the result.
      // 2. If the node is found in the source, and has the same position,
      // attributes and content with the right node (current node), it means the
      // element was removed in the left tree. In this case we add the entire
      // right subtree to the result, and mark it as 'removed by left'.
      // 3. If the node is found in the source, but has a different position,
      // attributes or content, then we have the case that the left removed the
      // node (and probably replaced it with something else), and the right
      // changed it. In this case we add the entire right subtree to the result
      // and mark it is 'added by right'. Also, we add the element from the same
      // position in the left subtree (if any) and mark it as 'added by left'.
      // In any of these cases, we don't process any other children.

      // Case 1: The node is not found in the source tree.
      $sourceNode = $this->sourceTree->search($currentNode->getId());
      if (empty($sourceNode)) {
        $clonedTree = Tree::cloneSubtree($currentNode);
        $clonedTree->getRoot()->flag('added', 'right');
        $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
      }
      else {
        // Case 2: the node was removed from the left.
        if ($currentNode->matchPosition($sourceNode) && $currentNode->matchAttributes($sourceNode) && $currentNode->matchContent($sourceNode)) {
          $clonedTree = Tree::cloneSubtree($currentNode);
          $clonedTree->getRoot()->flag('removed', 'left');
          $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
        }
        else {
          // Case 3: node was removed and maybe replaced by left, and updated
          // by right.
          $clonedTree = Tree::cloneSubtree($currentNode);
          $clonedTree->getRoot()->flag('added', 'right');
          $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
        }

        // Check if there is another node on the same position in the left tree
        // and add it to the result as 'added by left'.
        $path = $this->rightTree->getPathFromRoot($currentNode);
        $nodeFromLeft = $this->leftTree->getNodeFromPath($path);
        if (!empty($nodeFromLeft)) {
          $cloneTreeFromLeft = Tree::cloneSubtree($nodeFromLeft);
          $cloneTreeFromLeft->getRoot()->flag('added', 'left');
          $this->resultTree->addNode($cloneTreeFromLeft->getRoot(), $resultParent);
          $nodeFromLeft->flag('processed', TRUE, TRUE);
        }
      }
      $stopTraversing = TRUE;
    }
    if (empty($stopTraversing) || $currentNode->getDomNode()->tagName == 'ck-button') {
      foreach ($currentNode->getChildren() as $child) {
        $this->traverseRight($child, $nextResultParent);
      }
    }
  }

  /**
   * Traverses the left tree and for any unprocessed items, we just add them to
   * the result.
   *
   * @param \Drupal\delivery\TreeNode $currentNode
   *   The current node being processed.
   */
  protected function traverseLeft(TreeNode $currentNode, TreeNode $resultParent = NULL) {
    // Check if this item is processed. If yes, just ignore it and continue
    // with the children.
    $processed = $currentNode->getFlag('processed');
    if (empty($processed)) {
      // We also have to check now if the node exists in the source tree. If
      // yes, then if it is identical to left, it means that right just removed
      // the node and basically we don't do anything. If it is not identical
      // (or if the item does not exist), then it was added by left.
      $sourceNode = $this->sourceTree->search($currentNode->getId());
      if (!empty($sourceNode)) {
        if ($currentNode->matchPosition($sourceNode) && $currentNode->matchAttributes($sourceNode) && $currentNode->matchContent($sourceNode)) {
          // This is the case when right removed the node. So basically, we
          // done't do anything here. An alternative would to also highlight
          // that the right removed the node.
          $currentNode->flag('processed', TRUE, TRUE);
        }
        else {
          $clonedTree = Tree::cloneSubtree($currentNode);
          $clonedTree->getRoot()->flag('added', 'left');
          $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
          $currentNode->flag('processed', TRUE, TRUE);
        }
      }
      else {
        $clonedTree = Tree::cloneSubtree($currentNode);
        $clonedTree->getRoot()->flag('added', 'left');
        $this->resultTree->addNode($clonedTree->getRoot(), $resultParent);
        $currentNode->flag('processed', TRUE, TRUE);
      }
      // In this case it does not make sense to process the children because we
      // added all of them to the result and also marked them as processed.
    }
    else {
      // Process the children of the current node too.
      if (!empty($currentNode->getChildren())) {
        $currentNodePath = $this->leftTree->getPathFromRoot($currentNode);
        $newResultParent = $this->resultTree->getNodeFromPath($currentNodePath);
        foreach ($currentNode->getChildren() as $child) {
          if (empty($resultParent)) {
            $this->traverseLeft($child, $this->resultTree->getRoot());
          }
          else {
            // If we could find the new parent in the result tree, use it.
            // Otherwise just fallback to the current parent (this should
            // actually not happen very often).
            if (!empty($newResultParent)) {
              $this->traverseLeft($child, $newResultParent);
            }
            else {
              $this->traverseLeft($child, $resultParent);
            }
          }
        }
      }
    }
  }

  /**
   * Removes the duplicated nodes from a tree.
   *
   * A node is duplicated if its previous sibling has the same identifier, the
   * same attributes and the same content.
   *
   * @param \Drupal\delivery\TreeNode $node
   *   The current node to be processed.
   */
  protected function removeDuplicatesFromTree(TreeNode $node) {
    $children  = $node->getChildren();
    if (!empty($children)) {
      $count = count($children);
      for ($index = 0; $index < $count - 1; $index++) {
        // If the current child has the same identifier, attributes and content
        // as the next one, we only keep the current node and remove any 'added'
        // or 'removed' flags.
        $currentChild = $children[$index];
        $nextChild = $children[$index + 1];
        if ($currentChild->getId() === $nextChild->getId() && $currentChild->matchAttributes($nextChild) && $currentChild->matchContent($nextChild)) {
          $node->removeChild($index + 1);
          $currentChild->unflag('added');
          $currentChild->unflag('removed');
        }
      }
      // Apply the same algorithm to the children of the current node.
      foreach ($node->getChildren() as $child) {
        $this->removeDuplicatesFromTree($child);
      }
    }
  }

  /**
   * Creates a new node to highlight a conflict bewteen the left and right nodes
   * compared to a source.
   *
   * @param $id
   *   The id of the new node to be created.
   * @param \Drupal\delivery\TreeNode|null $source
   *   The source node.
   * @param \Drupal\delivery\TreeNode|null $left
   *   The left node.
   * @param \Drupal\delivery\TreeNode|null $right
   *   The right node.
   *
   * @return \Drupal\delivery\TreeNode
   *   The conflict node.
   */
  protected function createConflictNode($id, \DOMDocument $document, TreeNode $source = NULL, TreeNode $left = NULL, TreeNode $right = NULL) {
    if (!empty($left) && $left->getDomNode()->hasAttribute('link-target')) {
      $domNode = $document->importNode($left->getDomNode(), TRUE);
      $node = new TreeNode($id, $domNode);
      $node->flag('link-conflict', TRUE);

      if (!empty($left)) {
        $node->flag('left', $left);
      }
      if (!empty($right)) {
        $node->flag('right', $right);
      }
      if (!empty($source)) {
        $node->flag('source', $source);
      }
      return $node;
    }
    // In case of media items, don't show a conflict but silently copy over
    // the left value until we have conflict resolution for media.
    // TODO: More solid detection of media items by using template data.
    if (!empty($left) && $left->getDomNode()->hasAttribute('data-media-uuid')) {
      $domNode = $document->importNode($left->getDomNode(), TRUE);
      $node = new TreeNode($id, $domNode);
      $node->flag('media-conflict', TRUE);

      if (!empty($left)) {
        $node->flag('left', $left);
      }
      if (!empty($right)) {
        $node->flag('right', $right);
      }
      return $node;
    }

    $domNode = $document->createElement('ck-conflict-text');
    $node = new TreeNode($id, $domNode);

    $node->flag('conflict', TRUE);
    if (!empty($source)) {
      $node->flag('source', $source);
    }
    if (!empty($left)) {
      $node->flag('left', $left);
    }
    if (!empty($right)) {
      $node->flag('right', $right);
    }
    return $node;
  }

  /**
   * Builds a node tree from an xml string.
   *
   * @param string $string
   *  The xml source string.
   * @return \Drupal\delivery\Tree | NULL
   *  The node tree.
   */
  protected function getTree($string) {
    libxml_use_internal_errors(TRUE);
    $document = new \DOMDocument();
    $document->preserveWhiteSpace = FALSE;
    $document->loadHTML($string);
    return Tree::buildTree($document);
  }

  /**
   * Builds the DOM document from a tree.
   *
   * @param \Drupal\delivery\Tree $node
   *  The current node being processed.
   * @param \DOMNode $domParentNode
   *  The current dom node to be used as parent.
   * @param  \DOMDocument $document
   *  The document that will be built.
   */
  protected function buildDocumentFromTree(TreeNode $node, \DOMNode $domParentNode, \DOMDocument $document) {
    // If the node is a leaf, we also just set its content.
    if ($node->isLeaf()) {
      $domNode = $document->importNode($node->getDomNode(), TRUE);
      $domNode = $this->getNodeWithFlags($domNode, $node);
      $domParentNode->appendChild($domNode);
    }
    else {
      $domNode = $document->importNode($node->getDomNode());
      $domNode = $this->getNodeWithFlags($domNode, $node);
      $domParentNode->appendChild($domNode);
      foreach ($node->getChildren() as $child) {
        $this->buildDocumentFromTree($child, $domNode, $document);
      }
    }
  }

  /**
   * Given a dom node and a tree node which may have specific flags set (like
   * added, removed or conflict), returns a new node with all the flags applied.
   *
   * @param \DOMNode $domNode
   *  The source DOM node.
   * @param \Drupal\delivery\TreeNode $node
   *  The tree node with all the flags information.
   * @return \DOMNode
   *  The result DOM node.
   */
  protected function getNodeWithFlags(\DOMNode $domNode, TreeNode $node) {
    // Check the added flag.
    if ($added = $node->getFlag('added')) {
      $newNode = $domNode->ownerDocument->importNode($domNode, TRUE);
      $newNode->setAttribute('added', TRUE);
      $newNode->setAttribute('by', $added);
      return $newNode;

      // This is the case when we want to have a wrapper.
      //$newNode = $domNode->ownerDocument->createElement('ck-added');
      //$newNode->setAttribute('by', $added);
      //$newNode->appendChild($domNode);
      //return $newNode;
    }
    if ($removed = $node->getFlag('removed')) {
      $newNode = $domNode->ownerDocument->importNode($domNode, TRUE);
      $newNode->setAttribute('removed', TRUE);
      $newNode->setAttribute('by', $removed);
      return $newNode;

      // This is the case when we want to have a wrapper.
      //$newNode = $domNode->ownerDocument->createElement('ck-deleted');
      //$newNode->setAttribute('by', $removed);
      //$newNode->appendChild($domNode);
      //return $newNode;
    }

    if ($link_conflict = $node->getFlag('link-conflict')) {
      if ($leftNodeElement = $node->getFlag('left')) {
        $attributes = $this->getDomNodeAttributes($leftNodeElement->getDomNode());
        $attributes['label'] = $this->labels['left'] ?? 'left';
        $domNode->setAttribute('left', json_encode($attributes));
      }
      if ($rightNodeElement = $node->getFlag('right')) {
        $attributes = $this->getDomNodeAttributes($rightNodeElement->getDomNode());
        $attributes['label'] = $this->labels['right'] ?? 'right';
        $domNode->setAttribute('right', json_encode($attributes));
      }
      if ($sourceNodeElement = $node->getFlag('source')) {
        $attributes = $this->getDomNodeAttributes($sourceNodeElement->getDomNode());
        $attributes['label'] = $this->labels['source'] ?? 'source';
        $domNode->setAttribute('source', json_encode($attributes));
      }
      return $domNode;
    }

    if ($media_conflict = $node->getFlag('media-conflict')) {
      $newNode = $domNode->ownerDocument->createElement('ck-conflict-media');

      // Add the left node.
      $leftNode = $domNode->ownerDocument->createElement('ck-conflict-media-option');
      $leftNode->setAttribute('from', $this->labels['left'] ?? 'left');
      if ($leftNodeElement = $node->getFlag('left')) {
        if ($slot = $leftNodeElement->getDomNode()->getAttribute('slot')) {
          $newNode->setAttribute('slot', $slot);
        }
        $leftNodeImported = $domNode->ownerDocument->importNode($leftNodeElement->getDomNode(), TRUE);
        $leftNode->appendChild($leftNodeImported);
      }
      $newNode->appendChild($leftNode);

      // Add the right node.
      $rightNode = $domNode->ownerDocument->createElement('ck-conflict-media-option');
      $rightNode->setAttribute('from', $this->labels['right'] ?? 'right');
      if ($rightNodeElement = $node->getFlag('right')) {
        $rightNodeImported = $domNode->ownerDocument->importNode($rightNodeElement->getDomNode(), TRUE);
        $rightNode->appendChild($rightNodeImported);
      }
      $newNode->appendChild($rightNode);
      return $newNode;
    }

    if ($conflict = $node->getFlag('conflict')) {
      $newNode = $domNode->ownerDocument->createElement('ck-conflict-text');

      // Set the current class on the conflict element, so that it is applicable
      // at this position for the editor.
      $newNode->setAttribute('class', $node->getFlag('left')->getDomNode()->getAttribute('class'));
      // Add the source node.
      $sourceNode = $domNode->ownerDocument->createElement('ck-conflict-option');
      $sourceNode->setAttribute('from', $this->labels['source'] ?? 'source');
      if ($sourceNodeElement = $node->getFlag('source')) {
        $sourceNodeImported = $domNode->ownerDocument->importNode($sourceNodeElement->getDomNode(), TRUE);
        $sourceNode->appendChild($sourceNodeImported);
      }
      $newNode->appendChild($sourceNode);

      // Add the left node.
      $leftNode = $domNode->ownerDocument->createElement('ck-conflict-option');
      $leftNode->setAttribute('from', $this->labels['left'] ?? 'left');
      if ($leftNodeElement = $node->getFlag('left')) {
        $leftNodeImported = $domNode->ownerDocument->importNode($leftNodeElement->getDomNode(), TRUE);
        $leftNode->appendChild($leftNodeImported);
      }
      $newNode->appendChild($leftNode);

      // Add the right node.
      $rightNode = $domNode->ownerDocument->createElement('ck-conflict-option');
      $rightNode->setAttribute('from', $this->labels['right'] ?? 'right');
      if ($rightNodeElement = $node->getFlag('right')) {
        $rightNodeImported = $domNode->ownerDocument->importNode($rightNodeElement->getDomNode(), TRUE);
        $rightNode->appendChild($rightNodeImported);
      }
      $newNode->appendChild($rightNode);
      return $newNode;
    }
    return $domNode;
  }

  /**
   * Get all attributes for domNode.
   */
  protected function getDomNodeAttributes($domNode) {
    $attributes = [];
    foreach ($domNode->attributes as $attr) {
      $attributes[$attr->nodeName] = $attr->nodeValue;
    }
    return $attributes;
  }
  /**
   * Set conflict option label.
   *
   * @param string $key
   *   Option key.
   * @param string $label
   *   Options label.
   */
  public function setLabel($key, $label) {
    $this->labels[$key] = $label;
  }

}
