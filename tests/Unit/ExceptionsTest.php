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

use Phalcon\Quill\Exceptions\Exception;
use Phalcon\Quill\Exceptions\IncompatibleDocument;
use Phalcon\Quill\Exceptions\MalformedConfiguration;
use Phalcon\Quill\Exceptions\MalformedDocument;
use Phalcon\Quill\Exceptions\MissingConfiguration;
use Phalcon\Quill\Exceptions\MissingConfigurationKey;
use Phalcon\Quill\Exceptions\MissingDependency;
use Phalcon\Quill\Exceptions\MissingDocument;
use Phalcon\Quill\Exceptions\UnknownFormat;
use Phalcon\Quill\Exceptions\UnknownLanguage;
use Phalcon\Quill\Exceptions\WriteFailed;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Every message here is the only thing a user sees when something refuses to
 * run, so each one has to say what to do next rather than only what happened.
 * Each exception owns its wording; no caller passes a message in.
 */
final class ExceptionsTest extends TestCase
{
    public function testAMalformedConfigurationSaysWhatTheFileOwes(): void
    {
        $exception = new MalformedConfiguration('/project/quill.php');

        $this->assertStringContainsString("'/project/quill.php'", $exception->getMessage());
        $this->assertStringContainsString('must return an array', $exception->getMessage());
    }

    public function testAMalformedDocumentNamesTheFile(): void
    {
        $exception = new MalformedDocument('/project/model.json');

        $this->assertStringContainsString("'/project/model.json'", $exception->getMessage());
        $this->assertStringContainsString('is not a model document', $exception->getMessage());
    }

    public function testAMissingConfigurationKeyNamesTheKey(): void
    {
        $exception = new MissingConfigurationKey('branch');

        $this->assertStringContainsString("'branch'", $exception->getMessage());
        $this->assertStringContainsString('non-empty string', $exception->getMessage());
    }

    public function testAMissingConfigurationNamesTheFile(): void
    {
        $exception = new MissingConfiguration('/project/quill.php');

        $this->assertStringContainsString("'/project/quill.php'", $exception->getMessage());
        $this->assertStringContainsString('no such file', $exception->getMessage());
    }

    public function testAMissingDependencyNamesThePackageAndHowToInstallIt(): void
    {
        $exception = new MissingDependency('phalcon/zephir', 'The zephir reader');

        $this->assertSame(
            "The zephir reader requires 'phalcon/zephir'; install it with: "
            . 'composer require --dev phalcon/zephir',
            $exception->getMessage()
        );
    }

    public function testAMissingDocumentNamesTheFile(): void
    {
        $exception = new MissingDocument('/project/model.json');

        $this->assertStringContainsString("'/project/model.json'", $exception->getMessage());
        $this->assertStringContainsString('no such model document', $exception->getMessage());
    }

    public function testAnIncompatibleDocumentReportsBothVersions(): void
    {
        $exception = new IncompatibleDocument('/project/model.json', 6, 7);

        $this->assertStringContainsString('is version 6', $exception->getMessage());
        $this->assertStringContainsString('version 7', $exception->getMessage());
    }

    public function testAnIncompatibleDocumentSaysWhenThereIsNoVersionAtAll(): void
    {
        $exception = new IncompatibleDocument('/project/model.json', null, 7);

        $this->assertStringContainsString('declares no version', $exception->getMessage());
    }

    public function testAnUnknownFormatListsTheOnesThatWork(): void
    {
        $exception = new UnknownFormat('pdf', ['json', 'markdown']);

        $this->assertStringContainsString("'pdf'", $exception->getMessage());
        $this->assertStringContainsString('json, markdown', $exception->getMessage());
    }

    public function testAnUnknownLanguageListsTheOnesThatWork(): void
    {
        $exception = new UnknownLanguage('cobol', ['php', 'zephir']);

        $this->assertStringContainsString("Unknown language 'cobol'", $exception->getMessage());
        $this->assertStringContainsString('php, zephir', $exception->getMessage());
    }

    public function testAWriteFailureBlamesThePermissionsRatherThanTheUser(): void
    {
        $exception = new WriteFailed('/root/out.md');

        $this->assertStringContainsString("'/root/out.md'", $exception->getMessage());
        $this->assertStringContainsString('writable', $exception->getMessage());
    }

    /**
     * One catch in bin/quill has to stop everything quill throws, and every
     * one of these is a runtime condition - the input or the environment is
     * wrong, never quill's own wiring.
     */
    public function testEveryExceptionIsCatchableAsOneAndIsARuntimeFailure(): void
    {
        $all = [
            new IncompatibleDocument('/a', 6, 7),
            new MalformedConfiguration('/a'),
            new MalformedDocument('/a'),
            new MissingConfiguration('/a'),
            new MissingConfigurationKey('branch'),
            new MissingDependency('a/b', 'Something'),
            new MissingDocument('/a'),
            new UnknownFormat('pdf', ['json']),
            new UnknownLanguage('cobol', ['php']),
            new WriteFailed('/a'),
        ];

        foreach ($all as $exception) {
            $this->assertInstanceOf(Exception::class, $exception);
            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
