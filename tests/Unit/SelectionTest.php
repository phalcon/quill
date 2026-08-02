<?php

/**
 * This file is part of the Phalcon Quill.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Quill\Tests\Unit;

use Phalcon\Quill\Config;
use Phalcon\Quill\Selection;
use PHPUnit\Framework\TestCase;

final class SelectionTest extends TestCase
{
    public function testAnEmptyNamespaceMatchesEverything(): void
    {
        $selection = Selection::none();

        $this->assertTrue($selection->matchesNamespace('Phalcon\\Config\\Config'));
        $this->assertTrue($selection->matchesNamespace('Anything'));
        $this->assertFalse($selection->narrows());
    }

    /**
     * A prefix test that ignored the separator would pull in every sibling
     * whose name merely starts the same way.
     */
    public function testMatchingStopsAtTheNamespaceBoundary(): void
    {
        $selection = Selection::of('', 'Phalcon\\Config', $this->config());

        $this->assertTrue($selection->matchesNamespace('Phalcon\\Config'));
        $this->assertTrue($selection->matchesNamespace('Phalcon\\Config\\Adapter\\Json'));
        $this->assertFalse($selection->matchesNamespace('Phalcon\\Configuration'));
        $this->assertFalse($selection->matchesNamespace('Phalcon\\Auth\\Adapter\\Config'));
    }

    public function testNarrowsReportsEitherDimension(): void
    {
        $config = $this->config();

        $this->assertFalse(Selection::of('', '', $config)->narrows());
        $this->assertTrue(Selection::of('adapter', '', $config)->narrows());
        $this->assertTrue(Selection::of('', 'Config', $config)->narrows());
    }

    /**
     * Three spellings of the same namespace, because a caller should not have
     * to know whether the root is already there.
     */
    public function testTheRootNamespaceIsImplied(): void
    {
        $config = $this->config();

        $this->assertSame('Phalcon\\Config', Selection::of('', 'Config', $config)->namespace);
        $this->assertSame('Phalcon\\Config', Selection::of('', 'Phalcon\\Config', $config)->namespace);
        $this->assertSame('Phalcon\\Config', Selection::of('', '\\Phalcon\\Config\\', $config)->namespace);
        $this->assertSame('Phalcon', Selection::of('', 'Phalcon', $config)->namespace);
    }

    public function testTheFilterIsCarriedUntouched(): void
    {
        $selection = Selection::of('Adapter', 'Config', $this->config());

        $this->assertSame('Adapter', $selection->filter);
    }

    private function config(): Config
    {
        return new Config(
            'zephir',
            '/unused',
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon'
        );
    }
}
