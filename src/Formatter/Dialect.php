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
        private readonly string $baseUri = '',
    ) {
    }

    public static function markdown(): self
    {
        return new self('markdown', 'md', MarkdownFormatter::STYLESHEET, false);
    }

    /**
     * `$baseUri` is the path the pages are published under, `/5.20/api` for
     * example. Give it and every link becomes absolute, which is one string
     * that is correct from any depth. Leave it out and the links stay
     * relative, each one written for the depth of the page that refers.
     */
    public static function nimbus(string $baseUri = ''): self
    {
        return new self('nimbus', 'mdx', null, true, rtrim($baseUri, '/'));
    }

    /**
     * A link to another page, from the index.
     *
     * The index sits one segment above the pages it lists, so it reaches a
     * page without climbing. mkdocs keeps every page in one directory, where
     * the index is a file beside the rest and the two forms are the same.
     */
    public function indexLink(string $page): string
    {
        if ($this->baseUri !== '') {
            return $this->baseUri . '/' . $page . '/';
        }

        return $this->mdx ? $page . '/' : $page . '.md';
    }

    /**
     * A link to another page of the same run, from a page.
     *
     * mkdocs links the source file. nimbus serves every page as a directory,
     * so a sibling is one level up. A base URI removes the question: the link
     * is then absolute and the depth of the page that refers does not matter.
     */
    public function pageLink(string $page): string
    {
        if ($this->baseUri !== '') {
            return $this->baseUri . '/' . $page . '/';
        }

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
