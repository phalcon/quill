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

namespace Phalcon\Quill\Formatter\Markdown;

use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\Keyword;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Template\Templates;

use function array_map;
use function count;
use function explode;
use function implode;
use function sort;
use function str_repeat;
use function str_starts_with;
use function trim;
use function ucfirst;

/**
 * One class's section of a page: the heading, the badge, the inheritance tree
 * and every member section under it.
 *
 * Which templates are rendered, in what order, and what a section is skipped
 * for stays here - the templates carry the markup and nothing else. Everything
 * a section needs that does not vary per class is constructor state, so the
 * methods below take a definition and nothing more.
 *
 * Built fresh per format() call, because the registry and the presentation it
 * closes over belong to that run.
 */
final class ClassPage
{
    public function __construct(
        private readonly Templates $templates,
        private readonly Presentation $view,
        private readonly Registry $registry,
        private readonly Naming $naming,
        private readonly Signature $signature,
        private readonly Html $html,
    ) {
    }

    public function render(ClassDefinition $class): string
    {
        $badge     = 'Class';
        $structure = 'class';

        if ($class->structure->keyword === Keyword::Interface) {
            $badge     = 'Interface';
            $structure = 'interface';
        } elseif ($class->structure->keyword === Keyword::Trait) {
            $badge     = 'Trait';
            $structure = 'trait';
        } elseif ($class->structure->isAbstract === true) {
            $badge     = 'Abstract';
            $structure = 'abstract';
        } elseif ($class->structure->isFinal === true) {
            $badge     = 'Final';
            $structure = 'final';
        }

        $description = '';
        if ($class->description !== '') {
            $description = $this->templates->render('class-description', [
                'description' => $class->description,
            ]);
        }

        return $this->templates->render('class', [
            'badge'       => $badge,
            'constants'   => $this->constants($class),
            'description' => $description,
            'methods'     => $this->methodDetails($class),
            'properties'  => $this->properties($class),
            'sourceUrl'   => $this->view->sourceUrl($class->location->relPath),
            'structure'   => $structure,
            'summary'     => $this->summary($class),
            'title'       => $this->naming->title($class, $this->view),
            'tree'        => $this->tree($class),
            'usedBy'      => $this->usedBy($class),
            'uses'        => $this->uses($class),
        ]);
    }

    private function constants(ClassDefinition $class): string
    {
        if ($class->members->constants->isEmpty()) {
            return '';
        }

        $rows = '';
        foreach ($class->members->constants as $constant) {
            $rows .= $this->templates->render('constant-row', [
                'default'     => $this->html->default($constant->default),
                'description' => $this->rowDescription($constant->description),
                'name'        => $this->html->escape($constant->name),
                'type'        => $this->html->escape($constant->varType),
            ]);
        }

        return $this->templates->render('constants', ['rows' => $rows]);
    }

    /**
     * Markdown link to a class when it is in the registry, plain code when not.
     */
    private function fqcnLink(string $display, ?string $fqcn, string $currentPage): string
    {
        $target = $fqcn === null ? null : $this->registry->get($fqcn);
        if ($target === null) {
            return "`{$display}`";
        }

        $href       = '#' . $this->naming->anchor($target, $this->view);
        $targetPage = $this->naming->pageKey($target, $this->view);
        if ($targetPage !== $currentPage) {
            $href = $targetPage . '.md' . $href;
        }

        return "[`{$display}`]({$href})";
    }

    private function methodDetails(ClassDefinition $class): string
    {
        $groups = $this->orderMethods($class->members->methods);
        if ($groups === null) {
            return '';
        }

        $rendered = '';

        foreach (['public', 'protected'] as $group) {
            if ($groups[$group]->isEmpty()) {
                continue;
            }

            $methods = '';
            foreach ($groups[$group] as $method) {
                $description = '';
                if ($method->description !== '') {
                    $description = $this->templates->render('method-description', [
                        'description' => $method->description,
                    ]);
                }

                $methods .= $this->templates->render('method', [
                    'anchor'      => $this->naming->methodAnchor($class, $method->name, $this->view),
                    'description' => $description,
                    'name'        => $method->name,
                    'signature'   => implode("\n", $this->signature->lines($method)),
                ]);
            }

            $rendered .= $this->templates->render('method-group', [
                'count'   => (string) $groups[$group]->count(),
                'label'   => ucfirst($group),
                'methods' => $methods,
            ]);
        }

        return $this->templates->render('methods', ['groups' => $rendered]);
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

    private function properties(ClassDefinition $class): string
    {
        $visible = $class->members->properties->withoutPrivate();
        if ($visible->isEmpty()) {
            return '';
        }

        $rows = '';
        foreach ($visible as $property) {
            $rows .= $this->templates->render('property-row', [
                'default'     => $this->html->default($property->default),
                'description' => $this->rowDescription($property->description),
                'name'        => $this->html->escape($property->name),
                'type'        => $this->html->escape($property->varType),
                'visibility'  => $property->visibility,
            ]);
        }

        return $this->templates->render('properties', ['rows' => $rows]);
    }

    /**
     * One relation's names as a comma-separated list of links, for the
     * `extends`/`implements` annotation under a class in the tree. A name the
     * registry does not hold falls back to plain code, which fqcnLink handles.
     *
     * @param list<string> $names
     */
    private function relationLinks(array $names, ClassDefinition $class, string $currentPage): string
    {
        $links = [];
        foreach ($names as $name) {
            $fqcn    = $this->registry->resolve($name, $class);
            $links[] = $this->fqcnLink($fqcn ?? $name, $fqcn, $currentPage);
        }

        return implode(', ', $links);
    }

    /**
     * The description cell a member row carries, or empty when it has none.
     *
     * Shared by all three row shapes: the markup is identical, and only the
     * question of whether there is anything to say differs.
     */
    private function rowDescription(string $description): string
    {
        if ($description === '') {
            return '';
        }

        return $this->templates->render('row-description', [
            'description' => $this->html->inlineCode($description),
        ]);
    }

    private function summary(ClassDefinition $class): string
    {
        $groups = $this->orderMethods($class->members->methods);
        if ($groups === null) {
            return '';
        }

        $rows = '';

        foreach (['public', 'protected'] as $group) {
            foreach ($groups[$group] as $method) {
                $returnType = '';
                if ($method->returnType !== null) {
                    $returnType = $this->templates->render('summary-return-type', [
                        'type' => $this->html->escape($method->returnType),
                    ]);
                }

                $rows .= $this->templates->render('summary-row', [
                    'anchor'      => $this->naming->methodAnchor($class, $method->name, $this->view),
                    'description' => $this->rowDescription($this->summaryLine($method->description)),
                    'returnType'  => $returnType,
                    'signature'   => $this->signature->inline($method),
                    'visibility'  => $group,
                ]);
            }
        }

        return $this->templates->render('summary', ['rows' => $rows]);
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

    private function tree(ClassDefinition $class): string
    {
        $currentPage = $this->naming->pageKey($class, $this->view);
        $level       = 0;
        $lines       = [];

        foreach ($this->registry->ancestorsOf($class) as $ancestor) {
            $lines[] = str_repeat(' ', $level * 4) . '- '
                . $this->fqcnLink($ancestor['display'], $ancestor['fqcn'], $currentPage);
            $level++;
        }

        $current = str_repeat(' ', $level * 4) . "- **`{$class->location->fqcn}`**";

        $annotations = [];
        if (
            $class->structure->keyword === Keyword::Interface
            && count($class->relations->extends) > 1
        ) {
            $annotations[] = 'extends '
                . $this->relationLinks($class->relations->extends, $class, $currentPage);
        }

        if ($class->relations->implements !== []) {
            $annotations[] = 'implements '
                . $this->relationLinks($class->relations->implements, $class, $currentPage);
        }

        if ($annotations !== []) {
            $current .= ' - ' . implode('; ', $annotations);
        }

        $lines[] = $current;
        $level++;

        $children = $this->registry->childrenOf($class);
        sort($children);
        foreach ($children as $child) {
            $lines[] = str_repeat(' ', $level * 4) . '- '
                . $this->fqcnLink($child, $child, $currentPage);
        }

        return $this->templates->render('tree', ['lines' => implode("\n", $lines)]);
    }

    /**
     * The inverse of a trait's usage: which classes pull it in. Rendered as
     * links because, unlike the import list, every target is by construction
     * in the registry.
     */
    private function usedBy(ClassDefinition $class): string
    {
        $users = $this->registry->usedBy($class);
        if ($users === []) {
            return '';
        }

        sort($users);

        $currentPage = $this->naming->pageKey($class, $this->view);

        $links = array_map(
            fn (string $fqcn): string => $this->fqcnLink($fqcn, $fqcn, $currentPage),
            $users
        );

        return $this->templates->render('used-by', ['entries' => implode(' · ', $links)]);
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

        return $this->templates->render('uses', ['entries' => implode(' · ', $codes)]);
    }
}
