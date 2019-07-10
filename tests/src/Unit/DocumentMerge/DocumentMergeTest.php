<?php

namespace Drupal\Tests\delivery\Unit\DocumentMerge;

use Drupal\delivery\DocumentMerge;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the DocumentMerge service.
 */
class DocumentMergeTest extends UnitTestCase {

  protected function assertMergeResult($source, $left, $right, $result) {
    $merge = new DocumentMerge();
    $mergeDoc = new \DOMDocument();
    $mergeDoc->loadXML($merge->merge($source, $left, $right));
    // To properly compare the result, we load the result into a DOM and get its
    // html (actually the html of the root div).
    libxml_use_internal_errors(TRUE);
    $document = new \DOMDocument();
    $document->loadXML($result);

    $a = $mergeDoc->saveXML($mergeDoc->documentElement);
    $b = $document->saveXML($document->documentElement);
    $this->assertXmlStringEqualsXmlString($b, $a);
  }

  /**
   * @covers       \Drupal\delivery\DocumentMerge::merge
   * @dataProvider mergeProvider
   */
  public function testMerge($source, $left, $right, $result) {
    $this->markTestSkipped();
    $this->assertMergeResult($source, $left, $right, $result);
  }

  /**
   * Data provider for testMerge().
   */
  public function mergeProvider() {
    $current_dir = dirname(__FILE__);
    $test_files = $current_dir . '/files';
    $data = [];
    $index = 1;
    while (is_dir($test_files . '/case' . $index)) {
      $folder = $test_files . '/case' . $index;
      $data[] = [
        'source' => file_get_contents($folder . '/source.xml'),
        'left' => file_get_contents($folder . '/left.xml'),
        'right' => file_get_contents($folder . '/right.xml'),
        'result' => file_get_contents($folder . '/result.xml'),
      ];
      $index++;
    }
    return $data;
  }

  public function testEmptyDocument() {
    $source = <<<XML
<div class="container"></div>
XML;

    $left = <<<XML
<div class="container"></div>
XML;

    $right = <<<XML
<div class="container"></div>
XML;

    $result = <<<XML
<div class="container"></div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftAddsElement() {
    $source = <<<XML
<div class="container"></div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $right = <<<XML
<div class="container"></div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1" added="1" by="left"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightAddsElement() {
    $source = <<<XML
<div class="container"></div>
XML;

    $left = <<<XML
<div class="container">
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1" added="1" by="right"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testBothAddElements() {
    $source = <<<XML
<div class="container"></div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="2"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div added="1" by="right" class="a" id="2"></div>
    <div added="1" by="left" class="a" id="1"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testBothAddEqualElement() {
    $source = <<<XML
<div class="container"></div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftRemovesLastElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2" removed="1" by="left"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightRemovesLastElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftRemovesSecondElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2" removed="1" by="left"></div>
    <div class="a" id="3"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightRemovesSecondElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div added="1" by="right" class="a" id="3"></div>
    <div removed="1" by="right" class="a" id="2"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftMovesElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="3"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div removed="1" by="left" class="a" id="2"></div>
    <div class="a" id="3"></div>
    <div added="1" by="left" class="a" id="2"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightMovesElement() {
    $source = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $left = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="2"></div>
    <div class="a" id="3"></div>
</div>
XML;

    $right = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div class="a" id="3"></div>
    <div class="a" id="2"></div>
</div>
XML;

    $result = <<<XML
<div class="container">
    <div class="a" id="1"></div>
    <div added="1" by="right" class="a" id="3"></div>
    <div class="a" id="2"></div>
    <div removed="1" by="right" class="a" id="3"></div>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testEqualText() {
    $source = <<<XML
<div class="text">A</div>
XML;

    $left = <<<XML
<div class="container">A</div>
XML;

    $right = <<<XML
<div class="container">A</div>
XML;

    $result = <<<XML
<div class="container">A</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftChangesMedia() {
    $source = <<<XML
  <div class="media" data-media-uuid="1"></div>
XML;

    $left = <<<XML
  <div class="media" data-media-uuid="2"></div>
XML;

    $right = <<<XML
  <div class="media" data-media-uuid="1"></div>
XML;

    $result = <<<XML
  <ck-conflict-media>
    <ck-conflict-media-option from="left">
      <div class="media" data-media-uuid="2"/>
    </ck-conflict-media-option>
    <ck-conflict-media-option from="right">
      <div class="media" data-media-uuid="1"/>
    </ck-conflict-media-option>
  </ck-conflict-media>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightChangesMedia() {
    $source = <<<XML
  <div class="media" data-media-uuid="1"></div>
XML;

    $left = <<<XML
  <div class="media" data-media-uuid="1"></div>
XML;

    $right = <<<XML
  <div class="media" data-media-uuid="2"></div>
XML;

    $result = <<<XML
  <ck-conflict-media>
    <ck-conflict-media-option from="left">
      <div class="media" data-media-uuid="1"/>
    </ck-conflict-media-option>
    <ck-conflict-media-option from="right">
      <div class="media" data-media-uuid="2"/>
    </ck-conflict-media-option>
  </ck-conflict-media>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightChangesMediaWithSlot() {
    $source = <<<XML
  <div class="media" slot="media" data-media-uuid="1"></div>
XML;

    $left = <<<XML
  <div class="media" slot="media" data-media-uuid="1"></div>
XML;

    $right = <<<XML
  <div class="media" slot="media" data-media-uuid="2"></div>
XML;

      $result = <<<XML
  <ck-conflict-media slot="media">
    <ck-conflict-media-option from="left">
      <div class="media" data-media-uuid="1"/>
    </ck-conflict-media-option>
    <ck-conflict-media-option from="right">
      <div class="media" data-media-uuid="2"/>
    </ck-conflict-media-option>
  </ck-conflict-media>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testChangesToEqualMedia() {
    $source = <<<XML
  <div class="media" data-media-uuid="1"></div>
XML;

    $left = <<<XML
  <div class="media" data-media-uuid="2"></div>
XML;

    $right = <<<XML
  <div class="media" data-media-uuid="2"></div>
XML;

    $result = <<<XML
  <div class="media" data-media-uuid="2"></div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftChangesText() {
    $source = <<<XML
<div class="text">A</div>
XML;

    $left = <<<XML
<div class="text">B</div>
XML;

    $right = <<<XML
<div class="text">A</div>
XML;

    $result = <<<XML
<ck-conflict-text class="text">
    <ck-conflict-option from="source">
        <div class="text">A</div>
    </ck-conflict-option>
    <ck-conflict-option from="left">
        <div class="text">B</div>
    </ck-conflict-option>
    <ck-conflict-option from="right">
        <div class="text">A</div>
    </ck-conflict-option>
</ck-conflict-text>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightChangesText() {
    $source = <<<XML
<div class="text">A</div>
XML;

    $left = <<<XML
<div class="text">A</div>
XML;

    $right = <<<XML
<div class="text">B</div>
XML;

    $result = <<<XML
<ck-conflict-text class="text">
    <ck-conflict-option from="source">
        <div class="text">A</div>
    </ck-conflict-option>
    <ck-conflict-option from="left">
        <div class="text">A</div>
    </ck-conflict-option>
    <ck-conflict-option from="right">
        <div class="text">B</div>
    </ck-conflict-option>
</ck-conflict-text>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testChangeToEqualTexts() {
    $source = <<<XML
<div class="text">A</div>
XML;

    $left = <<<XML
<div class="text">B</div>
XML;

    $right = <<<XML
<div class="text">B</div>
XML;

    $result = <<<XML
<div class="text">B</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testBothChangeTexts() {
    $source = <<<XML
<div class="text">A</div>
XML;

    $left = <<<XML
<div class="text">B</div>
XML;

    $right = <<<XML
<div class="text">C</div>
XML;

    $result = <<<XML
<ck-conflict-text class="text">
    <ck-conflict-option from="source">
        <div class="text">A</div>
    </ck-conflict-option>
    <ck-conflict-option from="left">
        <div class="text">B</div>
    </ck-conflict-option>
    <ck-conflict-option from="right">
        <div class="text">C</div>
    </ck-conflict-option>
</ck-conflict-text>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testMultipleChangesInTextAndOrder() {
    $source = <<<XML
<div class="container">
  <div class="a" id="a">
    <div class="a" id="d">
      <div class="a" id="e">E</div>
      <div class="a" id="f">F</div>
    </div>
    <div class="a" id="g">G</div>
  </div>
  <div class="a" id="b">
    <div class="a" id="h">
      <div class="a" id="i">I</div>
    </div>
    <div class="a" id="j">J</div>
  </div>
  <div class="a" id="c">C</div>
  <div class="a" id="x">X</div>
</div>
XML;

    $left = <<<XML
<div class="container">
  <div class="a" id="a">
    <div class="a" id="d">
      <div class="a" id="f">F</div>
    </div>
    <div class="a" id="g">GLeft</div>
  </div>
  <div class="a" id="c">C</div>
  <div class="a" id="b">
    <div class="a" id="e">ELeft</div>
    <div class="a" id="j">JLeft</div>
    <div class="a" id="h">
      <div class="a" id="i">IUpdated</div>
    </div>
  </div>
  <div class="a" id="x">XLeft</div>
</div>
XML;

    $right = <<<XML
<div class="container">
  <div class="a" id="a">
    <div class="a" id="d">
      <div class="a" id="e">E</div>
      <div class="a" id="f">FRight</div>
    </div>
    <div class="a" id="g">G</div>
  </div>
  <div class="a" id="b">
    <div class="a" id="h">
      <div class="a" id="i">IUpdated</div>
    </div>
    <div class="a" id="j">JRight</div>
  </div>
  <div class="a" id="c">C</div>
  <div class="a" id="x">XRight</div>
</div>
XML;

    $result = <<<XML
<div class="container">
  <div class="a" id="a">
    <div class="a" id="d">
      <div class="a" id="e" removed="1" by="left">E</div>
      <div class="a" id="f" added="1" by="left">F</div>
      <div class="a" id="f" added="1" by="right">FRight</div>
    </div>
    <ck-conflict-text class="a">
      <ck-conflict-option from="source">
          <div class="a" id="g">G</div>
      </ck-conflict-option>
      <ck-conflict-option from="left">
          <div class="a" id="g">GLeft</div>
      </ck-conflict-option>
      <ck-conflict-option from="right">
        <div class="a" id="g">G</div>
      </ck-conflict-option>
    </ck-conflict-text>
  </div>
  <div class="a" id="b" added="1" by="right">
    <div class="a" id="h">
      <div class="a" id="i">IUpdated</div>
    </div>
    <div class="a" id="j">JRight</div>
  </div>
  <div class="a" id="c">C</div>
  <div class="a" id="b" added="1" by="left">
    <div class="a" id="e">ELeft</div>
    <div class="a" id="j">JLeft</div>
    <div class="a" id="h">
      <div class="a" id="i">IUpdated</div>
    </div>
  </div>
  <ck-conflict-text class="a">
    <ck-conflict-option from="source">
      <div class="a" id="x">X</div>
    </ck-conflict-option>
    <ck-conflict-option from="left">
      <div class="a" id="x">XLeft</div>
    </ck-conflict-option>
    <ck-conflict-option from="right">
      <div class="a" id="x">XRight</div>
    </ck-conflict-option>
</ck-conflict-text>
</div>
XML;
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLeftChangesAttribute() {
    $source = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $left = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $right = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $result = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRightChangesAttribute() {
    $source = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $left = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $right = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $result = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testBothChangeAttribute() {
    $source = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $left = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $right = <<<XML
<div class="container">
  <div class="test" data-color="blue">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $result = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testChangeAttributeAndText() {
    $source = <<<XML
<div class="container">
  <div class="test" data-color="green">
    <p class="text">Test</p>
  </div>
</div>
XML;

    $left = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <p class="text">A</p>
  </div>
</div>
XML;

    $right = <<<XML
<div class="container">
  <div class="test" data-color="blue">
    <p class="text">B</p>
  </div>
</div>
XML;

    $result = <<<XML
<div class="container">
  <div class="test" data-color="red">
    <ck-conflict-text class="text">
      <ck-conflict-option from="source">
        <p class="text">Test</p>
      </ck-conflict-option>
      <ck-conflict-option from="left">
        <p class="text">A</p>
      </ck-conflict-option>
      <ck-conflict-option from="right">
        <p class="text">B</p>
      </ck-conflict-option>
    </ck-conflict-text>
  </div>
</div>
XML;

    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRealWorldExample() {
    $folder = dirname(__FILE__);
    $source = file_get_contents($folder . '/files/cards/source.html');
    $left = file_get_contents($folder . '/files/cards/left.html');
    $right = file_get_contents($folder . '/files/cards/right.html');
    $result = file_get_contents($folder . '/files/cards/result.html');
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testLinksExample() {
    $folder = dirname(__FILE__);
    $source = file_get_contents($folder . '/files/links/source.html');
    $left = file_get_contents($folder . '/files/links/left.html');
    $right = file_get_contents($folder . '/files/links/right.html');
    $result = file_get_contents($folder . '/files/links/result.html');
    $this->assertMergeResult($source, $left, $right, $result);
  }

  public function testRichTextExample() {
    $folder = dirname(__FILE__);
    $source = file_get_contents($folder . '/files/rich_text/source.xml');
    $left = file_get_contents($folder . '/files/rich_text/left.xml');
    $right = file_get_contents($folder . '/files/rich_text/right.xml');
    $result = file_get_contents($folder . '/files/rich_text/result.xml');
    $this->assertMergeResult($source, $left, $right, $result);
  }
}
