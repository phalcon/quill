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

use Phalcon\Quill\Config;
use Phalcon\Quill\Reader\ReaderFactory;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * The invariant the whole project rests on: equivalent sources produce
 * identical models.
 *
 * Every other reader test asserts one reader against expected values, which
 * says nothing about whether the two agree. This reads one declaration written
 * in both languages and compares the models to each other, so a rule applied on
 * one side and missed on the other fails here rather than surfacing later as a
 * false parity difference between cphalcon and phalcon.
 *
 * When a construct is added to one fixture, add it to the twin.
 */
final class ReaderEquivalenceTest extends TestCase
{
    public function testTheSameDeclarationReadsIdenticallyFromBothLanguages(): void
    {
        $this->assertSame($this->read('zephir', 'zep'), $this->read('php', 'php'));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $language, string $extension): array
    {
        $config = new Config(
            $language,
            dirname(__DIR__, 2) . '/Fixtures/twin/' . $extension,
            '/unused',
            'phalcon/cphalcon',
            '5.0.x',
            'phalcon',
            $extension,
            'Phalcon'
        );

        $registry = (new ReaderFactory())->create($language)->read($config);
        $class    = $registry->get('Phalcon\\Twin\\Subject')
            ?? self::fail($language . ' did not read Phalcon\\Twin\\Subject');

        $document = $class->toArray();

        // The file extension is the one thing the two are entitled to disagree
        // about, and it is the only part of the path that differs.
        unset($document['location']['relPath']);

        return $document;
    }
}
