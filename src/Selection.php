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

namespace Phalcon\Quill;

use function str_starts_with;
use function trim;

/**
 * What one run narrows its output to. Config is per-project; this is per-run.
 *
 * The namespace rule lives here because it means one thing in every formatter.
 * The filter is carried rather than interpreted - Markdown matches it against a
 * page key and JSON against an FQCN, and this type does not arbitrate that.
 */
final class Selection
{
    public readonly string $namespace;

    public function __construct(
        public readonly string $filter = '',
        string $namespace = '',
    ) {
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * A namespace written without the configured root gets it prepended, so a
     * caller need not know whether the root is already there.
     */
    public static function of(string $filter, string $namespace, Config $config): self
    {
        $namespace = trim($namespace, '\\');
        $root      = $config->rootNamespace();

        if (
            $namespace !== ''
            && $root !== ''
            && $namespace !== $root
            && !str_starts_with($namespace, $root . '\\')
        ) {
            $namespace = $root . '\\' . $namespace;
        }

        return new self($filter, $namespace);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * The separator is part of the test: without it every sibling whose name
     * merely starts the same way would match.
     */
    public function matchesNamespace(string $fqcn): bool
    {
        if ($this->namespace === '') {
            return true;
        }

        return $fqcn === $this->namespace
            || str_starts_with($fqcn, $this->namespace . '\\');
    }

    public function narrows(): bool
    {
        return $this->filter !== '' || $this->namespace !== '';
    }
}
