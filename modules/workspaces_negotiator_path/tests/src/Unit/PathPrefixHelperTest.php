<?php

namespace Drupal\Tests\workspaces_negotiator_path\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper;

class PathPrefixHelperTest extends UnitTestCase {

  /**
   * @covers \Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper::findBestPathPrefixFit()
   *
   * @dataProvider findBestPathPrefixFitProvider
   */
  public function testFindBestPathPrefixFit($path, array $prefixes, $expected) {
    $this->assertEquals($expected, PathPrefixHelper::findBestPathPrefixFit($path, $prefixes));
  }

  public function findBestPathPrefixFitProvider() {
    return [
      [
        '/',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/dev'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod'
          ],
        ],
        NULL,
      ],
      [
        '/dev',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/dev'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod'
          ],
        ],
        [
          'id' => 2,
          'path_prefix' => '/dev'
        ],
      ],
      [
        '/prod/node/1',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/dev'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod'
          ],
        ],
        [
          'id' => 3,
          'path_prefix' => '/prod'
        ],
      ],
      [
        '/prod/node/1',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/prod/node'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod'
          ],
        ],
        [
          'id' => 2,
          'path_prefix' => '/prod/node'
        ],
      ],
      [
        '/prod/node/1',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/prod'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod/node'
          ],
        ],
        [
          'id' => 3,
          'path_prefix' => '/prod/node'
        ],
      ],
      [
        '/prod/nodetest/1',
        [
          [
            'id' => 1,
            'path_prefix' => '/stage'
          ],
          [
            'id' => 2,
            'path_prefix' => '/prod'
          ],
          [
            'id' => 3,
            'path_prefix' => '/prod/node'
          ],
        ],
        [
          'id' => 2,
          'path_prefix' => '/prod'
        ],
      ]
    ];
  }

  /**
   * @covers \Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper::pathPrefixMatch()
   * @dataProvider pathPrefixMatchProvider
   */
  public function testPathPrefixMatch($path, $prefix, $expected) {
    $this->assertEquals($expected, PathPrefixHelper::pathPrefixMatch($path, $prefix));
  }

  public function pathPrefixMatchProvider() {
    return [
      [
        '/',
        '/',
        TRUE,
      ],
      [
        '/',
        '',
        TRUE,
      ],
      // The '/' prefix will match any kind of path, that why this is TRUE.
      [
        '',
        '/',
        TRUE,
      ],
      [
        '/',
        '/stage',
        FALSE,
      ],
      [
        '/node/1',
        '/',
        TRUE,
      ],
      [
        '/node/1',
        '/stage',
        FALSE,
      ],
      [
        '/stage/node/1',
        '/stage',
        TRUE,
      ],
      [
        '/stage/more/node/1',
        '/stage/more',
        TRUE,
      ],
      [
        '/stage/more/node/1',
        '/stage/more/even_more',
        FALSE,
      ],
      [
        '/stage/more/node/1',
        '/stage/mo',
        FALSE,
      ],
    ];
  }

}
