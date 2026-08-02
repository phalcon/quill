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
use Phalcon\Quill\Exceptions\MalformedConfiguration;
use Phalcon\Quill\Exceptions\MissingConfiguration;
use Phalcon\Quill\Exceptions\MissingConfigurationKey;
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
                'namespace'  => 'Phalcon',
            ],
            '/project'
        );

        $this->assertSame('/cphalcon/phalcon', $config->sourceRoot());
        $this->assertSame('/out', $config->outputDir());
    }

    public function testAConfiguredAssetsDirectoryIsResolvedAgainstTheRoot(): void
    {
        $config = Config::fromArray($this->values(['assets' => 'docs/assets/css']), '/project');

        $this->assertSame('/project/docs/assets/css', $config->assetsDir());
    }

    /**
     * The namespace is a name, not a path, so the separators a project might
     * write around it are stripped rather than carried into every page key and
     * every resolution candidate.
     */
    public function testANamespaceIsTrimmedOfItsSeparators(): void
    {
        $config = Config::fromArray($this->values(['namespace' => '\\Phalcon\\']), '/project');

        $this->assertSame('Phalcon', $config->rootNamespace());
        $this->assertSame('phalcon_', $config->pagePrefix());
    }

    /**
     * Absent and present-but-empty mean the same thing - assets go where the
     * documents go - and neither may be mistaken for a configured path.
     */
    public function testAnEmptyOrAbsentAssetsKeyFallsBackToTheOutputDirectory(): void
    {
        $absent = Config::fromArray($this->values(), '/project');
        $empty  = Config::fromArray($this->values(['assets' => '']), '/project');

        $this->assertSame($absent->outputDir(), $absent->assetsDir());
        $this->assertSame($empty->outputDir(), $empty->assetsDir());
    }

    /**
     * A root that already ends in a separator must not produce a doubled one.
     */
    public function testARootWithATrailingSeparatorIsNotDoubled(): void
    {
        $config = Config::fromArray(
            [
                'language'   => 'zephir',
                'source'     => 'phalcon',
                'output'     => 'nikos/api',
                'repository' => 'phalcon/cphalcon',
                'branch'     => '5.0.x',
                'prefix'     => 'phalcon',
                'extension'  => 'zep',
                'namespace'  => 'Phalcon',
            ],
            '/project/'
        );

        $this->assertSame('/project/phalcon', $config->sourceRoot());
        $this->assertSame('/project/nikos/api', $config->outputDir());
    }

    public function testEveryConfiguredValueIsReadableBack(): void
    {
        $config = $this->config();

        $this->assertSame('zephir', $config->language());
        $this->assertSame('5.0.x', $config->branch());
        $this->assertSame('phalcon', $config->sourcePrefix());
        $this->assertSame('phalcon/cphalcon', $config->repository());
        $this->assertSame('zep', $config->extension());
    }

    public function testFromFileRejectsAFileThatDoesNotReturnAnArray(): void
    {
        $this->expectException(MalformedConfiguration::class);
        $this->expectExceptionMessage('must return an array');

        Config::fromFile(dirname(__DIR__) . '/Fixtures/config/not-an-array.php');
    }

    public function testFromFileRejectsAMissingFile(): void
    {
        $this->expectException(MissingConfiguration::class);
        $this->expectExceptionMessage('no such file');

        Config::fromFile('/nowhere/quill.php');
    }

    public function testFromFileRejectsAMissingKey(): void
    {
        $this->expectException(MissingConfigurationKey::class);
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

    public function testSourceUrlNormalizesBackslashes(): void
    {
        $this->assertSame(
            'https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Acl/Adapter/Memory.zep',
            $this->config()->sourceUrl('Acl\\Adapter\\Memory.zep')
        );
    }

    public function testTrailingSeparatorsAreNormalized(): void
    {
        $config = new Config(
            'zephir',
            '/src/',
            '/out/',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            'zep',
            'Phalcon'
        );

        $this->assertSame('/src', $config->sourceRoot());
        $this->assertSame('/out', $config->outputDir());
    }

    /**
     * The copy redirects output and changes nothing else, so a one-off run
     * cannot disturb the configured destination.
     */
    public function testWithOutputDirChangesOnlyTheDestination(): void
    {
        $config = $this->config();
        $copy   = $config->withOutputDir('/elsewhere');

        $this->assertSame('/elsewhere', $copy->outputDir());
        $this->assertNotSame($config, $copy);

        $this->assertSame($config->language(), $copy->language());
        $this->assertSame($config->sourceRoot(), $copy->sourceRoot());
        $this->assertSame($config->repository(), $copy->repository());
        $this->assertSame($config->branch(), $copy->branch());
        $this->assertSame($config->sourcePrefix(), $copy->sourcePrefix());
        $this->assertSame($config->extension(), $copy->extension());

        // The original still writes where it was told to.
        $this->assertSame('/unused', $config->outputDir());
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
            'zep',
            'Phalcon'
        );
    }

    /**
     * A complete key set, with any of it overridden per test.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function values(array $overrides = []): array
    {
        // The overrides go first: `+` keeps the left operand for a duplicate
        // key, so putting the defaults there would silently ignore them.
        return $overrides + [
            'language'   => 'zephir',
            'source'     => 'phalcon',
            'output'     => 'nikos/api',
            'repository' => 'phalcon/cphalcon',
            'branch'     => '5.0.x',
            'prefix'     => 'phalcon',
            'extension'  => 'zep',
            'namespace'  => 'Phalcon',
        ];
    }
}
