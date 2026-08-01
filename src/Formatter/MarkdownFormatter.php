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

namespace Phalcon\Scribe\Formatter;

use Phalcon\Scribe\Config;
use Phalcon\Scribe\Contracts\Formatter;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\Kind;
use Phalcon\Scribe\Model\PropertyDefinition;
use Phalcon\Scribe\Model\Registry;

use function array_keys;
use function array_map;
use function count;
use function htmlspecialchars;
use function implode;
use function preg_replace;
use function sort;
use function str_repeat;
use function str_replace;
use function ucfirst;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const PHP_EOL;

/**
 * Emits the mkdocs Markdown the documentation repositories consume.
 *
 * A direct port of the emit and render half of cphalcon's
 * bin/generate-api-docs.php. Rendering quirks are reproduced rather than
 * cleaned up - the byte-for-byte gate depends on it, and improvements land
 * once that gate is green.
 */
final class MarkdownFormatter implements Formatter
{
    /**
     * @return array<string, string>
     */
    public function format(Registry $registry, Config $config): array
    {
        $pages  = $registry->pages();
        $output = [];

        $index = <<<EOT
            ---
            hide:
                - toc
            ---

            # API Index
            - - -


            EOT;

        foreach (array_keys($pages) as $page) {
            $index .= $this->indexLine($page);
        }

        $output['index'] = $index;

        foreach ($pages as $page => $fqcns) {
            $document = <<<EOT
                ---
                hide:
                    - navigation
                ---

                !!! info "NOTE"

                    All classes are prefixed with `Phalcon`

                EOT;

            foreach ($fqcns as $fqcn) {
                $class = $registry->get($fqcn);
                if ($class !== null) {
                    $document .= $this->classDoc($class, $registry, $config);
                }
            }

            $output[$page] = $document;
        }

        return $output;
    }

    private function classDoc(ClassDefinition $class, Registry $registry, Config $config): string
    {
        $badge = 'Class';
        $css   = 'class';

        /**
         * No trait branch, deliberately: the legacy script has none, so a
         * trait falls through to Class. Fixed once the gate is green.
         */
        if ($class->kind === Kind::InterfaceKind) {
            $badge = 'Interface';
            $css   = 'interface';
        } elseif ($class->abstract) {
            $badge = 'Abstract';
            $css   = 'abstract';
        } elseif ($class->final) {
            $badge = 'Final';
            $css   = 'final';
        }

        $output = "\n\n## {$class->title}\n\n"
            . "<span class=\"badge badge--{$css}\">{$badge}</span>\n"
            . "[:material-github: Source on GitHub]"
            . '(' . $config->sourceUrl($class->relPath) . ')'
            . "{ .src-btn }\n";

        if ($class->description !== '') {
            $output .= "\n" . $class->description . "\n";
        }

        $output .= $this->tree($class, $registry);
        $output .= $this->uses($class);
        $output .= $this->summary($class);
        $output .= $this->constants($class);
        $output .= $this->properties($class);
        $output .= $this->methodDetails($class);

        return $output;
    }

    private function constants(ClassDefinition $class): string
    {
        if ($class->constants === []) {
            return '';
        }

        $output = "\n### Constants\n\n<div class=\"api-list\">\n";
        foreach ($class->constants as $constant) {
            $output .= "<div class=\"api-item\">\n"
                . '<code class="ret">' . $this->escape($constant->varType) . "</code>\n"
                . '<code class="sig"><span class="sc">' . $this->escape($constant->name)
                . '</span>' . $this->htmlDefault($constant->default) . "</code>\n";

            if ($constant->description !== '') {
                $output .= '<span class="desc">'
                    . $this->inlineCode($constant->description) . "</span>\n";
            }

            $output .= "</div>\n";
        }

        return $output . "</div>\n";
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE);
    }

    /**
     * Markdown link to a class when it is in the registry, plain code when not.
     */
    private function fqcnLink(
        string $display,
        ?string $fqcn,
        Registry $registry,
        string $currentPage
    ): string {
        $target = $fqcn === null ? null : $registry->get($fqcn);
        if ($target === null) {
            return "`{$display}`";
        }

        $href = '#' . $target->anchor;
        if ($target->page !== $currentPage) {
            $href = $target->page . '.md' . $href;
        }

        return "[`{$display}`]({$href})";
    }

    /**
     * Default-value expression as a muted highlight span, or empty.
     */
    private function htmlDefault(?string $default): string
    {
        if ($default === null) {
            return '';
        }

        return '<span class="sm"> = ' . $this->escape($default) . '</span>';
    }

    private function indexLine(string $page): string
    {
        return '- [Phalcon ' . ucfirst(str_replace('phalcon_', '', $page)) . ']'
            . '(' . $page . '.md)' . PHP_EOL;
    }

    /**
     * Escaped text with markdown backtick spans converted to <code>.
     */
    private function inlineCode(string $text): string
    {
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $this->escape($text));
    }

    private function methodDetails(ClassDefinition $class): string
    {
        return '';
    }

    private function properties(ClassDefinition $class): string
    {
        /** @var list<PropertyDefinition> $visible */
        $visible = [];
        foreach ($class->properties as $property) {
            if ($property->visibility !== 'private') {
                $visible[] = $property;
            }
        }

        if ($visible === []) {
            return '';
        }

        $output = "\n### Properties\n\n<div class=\"api-list\">\n";
        foreach ($visible as $property) {
            $visibility = $property->visibility;

            $output .= "<div class=\"api-item\">\n"
                . "<code class=\"vis vis-{$visibility}\">{$visibility}</code>\n"
                . '<code class="ret">' . $this->escape($property->varType) . "</code>\n"
                . '<code class="sig"><span class="sv">$' . $this->escape($property->name)
                . '</span>' . $this->htmlDefault($property->default) . "</code>\n";

            if ($property->description !== '') {
                $output .= '<span class="desc">'
                    . $this->inlineCode($property->description) . "</span>\n";
            }

            $output .= "</div>\n";
        }

        return $output . "</div>\n";
    }

    private function summary(ClassDefinition $class): string
    {
        return '';
    }

    private function tree(ClassDefinition $class, Registry $registry): string
    {
        $level = 0;
        $lines = [];

        foreach ($registry->ancestorsOf($class) as $ancestor) {
            $lines[] = str_repeat(' ', $level * 4) . '- '
                . $this->fqcnLink($ancestor['display'], $ancestor['fqcn'], $registry, $class->page);
            $level++;
        }

        $current = str_repeat(' ', $level * 4) . "- **`{$class->fqcn}`**";

        $annotations = [];
        if ($class->kind === Kind::InterfaceKind && count($class->extends) > 1) {
            $links = [];
            foreach ($class->extends as $name) {
                $fqcn    = $registry->resolve($name, $class);
                $links[] = $this->fqcnLink($fqcn ?? $name, $fqcn, $registry, $class->page);
            }

            $annotations[] = 'extends ' . implode(', ', $links);
        }

        if ($class->implements !== []) {
            $links = [];
            foreach ($class->implements as $name) {
                $fqcn    = $registry->resolve($name, $class);
                $links[] = $this->fqcnLink($fqcn ?? $name, $fqcn, $registry, $class->page);
            }

            $annotations[] = 'implements ' . implode(', ', $links);
        }

        if ($annotations !== []) {
            $current .= ' - ' . implode('; ', $annotations);
        }

        $lines[] = $current;
        $level++;

        $children = $registry->childrenOf($class);
        sort($children);
        foreach ($children as $child) {
            $lines[] = str_repeat(' ', $level * 4) . '- '
                . $this->fqcnLink($child, $child, $registry, $class->page);
        }

        return "\n<div class=\"api-tree\" markdown>\n\n"
            . implode("\n", $lines)
            . "\n\n</div>\n";
    }

    private function uses(ClassDefinition $class): string
    {
        if ($class->uses === []) {
            return '';
        }

        $uses = $class->uses;
        sort($uses);

        $codes = array_map(
            static fn (string $use): string => "`{$use}`",
            $uses
        );

        return "\n__Uses__ " . implode(' · ', $codes) . "\n{ .api-uses }\n";
    }
}
