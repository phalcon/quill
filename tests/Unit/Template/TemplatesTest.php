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

namespace Phalcon\Quill\Tests\Unit\Template;

use Phalcon\Quill\Exceptions\MissingTemplate;
use Phalcon\Quill\Exceptions\MissingTemplateDirectory;
use Phalcon\Quill\Exceptions\UnknownPlaceholder;
use Phalcon\Quill\Template\Templates;
use PHPUnit\Framework\TestCase;

use function dirname;
use function implode;

/**
 * Resolution order, substitution and the three ways a template set can be
 * wrong.
 *
 * The fixtures under `tests/Fixtures/templates` stand in for a consumer's own
 * directory; the shipped set under `resources/templates` is the fallback.
 */
final class TemplatesTest extends TestCase
{
    public function testACustomFileWinsForThatNameOnly(): void
    {
        $custom  = Templates::for('markdown', $this->fixtures());
        $shipped = Templates::for('markdown', '');

        // The same name, two bodies: the custom directory is consulted first
        // rather than merged into the shipped one.
        $this->assertStringContainsString(
            'CUSTOM',
            $custom->render('class', $this->classValues())
        );
        $this->assertStringNotContainsString(
            'CUSTOM',
            $shipped->render('class', $this->classValues())
        );

        // A name the custom directory does not carry still resolves, so
        // overriding one template is not vendoring the rest.
        $this->assertStringContainsString(
            'All classes are prefixed with',
            $custom->render('page', ['classes' => '', 'namespace' => 'Phalcon'])
        );
    }

    public function testAFileOutsideTheFormatDirectoryIsReported(): void
    {
        $warnings = Templates::unrecognized('markdown', $this->fixtures());

        $joined = implode("\n", $warnings);

        $this->assertStringContainsString("sits outside 'markdown/'", $joined);
        $this->assertStringContainsString('misplaced.tpl', $joined);
    }

    /**
     * The known-name set is the shipped directory's own listing, so a format
     * that ships none has no set to be outside of and the call is inert.
     */
    public function testAFormatWithNoShippedTemplatesIsInert(): void
    {
        $this->assertSame([], Templates::unrecognized('json', $this->fixtures()));
    }

    public function testAnUnmatchedTemplateNameIsReported(): void
    {
        $warnings = Templates::unrecognized('markdown', $this->fixtures());

        $this->assertStringContainsString('constants-row.tpl', $warnings[0]);
        $this->assertStringContainsString("Did you mean 'constant-row.tpl'?", $warnings[0]);
    }

    public function testAnUnsuppliedPlaceholderThrowsNamingEveryToken(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        $this->expectException(UnknownPlaceholder::class);
        $this->expectExceptionMessage('{{alpha}}, {{beta}}');

        $templates->render('pair', []);
    }

    public function testARepeatedPlaceholderIsSubstitutedEverywhere(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        $this->assertSame(
            'twice and twice again',
            $templates->render('repeat', ['word' => 'twice'])
        );
    }

    public function testAShippedTemplateIsRendered(): void
    {
        // No custom directory, so the only place this can come from is the set
        // that ships with quill.
        $templates = Templates::for('markdown', '');

        $this->assertStringContainsString(
            'Sample\\Base',
            $templates->render('class', $this->classValues())
        );
    }

    public function testASuppliedValueNoPlaceholderUsesIsIgnored(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        // One-way strictness: a consumer's template may use fewer slots than
        // it is handed without that being an error.
        $this->assertSame(
            'twice and twice again',
            $templates->render('repeat', ['word' => 'twice', 'unused' => 'ignored'])
        );
    }

    public function testATemplatesPathThatIsNotADirectoryThrows(): void
    {
        $this->expectException(MissingTemplateDirectory::class);
        $this->expectExceptionMessage('is not a directory');

        Templates::for('markdown', $this->fixtures() . '/markdown/class.tpl');
    }

    public function testATemplateWithNoPlaceholdersRendersVerbatim(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        $this->assertSame(
            'nothing to substitute',
            $templates->render('verbatim', [])
        );
    }

    public function testAValueContainingAPlaceholderIsNotRescanned(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        // strtr makes a single pass, so a description that happens to contain
        // {{title}} is text, not an instruction.
        $this->assertSame(
            'literal {{title}} here',
            $templates->render('echo', ['value' => '{{title}}'])
        );
    }

    public function testExactlyOneTrailingNewlineIsStripped(): void
    {
        // The file ends "...</p>\n\n" - a blank line, which is how a template
        // whose output must end in a newline is written. One newline goes, one
        // survives.
        $templates = Templates::for('markdown', $this->fixtures());

        $this->assertSame("<p>x</p>\n", $templates->render('trailing', []));
    }

    public function testMissingInBothThrowsNamingEveryPathItTried(): void
    {
        $templates = Templates::for('markdown', $this->fixtures());

        $this->expectException(MissingTemplate::class);
        $this->expectExceptionMessage('tests/Fixtures/templates/markdown/nowhere.tpl');

        $templates->render('nowhere', []);
    }

    /**
     * Every slot the shipped class template will ever carry. Supplied whole so
     * the two tests that render it keep passing as the real markup replaces the
     * stub - a value no template uses is ignored, which is what makes a
     * superset safe.
     *
     * @return array<string, string>
     */
    private function classValues(): array
    {
        return [
            'badge'       => 'Class',
            'constants'   => '',
            'description' => '',
            'methods'     => '',
            'properties'  => '',
            'sourceUrl'   => 'https://example.com/Base.zep',
            'structure'   => 'class',
            'summary'     => '',
            'title'       => 'Sample\\Base',
            'tree'        => '',
            'usedBy'      => '',
            'uses'        => '',
        ];
    }

    private function fixtures(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/templates';
    }
}
