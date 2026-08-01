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

namespace Phalcon\Scribe\Reader;

use FilesystemIterator;
use Phalcon\Scribe\Config;
use Phalcon\Scribe\Contracts\Reader;
use Phalcon\Scribe\Model\ClassDefinition;
use Phalcon\Scribe\Model\ConstantDefinition;
use Phalcon\Scribe\Model\ConstantDefinitionCollection;
use Phalcon\Scribe\Model\Structure;
use Phalcon\Scribe\Model\MethodDefinition;
use Phalcon\Scribe\Model\MethodDefinitionCollection;
use Phalcon\Scribe\Model\ParameterDefinition;
use Phalcon\Scribe\Model\ParameterDefinitionCollection;
use Phalcon\Scribe\Model\PropertyDefinition;
use Phalcon\Scribe\Model\PropertyDefinitionCollection;
use Phalcon\Scribe\Model\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Zephir\Parser\Parser;

use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_replace;
use function sort;
use function str_replace;
use function str_starts_with;
use function strrchr;
use function strtolower;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Reads `.zep` sources through Zephir's own parser.
 *
 * Ported from cphalcon's bin/generate-api-docs.php. Two things differ on
 * purpose: private members are captured rather than skipped - filtering is the
 * formatter's call - and return types and default values are normalised to
 * strings here, so no parser AST leaks into the model.
 */
final class ZephirReader implements Reader
{
    public function read(Config $config): Registry
    {
        $parser      = new Parser();
        $prefix      = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $definitions = [];

        foreach ($this->collectFiles($config) as $relPath) {
            /** @var array<int, mixed> $ast */
            $ast   = $parser->parse($prefix . $relPath);
            $class = $this->readFile($ast, $relPath, $config);

            if ($class !== null) {
                $definitions[$class->fqcn] = $class;
            }
        }

        return new Registry($definitions);
    }

    /**
     * Strips the comment decoration, returning clean text lines.
     */
    private function cleanDocblock(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        $output = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '**' || $line === '*' || $line === '*/') {
                $output[] = '';

                continue;
            }
            if (str_starts_with($line, '* ')) {
                $output[] = substr($line, 2);

                continue;
            }
            if (str_starts_with($line, '*')) {
                $output[] = ltrim(substr($line, 1));

                continue;
            }

            $output[] = $line;
        }

        return trim(implode("\n", $output), "\n");
    }

    /**
     * @return list<string> relative paths of every source file
     */
    private function collectFiles(Config $config): array
    {
        $prefix   = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $config->sourceRoot(),
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $files = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isFile() && $file->getExtension() === $config->extension()) {
                $files[] = str_replace($prefix, '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Removes the tag block from a cleaned docblock, keeping prose, examples
     * and informational tags such as `@todo`.
     */
    private function describe(string $cleanDocblock): string
    {
        $output   = [];
        $skipping = false;
        foreach (explode("\n", $cleanDocblock) as $line) {
            $trimmed = trim($line);

            // Multi-line tags are swallowed up to the next blank line.
            if ($skipping) {
                if ($trimmed === '') {
                    $skipping = false;
                }

                continue;
            }

            if (preg_match('/^@(param|return|throws|var|deprecated|phpstan-\S+|psalm-\S+)\b/', $trimmed) === 1) {
                $skipping = true;

                continue;
            }

            $output[] = $line;
        }

        $text = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $output));

        return trim($text, "\n");
    }

    /**
     * @param array<array-key, mixed> $modifiers
     *
     * @return 'public'|'protected'|'private'
     */
    private function methodVisibility(array $modifiers): string
    {
        if (in_array('private', $modifiers, true)) {
            return 'private';
        }

        return in_array('protected', $modifiers, true) ? 'protected' : 'public';
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function node(array $node, string $key): ?array
    {
        $value = $node[$key] ?? null;
        if (!is_array($value) || $value === []) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function pageKey(string $relPath, Config $config): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $relPath);
        $key      = str_replace('.' . $config->extension(), '', $segments[0]);

        return 'phalcon_' . strtolower($key);
    }

    /**
     * @param array<string, mixed> $parameter
     */
    private function parameterType(array $parameter): string
    {
        $cast = $this->node($parameter, 'cast');
        if ($cast !== null && is_string($cast['value'] ?? null)) {
            return $cast['value'];
        }

        $type = $parameter['data-type'] ?? 'variable';

        return !is_string($type) || $type === 'variable' ? 'mixed' : $type;
    }

    /**
     * @param array<array-key, mixed> $modifiers
     *
     * @return 'public'|'protected'|'private'
     */
    private function propertyVisibility(array $modifiers): string
    {
        if (in_array('private', $modifiers, true)) {
            return 'private';
        }

        // Mirrors the legacy rule: public when declared, protected otherwise.
        return in_array('public', $modifiers, true) ? 'public' : 'protected';
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function readConstants(array $definition): ConstantDefinitionCollection
    {
        $constants = [];
        foreach ($this->section($definition, 'constants') as $constant) {
            $name = $constant['name'] ?? null;
            if (($constant['type'] ?? '') !== 'const' || !is_string($name)) {
                continue;
            }

            $clean   = $this->cleanDocblock($this->text($constant, 'docblock'));
            $default = $this->node($constant, 'default');

            $constants[$name] = new ConstantDefinition(
                $name,
                $this->renderDefault($default),
                $this->varType($clean, $default),
                $this->describe($clean)
            );
        }

        return (new ConstantDefinitionCollection(array_values($constants)))->sortedByName();
    }

    /**
     * Reduces one parsed file to a single definition, or null when the file
     * declares no class, interface or trait.
     *
     * @param array<int, mixed> $ast
     */
    private function readFile(array $ast, string $relPath, Config $config): ?ClassDefinition
    {
        $namespace = '';
        $uses      = [];
        $usesMap   = [];
        $comment   = '';
        $node      = null;
        $structure = Structure::classType(false, false);

        foreach ($ast as $item) {
            if (!is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $type = $item['type'] ?? '';

            if ($type === 'namespace') {
                $namespace = $this->text($item, 'name') ?? '';
                $comment   = '';
            } elseif ($type === 'comment') {
                $comment = $this->text($item, 'value') ?? '';
            } elseif ($type === 'use') {
                $comment = '';
                foreach ($this->section($item, 'aliases') as $alias) {
                    $name = $alias['name'] ?? null;
                    if (!is_string($name)) {
                        continue;
                    }

                    $uses[] = $name;

                    $short = $alias['alias'] ?? null;
                    if (!is_string($short)) {
                        $short = substr((string) strrchr('\\' . $name, '\\'), 1);
                    }

                    $usesMap[$short] = $name;
                }
            } elseif ($type === 'class' || $type === 'interface' || $type === 'trait') {
                $node = $item;

                // Zephir has no enum declaration, so Keyword::Enum is
                // unreachable here; it arrives with the PHP reader.
                $structure = match ($type) {
                    'interface' => Structure::interface(),
                    'trait'     => Structure::trait(),
                    default     => Structure::classType(
                        ($item['abstract'] ?? 0) === 1,
                        ($item['final'] ?? 0) === 1
                    ),
                };

                break;
            }
        }

        if ($node === null) {
            return null;
        }

        $fqcn  = $namespace . '\\' . ($this->text($node, 'name') ?? '');
        $title = (string) preg_replace('/^Phalcon\\\\/', '', $fqcn);

        $definition = $this->node($node, 'definition') ?? [];

        return new ClassDefinition(
            $fqcn,
            $title,
            $this->pageKey($relPath, $config),
            $this->slugify($title),
            $relPath,
            $namespace,
            $structure,
            $this->describe($this->cleanDocblock($comment)),
            $uses,
            $usesMap,
            $this->readExtends($node),
            $this->readImplements($node),
            $this->readTraits($definition),
            $this->readConstants($definition),
            $this->readProperties($definition),
            $this->readMethods($definition)
        );
    }

    /**
     * A class carries at most one parent; an interface may list several.
     *
     * @param array<string, mixed> $node
     *
     * @return list<string>
     */
    private function readExtends(array $node): array
    {
        $extends = $node['extends'] ?? null;

        if (is_string($extends) && $extends !== '') {
            return [$extends];
        }

        if (!is_array($extends)) {
            return [];
        }

        $names = [];
        foreach ($extends as $entry) {
            if (is_array($entry) && is_string($entry['value'] ?? null)) {
                $names[] = $entry['value'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return list<string>
     */
    private function readImplements(array $node): array
    {
        $names = [];
        foreach ($this->section($node, 'implements') as $entry) {
            if (is_string($entry['value'] ?? null)) {
                $names[] = $entry['value'];
            }
        }

        return $names;
    }

    /**
     * Source order is preserved - the formatter's ordering depends on it.
     *
     * @param array<string, mixed> $definition
     */
    private function readMethods(array $definition): MethodDefinitionCollection
    {
        $methods = [];
        foreach ($this->section($definition, 'methods') as $method) {
            $name = $method['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            $modifiers  = $this->strings($method, 'visibility');
            $parameters = [];
            foreach ($this->section($method, 'parameters') as $parameter) {
                if (($parameter['type'] ?? '') !== 'parameter' || !is_string($parameter['name'] ?? null)) {
                    continue;
                }

                $parameters[] = new ParameterDefinition(
                    $parameter['name'],
                    $this->parameterType($parameter),
                    $this->renderDefault($this->node($parameter, 'default'))
                );
            }

            $methods[$name] = new MethodDefinition(
                $name,
                $modifiers,
                $this->methodVisibility($modifiers),
                new ParameterDefinitionCollection($parameters),
                $this->renderReturnType($this->node($method, 'return-type')),
                $this->describe($this->cleanDocblock($this->text($method, 'docblock')))
            );
        }

        return new MethodDefinitionCollection(array_values($methods));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function readProperties(array $definition): PropertyDefinitionCollection
    {
        $properties = [];
        foreach ($this->section($definition, 'properties') as $property) {
            $name = $property['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            $modifiers = $this->strings($property, 'visibility');

            $shortcuts = [];
            foreach ($this->section($property, 'shortcuts') as $shortcut) {
                if (is_string($shortcut['name'] ?? null)) {
                    $shortcuts[] = $shortcut['name'];
                }
            }

            $clean   = $this->cleanDocblock($this->text($property, 'docblock'));
            $default = $this->node($property, 'default');

            $properties[$name] = new PropertyDefinition(
                $name,
                $this->propertyVisibility($modifiers),
                $this->renderDefault($default),
                $this->varType($clean, $default),
                $this->describe($clean),
                $shortcuts
            );
        }

        return (new PropertyDefinitionCollection(array_values($properties)))->sortedByName();
    }

    /**
     * Traits pulled in by the class body. The parser files these under
     * `definition.uses` as `use-trait` nodes - not to be confused with the
     * file's top-level namespace imports, which are a different relation.
     *
     * @param array<string, mixed> $definition
     *
     * @return list<string>
     */
    private function readTraits(array $definition): array
    {
        $traits = [];
        foreach ($this->section($definition, 'uses') as $use) {
            if (($use['type'] ?? '') !== 'use-trait') {
                continue;
            }

            foreach ($this->section($use, 'traits') as $trait) {
                if (is_string($trait['value'] ?? null)) {
                    $traits[] = $trait['value'];
                }
            }
        }

        return $traits;
    }

    /**
     * Renders a default-value expression from the AST into the string the
     * model carries.
     *
     * @param array<string, mixed>|null $expr
     */
    private function renderDefault(?array $expr): ?string
    {
        if ($expr === null) {
            return null;
        }

        /** @var mixed $value */
        $value = $expr['value'] ?? null;
        $type  = $expr['type'] ?? '';

        return match ($type) {
            'string'                 => '"' . $this->scalar($value) . '"',
            'char'                   => "'" . $this->scalar($value) . "'",
            'int', 'uint', 'long',
            'double', 'bool'         => $this->scalar($value),
            'null'                   => 'null',
            'empty-array'            => '[]',
            'array'                  => '[...]',
            'static-constant-access' => $this->scalar($this->node($expr, 'left')['value'] ?? 'self')
                . '::' . $this->scalar($this->node($expr, 'right')['value'] ?? ''),
            'constant'               => $this->scalar($value),
            'minus'                  => '-' . ($this->renderDefault($this->node($expr, 'left')) ?? ''),
            default                  => $this->scalar($value ?? $type),
        };
    }

    /**
     * @param array<string, mixed>|null $returnType
     */
    private function renderReturnType(?array $returnType): ?string
    {
        if ($returnType === null) {
            return null;
        }

        if (($returnType['void'] ?? 0) === 1) {
            return 'void';
        }

        $types = [];
        foreach ($this->section($returnType, 'list') as $entry) {
            $cast = $this->node($entry, 'cast');
            if ($cast !== null && is_string($cast['value'] ?? null)) {
                $type = $cast['value'];
                if (($entry['collection'] ?? 0) === 1) {
                    $type .= '[]';
                }
            } else {
                $type = $entry['data-type'] ?? 'mixed';
                if (!is_string($type) || $type === 'variable') {
                    $type = 'mixed';
                }
            }

            $types[] = $type;
        }

        return $types === [] ? null : implode('|', $types);
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A list-shaped sub-node, filtered to the array entries it holds.
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private function section(array $node, string $key): array
    {
        $value = $node[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * A list-shaped sub-node of plain strings, such as a visibility list.
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<string>
     */
    private function strings(array $node, string $key): array
    {
        $value = $node[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function slugify(string $title): string
    {
        return strtolower((string) preg_replace('/[^\w\s-]/', '', $title));
    }

    private function text(mixed $node, string $key): ?string
    {
        if (!is_array($node)) {
            return null;
        }

        $value = $node[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Type from the `@var` tag, falling back to the default value's type.
     *
     * @param array<string, mixed>|null $default
     */
    private function varType(string $cleanDocblock, ?array $default): string
    {
        if (preg_match('/^@var\s+(.+)$/m', $cleanDocblock, $matches) === 1) {
            return trim($matches[1]);
        }

        return match ($default['type'] ?? '') {
            'string', 'char'       => 'string',
            'int', 'uint', 'long'  => 'int',
            'double'               => 'float',
            'bool'                 => 'bool',
            'empty-array', 'array' => 'array',
            default                => 'mixed',
        };
    }
}
