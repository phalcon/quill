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
use Phalcon\Scribe\Model\Keyword;
use Phalcon\Scribe\Model\MethodDefinition;
use Phalcon\Scribe\Model\ParameterDefinition;
use Phalcon\Scribe\Model\PropertyDefinition;
use Phalcon\Scribe\Model\Registry;

use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use function htmlspecialchars;
use function implode;
use function preg_replace;
use function sort;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function stripos;
use function strtolower;
use function trim;
use function ucfirst;
use function uksort;

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
    public function format(Registry $registry, Config $config, string $filter = ''): array
    {
        $pages  = $registry->pages();
        $output = [];

        foreach (array_keys($pages) as $page) {
            if ($filter !== '' && stripos($page, strtolower($filter)) === false) {
                unset($pages[$page]);
            }
        }

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

        if ($class->structure->keyword === Keyword::Interface) {
            $badge = 'Interface';
            $css   = 'interface';
        } elseif ($class->structure->keyword === Keyword::Trait) {
            $badge = 'Trait';
            $css   = 'trait';
        } elseif ($class->structure->isAbstract === true) {
            $badge = 'Abstract';
            $css   = 'abstract';
        } elseif ($class->structure->isFinal === true) {
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
        $output .= $this->usedBy($class, $registry);
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

    /**
     * @param list<ParameterDefinition> $parameters
     *
     * @return list<string> one HTML-rendered string per parameter
     */
    private function htmlParams(array $parameters): array
    {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $rendered[] = '<span class="st">' . $this->escape($parameter->type) . '</span>'
                . ' <span class="sv">$' . $this->escape($parameter->name) . '</span>'
                . $this->htmlDefault($parameter->default);
        }

        return $rendered;
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

    /**
     * Signature for the summary rows as HTML with highlight spans. With two or
     * more parameters each is wrapped in a .prm span which the CSS renders as
     * its own indented line; the markup stays on one line so the markdown
     * pipeline cannot disturb it.
     */
    private function inlineSignature(MethodDefinition $method): string
    {
        $name   = '<span class="sf">' . $this->escape($method->name) . '</span>';
        $params = $this->htmlParams($method->parameters);

        if (count($method->parameters) < 2) {
            $inline = implode(', ', $params);
            $inline = $inline !== '' ? '( ' . $inline . ' )' : '()';

            return $name . $inline;
        }

        $lines = '';
        $last  = count($params) - 1;
        foreach ($params as $index => $param) {
            $comma  = $index < $last ? ',' : '';
            $lines .= '<span class="prm">' . $param . $comma . '</span>';
        }

        return $name . '(' . $lines . ')';
    }

    private function methodAnchor(ClassDefinition $class, string $methodName): string
    {
        return $class->anchor . '-' . strtolower($methodName);
    }

    private function methodDetails(ClassDefinition $class): string
    {
        $groups = $this->orderMethods($class->methods);
        if ($groups['public'] === [] && $groups['protected'] === []) {
            return '';
        }

        $output = "\n### Methods\n";

        foreach (['public', 'protected'] as $group) {
            if ($groups[$group] === []) {
                continue;
            }

            $count   = count($groups[$group]);
            $label   = ucfirst($group);
            $output .= "\n<div class=\"api-group\">{$label} · {$count}</div>\n";

            foreach ($groups[$group] as $method) {
                $anchor    = $this->methodAnchor($class, $method->name);
                $signature = implode("\n", $this->signatureLines($method));

                $output .= "\n#### `{$method->name}()` { #{$anchor} }\n\n"
                    . "```php\n{$signature}\n```\n";

                if ($method->description !== '') {
                    $output .= "\n" . $method->description . "\n";
                }
            }
        }

        return $output;
    }

    /**
     * Private methods dropped, reserved (__*) first, then alphabetical, split
     * by visibility.
     *
     * The emptiness guard lives on the result rather than on the incoming list
     * because the model carries private members that the legacy script had
     * already discarded - checking the raw list would emit a bare heading for
     * a class whose methods are all private.
     *
     * @param list<MethodDefinition> $methods
     *
     * @return array{public: list<MethodDefinition>, protected: list<MethodDefinition>}
     */
    private function orderMethods(array $methods): array
    {
        $groups = ['public' => [], 'protected' => []];
        foreach ($methods as $method) {
            if ($method->visibility === 'private') {
                continue;
            }

            $key = $method->visibility === 'protected' ? 'protected' : 'public';

            $groups[$key][$method->name] = $method;
        }

        $ordered = ['public' => [], 'protected' => []];
        foreach ($groups as $key => $group) {
            uksort(
                $group,
                static function (string $a, string $b): int {
                    $ra = str_starts_with($a, '__') ? 0 : 1;
                    $rb = str_starts_with($b, '__') ? 0 : 1;

                    return [$ra, $a] <=> [$rb, $b];
                }
            );

            $ordered[$key] = array_values($group);
        }

        return $ordered;
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

    /**
     * @param list<ParameterDefinition> $parameters
     *
     * @return list<string> one rendered string per parameter
     */
    private function renderParams(array $parameters): array
    {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $param = $parameter->type . ' $' . $parameter->name;
            if ($parameter->default !== null) {
                $param .= ' = ' . $parameter->default;
            }

            $rendered[] = $param;
        }

        return $rendered;
    }

    /**
     * @return list<string> lines of the fenced signature block
     */
    private function signatureLines(MethodDefinition $method): array
    {
        $prefix = implode(' ', $method->modifiers) . ' function ' . $method->name;
        $suffix = ($method->returnType !== null ? ': ' . $method->returnType : '') . ';';
        $params = $this->renderParams($method->parameters);

        if (count($params) < 2) {
            $inline = implode(', ', $params);
            $inline = $inline !== '' ? '( ' . $inline . ' )' : '()';

            return [$prefix . $inline . $suffix];
        }

        $lines = [$prefix . '('];
        foreach ($params as $index => $param) {
            $comma   = $index < count($params) - 1 ? ',' : '';
            $lines[] = '    ' . $param . $comma;
        }

        $lines[] = ')' . $suffix;

        return $lines;
    }

    private function summary(ClassDefinition $class): string
    {
        $groups = $this->orderMethods($class->methods);
        if ($groups['public'] === [] && $groups['protected'] === []) {
            return '';
        }

        $output = "\n### Method Summary\n\n<div class=\"api-list\">\n";

        foreach (['public', 'protected'] as $group) {
            foreach ($groups[$group] as $method) {
                $anchor = $this->methodAnchor($class, $method->name);
                $sig    = $this->inlineSignature($method);

                $output .= "<a class=\"api-item\" href=\"#{$anchor}\">\n"
                    . "<code class=\"vis vis-{$group}\">{$group}</code>\n";

                if ($method->returnType !== null) {
                    $output .= '<code class="ret">' . $this->escape($method->returnType) . "</code>\n";
                }

                $output .= "<code class=\"sig\">{$sig}</code>\n";

                $line = $this->summaryLine($method->description);
                if ($line !== '') {
                    $output .= '<span class="desc">'
                        . $this->inlineCode($line) . "</span>\n";
                }

                $output .= "</a>\n";
            }
        }

        return $output . "</div>\n";
    }

    /**
     * First prose line of a description, used in the summary rows.
     */
    private function summaryLine(string $description): string
    {
        foreach (explode("\n", $description) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '```')) {
                return '';
            }

            return $line;
        }

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
        if ($class->structure->keyword === Keyword::Interface && count($class->extends) > 1) {
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

    /**
     * The inverse of a trait's usage: which classes pull it in. Rendered as
     * links because, unlike the import list, every target is by construction
     * in the registry.
     */
    private function usedBy(ClassDefinition $class, Registry $registry): string
    {
        $users = $registry->usedBy($class);
        if ($users === []) {
            return '';
        }

        sort($users);

        $links = array_map(
            fn (string $fqcn): string => $this->fqcnLink($fqcn, $fqcn, $registry, $class->page),
            $users
        );

        return "\n__Used by__ " . implode(' · ', $links) . "\n{ .api-used-by }\n";
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
