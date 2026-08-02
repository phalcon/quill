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

use Phalcon\Quill\Config;

/**
 * The four project-specific values the Markdown output needs, and nothing else.
 *
 * Config carries nine keys describing a source tree, a reader and a repository.
 * Rendering a page needs four of them, so it takes these rather than the whole
 * object - a formatter has no business knowing which language was read or where
 * the sources live, and every key added to Config used to widen a type a dozen
 * signatures already carried.
 */
final class Presentation
{
    private function __construct(
        public readonly string $extension,
        public readonly string $pagePrefix,
        public readonly string $rootNamespace,
        private readonly Config $config,
    ) {
    }

    public static function from(Config $config): self
    {
        return new self(
            $config->extension(),
            $config->pagePrefix(),
            $config->rootNamespace(),
            $config
        );
    }

    /**
     * The "Source on GitHub" link for a definition's file.
     *
     * Delegated rather than copied: the URL is built from three more Config
     * values that nothing else here needs, and duplicating the format would put
     * the link's shape in two places.
     */
    public function sourceUrl(string $relativePath): string
    {
        return $this->config->sourceUrl($relativePath);
    }
}
