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

namespace Phalcon\Quill\Formatter;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Exceptions\MissingAsset;
use Phalcon\Quill\Formatter\Markdown\Classes;
use Phalcon\Quill\Formatter\Markdown\Html;
use Phalcon\Quill\Formatter\Markdown\Naming;
use Phalcon\Quill\Formatter\Markdown\Presentation;
use Phalcon\Quill\Formatter\Markdown\Signature;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\Keyword;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Selection;

use function array_keys;
use function array_map;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function ksort;
use function sort;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function stripos;
use function trim;
use function ucfirst;

use const PHP_EOL;

/**
 * Emits the mkdocs Markdown the documentation repositories consume.
 *
 * The exact whitespace and markup here is load-bearing. These documents are
 * diffed between the two Phalcon implementations, so an incidental formatting
 * change reads as an API change. Alter the rendering deliberately, never for
 * tidiness.
 *
 * Naming, escaping and signature rendering live in the Markdown namespace;
 * what stays here is page assembly and the section layout.
 */
final class MarkdownFormatter implements Formatter
{
    public const STYLESHEET = 'api.css';

    private readonly Html $html;
    private readonly Naming $naming;
    private readonly Signature $signature;

    public function __construct()
    {
        $this->html      = new Html();
        $this->naming    = new Naming();
        $this->signature = new Signature($this->html);
    }

    /**
     * The stylesheet the emitted markup depends on: selectors only. Colors come
     * from `--api-*` custom properties it reads but does not define, leaving the
     * palette - and light and dark - to whichever site renders the pages.
     *
     * @return array<string, string>
     */
    public function assets(): array
    {
        $path  = dirname(__DIR__, 2) . '/resources/' . self::STYLESHEET;
        $sheet = @file_get_contents($path);

        if ($sheet === false) {
            throw new MissingAsset($path);
        }

        return [self::STYLESHEET => $sheet];
    }

    public function extension(): string
    {
        return 'md';
    }

    /**
     * @return array<string, string>
     */
    public function format(Registry $registry, Config $config, Selection $selection): array
    {
        $view   = Presentation::from($config);
        $pages  = $this->pages($registry, $view, $selection);
        $output = [];

        foreach (array_keys($pages) as $page) {
            if ($selection->filter !== '' && stripos($page, $selection->filter) === false) {
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
            $index .= $this->indexLine($page, $view);
        }

        $output['index'] = $index;

        foreach ($pages as $page => $fqcns) {
            $document = <<<EOT
                ---
                hide:
                    - navigation
                ---

                !!! info "NOTE"

                    All classes are prefixed with `{$view->rootNamespace}`

                EOT;

            foreach ($fqcns as $fqcn) {
                $class = $registry->get($fqcn);
                if ($class !== null) {
                    $document .= $this->classDoc($class, $registry, $view);
                }
            }

            $output[$page] = $document;
        }

        return $output;
    }

    private function classDoc(ClassDefinition $class, Registry $registry, Presentation $view): string
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

        $badgeClass = Classes::BADGE . ' ' . Classes::BADGE_PREFIX . $css;

        $output = "\n\n## " . $this->naming->title($class, $view) . "\n\n"
            . "<span class=\"{$badgeClass}\">{$badge}</span>\n"
            . "[:material-github: Source on GitHub]"
            . '(' . $view->sourceUrl($class->location->relPath) . ')'
            . '{ .' . Classes::SOURCE_BUTTON . " }\n";

        if ($class->description !== '') {
            $output .= "\n" . $class->description . "\n";
        }

        $output .= $this->tree($class, $registry, $view);
        $output .= $this->uses($class);
        $output .= $this->usedBy($class, $registry, $view);
        $output .= $this->summary($class, $view);
        $output .= $this->constants($class);
        $output .= $this->properties($class);
        $output .= $this->methodDetails($class, $view);

        return $output;
    }

    private function constants(ClassDefinition $class): string
    {
        if ($class->members->constants->isEmpty()) {
            return '';
        }

        $output = "\n### Constants\n\n" . $this->openList();
        foreach ($class->members->constants as $constant) {
            $output .= $this->row(
                '<code class="' . Classes::RETURN_TYPE . '">' . $this->html->escape($constant->varType) . "</code>\n"
                . '<code class="' . Classes::SIGNATURE . '"><span class="' . Classes::TOKEN_CONSTANT
                . '">' . $this->html->escape($constant->name)
                . '</span>' . $this->html->default($constant->default) . "</code>\n",
                $constant->description
            );
        }

        return $output . "</div>\n";
    }

    /**
     * Markdown link to a class when it is in the registry, plain code when not.
     */
    private function fqcnLink(
        string $display,
        ?string $fqcn,
        Registry $registry,
        Presentation $view,
        string $currentPage
    ): string {
        $target = $fqcn === null ? null : $registry->get($fqcn);
        if ($target === null) {
            return "`{$display}`";
        }

        $href       = '#' . $this->naming->anchor($target, $view);
        $targetPage = $this->naming->pageKey($target, $view);
        if ($targetPage !== $currentPage) {
            $href = $targetPage . '.md' . $href;
        }

        return "[`{$display}`]({$href})";
    }

    private function indexLine(string $page, Presentation $view): string
    {
        $label = ucfirst(str_replace($view->pagePrefix, '', $page));

        return '- [' . $view->rootNamespace . ' ' . $label . ']'
            . '(' . $page . '.md)' . PHP_EOL;
    }

    private function methodDetails(ClassDefinition $class, Presentation $view): string
    {
        $groups = $this->orderMethods($class->members->methods);
        if ($groups === null) {
            return '';
        }

        $output = "\n### Methods\n";

        foreach (['public', 'protected'] as $group) {
            if ($groups[$group]->isEmpty()) {
                continue;
            }

            $count   = $groups[$group]->count();
            $label   = ucfirst($group);
            $groupClass = Classes::GROUP;
            $output    .= "\n<div class=\"{$groupClass}\">{$label} · {$count}</div>\n";

            foreach ($groups[$group] as $method) {
                $anchor    = $this->naming->methodAnchor($class, $method->name, $view);
                $signature = implode("\n", $this->signature->lines($method));

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
     * The opening tag of a member list. Three sections emit one, and the class
     * name on it is part of the contract with the stylesheet.
     */
    private function openList(): string
    {
        return '<div class="' . Classes::LIST . "\">\n";
    }

    /**
     * Private methods dropped, reserved (__*) first, then alphabetical, split
     * by visibility. Null when nothing survives.
     *
     * The emptiness guard is on the result rather than the incoming list: the
     * model keeps private members, so a class whose methods are all private
     * would otherwise emit a heading with nothing under it. Both sections that
     * render methods open with this question, so both ask it here.
     *
     * @return array{public: MethodDefinitionCollection, protected: MethodDefinitionCollection}|null
     */
    private function orderMethods(MethodDefinitionCollection $methods): ?array
    {
        $visible = $methods->withoutPrivate()->ordered();

        $groups = [
            'public'    => $visible->withVisibility('public'),
            'protected' => $visible->withVisibility('protected'),
        ];

        if ($groups['public']->isEmpty() && $groups['protected']->isEmpty()) {
            return null;
        }

        return $groups;
    }

    /**
     * Page key => the FQCNs on it, keys sorted and FQCNs sorted within each.
     *
     * Which file a class lands in is a Markdown decision, so the grouping
     * lives here rather than on the registry.
     *
     * @return array<string, list<string>>
     */
    private function pages(Registry $registry, Presentation $view, Selection $selection): array
    {
        $pages = [];
        foreach ($registry->definitions() as $fqcn => $class) {
            if (!$selection->matchesNamespace($fqcn)) {
                continue;
            }

            $pages[$this->naming->pageKey($class, $view)][] = $fqcn;
        }

        ksort($pages);

        foreach ($pages as $page => $fqcns) {
            sort($fqcns);
            $pages[$page] = $fqcns;
        }

        return $pages;
    }

    private function properties(ClassDefinition $class): string
    {
        $visible = $class->members->properties->withoutPrivate();
        if ($visible->isEmpty()) {
            return '';
        }

        $output = "\n### Properties\n\n" . $this->openList();
        foreach ($visible as $property) {
            $visibility = $property->visibility;
            $visClass   = Classes::VISIBILITY . ' ' . Classes::VISIBILITY_PREFIX . $visibility;

            $output .= $this->row(
                "<code class=\"{$visClass}\">{$visibility}</code>\n"
                . '<code class="' . Classes::RETURN_TYPE . '">' . $this->html->escape($property->varType) . "</code>\n"
                . '<code class="' . Classes::SIGNATURE . '"><span class="' . Classes::TOKEN_VARIABLE
                . '">$' . $this->html->escape($property->name)
                . '</span>' . $this->html->default($property->default) . "</code>\n",
                $property->description
            );
        }

        return $output . "</div>\n";
    }

    /**
     * One relation's names as a comma-separated list of links, for the
     * `extends`/`implements` annotation under a class in the tree. A name the
     * registry does not hold falls back to plain code, which fqcnLink handles.
     *
     * @param list<string> $names
     */
    private function relationLinks(
        array $names,
        ClassDefinition $class,
        Registry $registry,
        Presentation $view,
        string $currentPage
    ): string {
        $links = [];
        foreach ($names as $name) {
            $fqcn    = $registry->resolve($name, $class);
            $links[] = $this->fqcnLink($fqcn ?? $name, $fqcn, $registry, $view, $currentPage);
        }

        return implode(', ', $links);
    }

    /**
     * One row of an `api-list`: the wrapper, whatever the caller puts inside,
     * and the description when there is one.
     *
     * Only the envelope is shared - a constant row and a property row differ in
     * the middle, and forcing those through one signature would take more
     * parameters than it saves lines.
     */
    private function row(string $body, string $description): string
    {
        $output = '<div class="' . Classes::ITEM . "\">\n" . $body;

        if ($description !== '') {
            $output .= '<span class="' . Classes::DESCRIPTION . '">'
                . $this->html->inlineCode($description) . "</span>\n";
        }

        return $output . "</div>\n";
    }

    private function summary(ClassDefinition $class, Presentation $view): string
    {
        $groups = $this->orderMethods($class->members->methods);
        if ($groups === null) {
            return '';
        }

        $output = "\n### Method Summary\n\n" . $this->openList();

        foreach (['public', 'protected'] as $group) {
            foreach ($groups[$group] as $method) {
                $anchor   = $this->naming->methodAnchor($class, $method->name, $view);
                $itemClass = Classes::ITEM;
                $visClass  = Classes::VISIBILITY . ' ' . Classes::VISIBILITY_PREFIX . $group;

                $output .= "<a class=\"{$itemClass}\" href=\"#{$anchor}\">\n"
                    . "<code class=\"{$visClass}\">{$group}</code>\n";

                if ($method->returnType !== null) {
                    $output .= '<code class="' . Classes::RETURN_TYPE . '">'
                        . $this->html->escape($method->returnType) . "</code>\n";
                }

                $output .= '<code class="sig">' . $this->signature->inline($method) . "</code>\n";

                $line = $this->summaryLine($method->description);
                if ($line !== '') {
                    $output .= '<span class="desc">'
                        . $this->html->inlineCode($line) . "</span>\n";
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

    private function tree(ClassDefinition $class, Registry $registry, Presentation $view): string
    {
        $currentPage = $this->naming->pageKey($class, $view);
        $level       = 0;
        $lines       = [];

        foreach ($registry->ancestorsOf($class) as $ancestor) {
            $lines[] = str_repeat(' ', $level * 4) . '- '
                . $this->fqcnLink($ancestor['display'], $ancestor['fqcn'], $registry, $view, $currentPage);
            $level++;
        }

        $current = str_repeat(' ', $level * 4) . "- **`{$class->location->fqcn}`**";

        $annotations = [];
        if ($class->structure->keyword === Keyword::Interface && count($class->relations->extends) > 1) {
            $annotations[] = 'extends '
                . $this->relationLinks($class->relations->extends, $class, $registry, $view, $currentPage);
        }

        if ($class->relations->implements !== []) {
            $annotations[] = 'implements '
                . $this->relationLinks($class->relations->implements, $class, $registry, $view, $currentPage);
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
                . $this->fqcnLink($child, $child, $registry, $view, $currentPage);
        }

        return "\n<div class=\"" . Classes::TREE . "\" markdown>\n\n"
            . implode("\n", $lines)
            . "\n\n</div>\n";
    }

    /**
     * The inverse of a trait's usage: which classes pull it in. Rendered as
     * links because, unlike the import list, every target is by construction
     * in the registry.
     */
    private function usedBy(ClassDefinition $class, Registry $registry, Presentation $view): string
    {
        $users = $registry->usedBy($class);
        if ($users === []) {
            return '';
        }

        sort($users);

        $currentPage = $this->naming->pageKey($class, $view);

        $links = array_map(
            fn (string $fqcn): string => $this->fqcnLink($fqcn, $fqcn, $registry, $view, $currentPage),
            $users
        );

        return "\n__Used by__ " . implode(' · ', $links) . "\n{ ." . Classes::USED_BY . " }\n";
    }

    private function uses(ClassDefinition $class): string
    {
        if ($class->imports->uses === []) {
            return '';
        }

        $uses = $class->imports->uses;
        sort($uses);

        $codes = array_map(
            static fn (string $use): string => "`{$use}`",
            $uses
        );

        return "\n__Uses__ " . implode(' · ', $codes) . "\n{ ." . Classes::USES . " }\n";
    }
}
