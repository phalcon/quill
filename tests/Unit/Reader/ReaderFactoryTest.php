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

namespace Phalcon\Quill\Tests\Unit\Reader;

use Phalcon\Quill\Exceptions\UnknownLanguage;
use Phalcon\Quill\Reader\PhpReader;
use Phalcon\Quill\Reader\ReaderFactory;
use Phalcon\Quill\Reader\ZephirReader;
use PHPUnit\Framework\TestCase;

final class ReaderFactoryTest extends TestCase
{
    public function testCreatesThePhpReader(): void
    {
        $this->assertInstanceOf(PhpReader::class, (new ReaderFactory())->create('php'));
    }

    public function testCreatesTheZephirReader(): void
    {
        $this->assertInstanceOf(ZephirReader::class, (new ReaderFactory())->create('zephir'));
    }

    public function testUnknownLanguageThrows(): void
    {
        $this->expectException(UnknownLanguage::class);
        $this->expectExceptionMessage("Unknown language 'ruby'; known languages are: php, zephir");

        (new ReaderFactory())->create('ruby');
    }
}
