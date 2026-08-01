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
use Phalcon\Quill\Exceptions\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ConfigTest extends TestCase
{
    public function testAbsolutePathsInConfigPassThroughUnchanged(): void
    {
        $config = Config::fromArray(
            [
                'language'   => 'zephir',
                'source'     => '/cphalcon/phalcon',
                'output'     => '/out',
                'repository' => 'phalcon/cphalcon',
                'branch'     => '5.0.x',
                'prefix'     => 'phalcon',
                'extension'  => 'zep',
            ],
            '/project'
        );

        $this->assertSame('/cphalcon/phalcon', $config->sourceRoot());
        $this->assertSame('/out', $config->outputDir());
    }

    public function testFromFileRejectsAFileThatDoesNotReturnAnArray(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('must return an array');

        Config::fromFile(dirname(__DIR__) . '/Fixtures/config/not-an-array.php');
    }

    public function testFromFileRejectsAMissingFile(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('no such file');

        Config::fromFile('/nowhere/quill.php');
    }

    public function testFromFileRejectsAMissingKey(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage("'branch'");

        Config::fromArray(
            [
                'language'   => 'zephir',
                'source'     => 'phalcon',
                'output'     => 'nikos/api',
                'repository' => 'phalcon/cphalcon',
                'prefix'     => 'phalcon',
                'extension'  => 'zep',
            ],
            '/project'
        );
    }

    public function testFromFileResolvesRelativePathsAgainstTheConfigDirectory(): void
    {
        $directory = dirname(__DIR__) . '/Fixtures/config';
        $config    = Config::fromFile($directory . '/valid.php');

        $this->assertSame('zephir', $config->language());
        $this->assertSame($directory . '/phalcon', $config->sourceRoot());
        $this->assertSame($directory . '/nikos/api', $config->outputDir());
    }

    public function testSourceUrlMatchesTheLegacyHardcodedForm(): void
    {
        $this->assertSame(
            'https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Acl/Adapter/Memory.zep',
            $this->config()->sourceUrl('Acl/Adapter/Memory.zep')
        );
    }

    public function testSourceUrlNormalisesBackslashes(): void
    {
        $this->assertSame(
            'https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Acl/Adapter/Memory.zep',
            $this->config()->sourceUrl('Acl\\Adapter\\Memory.zep')
        );
    }

    public function testTrailingSeparatorsAreNormalised(): void
    {
        $config = new Config(
            'zephir',
            '/src/',
            '/out/',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep'
        );

        $this->assertSame('/src', $config->sourceRoot());
        $this->assertSame('/out', $config->outputDir());
    }

    private function config(): Config
    {
        return new Config(
            'zephir',
            '/cphalcon/phalcon',
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep'
        );
    }
}
