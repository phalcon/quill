<?php

/**
 * This file is part of the Phalcon Scribe.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Scribe\Tests\Unit\Reader;

use Phalcon\Scribe\Exceptions\UnknownLanguage;
use Phalcon\Scribe\Reader\ReaderFactory;
use Phalcon\Scribe\Reader\ZephirReader;
use PHPUnit\Framework\TestCase;

final class ReaderFactoryTest extends TestCase
{
    public function testCreatesTheZephirReader(): void
    {
        $this->assertInstanceOf(ZephirReader::class, (new ReaderFactory())->create('zephir'));
    }

    public function testPhpIsNotAvailableYet(): void
    {
        $this->expectException(UnknownLanguage::class);
        $this->expectExceptionMessage("Unknown language 'php'; known languages are: zephir");

        (new ReaderFactory())->create('php');
    }
}
