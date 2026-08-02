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
use Phalcon\Quill\Exceptions\MissingAsset;
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

        $this->assertSame(
            "quill configuration: '/project/quill.php' must return an array",
            $exception->getMessage()
        );
    }

    public function testAMalformedDocumentNamesTheFile(): void
    {
        $exception = new MalformedDocument('/project/model.json');

        $this->assertSame(
            "'/project/model.json' is not a model document",
            $exception->getMessage()
        );
    }

    /**
     * A packaged asset that cannot be found means a broken installation, not a
     * misconfigured project, and the message has to say which.
     */
    public function testAMissingAssetNamesThePathAndBlamesTheInstall(): void
    {
        $exception = new MissingAsset('/vendor/phalcon/quill/resources/api.css');

        $this->assertSame(
            "The asset '/vendor/phalcon/quill/resources/api.css' is missing from"
            . ' this installation. It ships with quill, so a copy that cannot'
            . ' find it is incomplete rather than misconfigured.',
            $exception->getMessage()
        );
    }

    public function testAMissingConfigurationKeyNamesTheKey(): void
    {
        $exception = new MissingConfigurationKey('branch');

        $this->assertSame(
            "quill configuration key 'branch' is required and must be a non-empty string",
            $exception->getMessage()
        );
    }

    public function testAMissingConfigurationNamesTheFile(): void
    {
        $exception = new MissingConfiguration('/project/quill.php');

        $this->assertSame(
            "quill configuration: no such file '/project/quill.php'",
            $exception->getMessage()
        );
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

        $this->assertSame(
            "no such model document '/project/model.json'",
            $exception->getMessage()
        );
    }

    public function testAnIncompatibleDocumentReportsBothVersions(): void
    {
        $exception = new IncompatibleDocument('/project/model.json', 6, 7);

        $this->assertSame(
            "'/project/model.json' is version 6, but this copy of quill reads version 7."
            . ' Comparing across versions reports shape changes as differences between'
            . ' implementations, so regenerate the document with the quill that reads it.',
            $exception->getMessage()
        );
    }

    public function testAnIncompatibleDocumentSaysWhenThereIsNoVersionAtAll(): void
    {
        $exception = new IncompatibleDocument('/project/model.json', null, 7);

        $this->assertSame(
            "'/project/model.json' declares no version, but this copy of quill reads version 7."
            . ' Comparing across versions reports shape changes as differences between'
            . ' implementations, so regenerate the document with the quill that reads it.',
            $exception->getMessage()
        );
    }

    public function testAnUnknownFormatListsTheOnesThatWork(): void
    {
        $exception = new UnknownFormat('pdf', ['json', 'markdown']);

        $this->assertSame(
            "Unknown format 'pdf'; known formats are: json, markdown",
            $exception->getMessage()
        );
    }

    public function testAnUnknownLanguageListsTheOnesThatWork(): void
    {
        $exception = new UnknownLanguage('cobol', ['php', 'zephir']);

        $this->assertSame(
            "Unknown language 'cobol'; known languages are: php, zephir",
            $exception->getMessage()
        );
    }

    public function testAWriteFailureBlamesThePermissionsRatherThanTheUser(): void
    {
        $exception = new WriteFailed('/root/out.md');

        $this->assertSame(
            "Could not write '/root/out.md'. Check the destination is writable"
            . ' by the user running quill.',
            $exception->getMessage()
        );
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
