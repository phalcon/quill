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

namespace Phalcon\Quill\Tests\Unit\Formatter\Markdown;

use Phalcon\Quill\Formatter\Markdown\Classes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function dirname;
use function file_get_contents;
use function glob;
use function implode;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function str_starts_with;
use function trim;

/**
 * The rendering contract, which after templating lives in three places: the
 * templates that emit the class names, api.css that styles them, and the site
 * theme supplying the --api-* palette. Distance between the first two is nil,
 * so a drift is trivial to fix - the failure is that nothing tells you it
 * drifted. badge--trait and .api-used-by have both already cost follow-up work
 * that way.
 *
 * The reverse direction is deliberately not asserted. Selectors api.css
 * carries that no template emits would flag dead CSS, but the file also holds
 * --api-* custom properties and structural selectors that never appear as
 * template tokens, so the check would be more noise than signal.
 */
final class MarkupContractTest extends TestCase
{
    /**
     * The two declared names that are prefixes rather than complete classes,
     * and everything the formatter appends to them. Without these, the first
     * assertion would wave a new `badge--enum` through on the prefix alone and
     * nothing would ask api.css for a rule.
     *
     * @var array<string, list<string>>
     */
    private const PREFIX_VARIANTS = [
        'badge--' => ['abstract', 'class', 'final', 'interface', 'trait'],
        'vis-'    => ['protected', 'public'],
    ];

    public function testEveryClassNameInATemplateIsDeclared(): void
    {
        $declared = $this->declared();

        foreach ($this->tokens() as $token => $file) {
            $known = false;
            foreach ($declared as $name) {
                if ($token === $name || str_starts_with($token, $name)) {
                    $known = true;

                    break;
                }
            }

            $this->assertTrue(
                $known,
                "'" . $token . "' in " . $file . ' is not declared in Classes.'
                . ' Add the constant and a rule in api.css, or correct the template.'
            );
        }
    }

    public function testEveryDeclaredNameHasASelector(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 4) . '/resources/api.css');

        foreach ($this->declared() as $name) {
            $variants = self::PREFIX_VARIANTS[$name] ?? [''];

            foreach ($variants as $variant) {
                $this->assertStringContainsString(
                    '.' . $name . $variant,
                    $css,
                    "Classes declares '" . $name . $variant . "' and api.css does not style it."
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function declared(): array
    {
        /** @var list<string> $values */
        $values = (new ReflectionClass(Classes::class))->getConstants();

        return $values;
    }

    /**
     * Every class name the shipped templates emit, mapped to the file it came
     * from so a failure names it.
     *
     * Placeholders are stripped before the attributes are read, which turns
     * `badge--{{structure}}` into `badge--` and `vis-{{visibility}}` into
     * `vis-` - exactly the prefixes Classes declares.
     *
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $directory = dirname(__DIR__, 4) . '/resources/templates/markdown';
        $tokens    = [];

        foreach (glob($directory . '/*.tpl') ?: [] as $path) {
            $body = (string) preg_replace(
                '/\{\{[a-zA-Z][a-zA-Z0-9]*\}\}/',
                '',
                (string) file_get_contents($path)
            );

            preg_match_all('/class="([^"]*)"/', $body, $attributes);
            foreach ($attributes[1] as $attribute) {
                foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $token) {
                    if ($token !== '') {
                        $tokens[$token] = $path;
                    }
                }
            }

            // The `{ .name }` attribute lists mkdocs reads, which is how the
            // source button and both inline lists carry their class.
            preg_match_all('/\{\s*\.([A-Za-z0-9_-]+)\s*\}/', $body, $lists);
            foreach ($lists[1] as $token) {
                $tokens[$token] = $path;
            }
        }

        $this->assertNotSame([], $tokens, 'No templates were read: ' . implode(', ', [$directory]));

        return $tokens;
    }
}
