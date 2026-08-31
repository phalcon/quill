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

use Phalcon\Quill\Formatter\Markdown\Mdx;

/**
 * What separates one Markdown output from another.
 *
 * The page grouping, the index and the shape of a class section are the same
 * whether mkdocs or nimbus renders them. Five things are not: the template
 * directory, the file extension, the stylesheet, how a link to another page
 * is written, and what prose must have escaped. They are gathered here so
 * that one pipeline serves both, and so that a new output is a new factory
 * method rather than a second copy of the pipeline.
 */
final class Dialect
{
    private function __construct(
        public readonly string $name,
        public readonly string $extension,
        public readonly ?string $stylesheet,
        private readonly bool $mdx,
    ) {
    }

    public static function markdown(): self
    {
        return new self('markdown', 'md', MarkdownFormatter::STYLESHEET, false);
    }

    public static function nimbus(): self
    {
        return new self('nimbus', 'mdx', null, true);
    }

    /**
     * A link to another page of the same run.
     *
     * mkdocs links the source file. nimbus serves every page as a directory,
     * so a sibling is one level up. Neither form carries the version prefix,
     * which quill does not know.
     */
    public function pageLink(string $page): string
    {
        return $this->mdx ? '../' . $page . '/' : $page . '.md';
    }

    /**
     * Prose that goes into the document as the docblock wrote it.
     */
    public function prose(string $text): string
    {
        return $this->mdx ? Mdx::safe($text) : $text;
    }
}
