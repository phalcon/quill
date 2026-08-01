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

namespace Phalcon\Quill\Parity;

use Phalcon\Quill\Exceptions\IncompatibleDocument;
use Phalcon\Quill\Exceptions\MalformedDocument;
use Phalcon\Quill\Exceptions\MissingDocument;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\Document;

use function file_get_contents;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function str_contains;
use function strrchr;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * A model document read back from disk.
 *
 * Every command that compares two implementations starts by loading two of
 * these and rejecting anything that is not one. The caller passes its own
 * name so the failure still says which command refused the file.
 */
final class ModelDocument
{
    /**
     * @param array<string, mixed> $definitions
     */
    private function __construct(
        public readonly string $language,
        public readonly string $repository,
        public readonly array $definitions,
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new MissingDocument($path);
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new MalformedDocument($path);
        }

        /**
         * The version gate comes before anything is read out of the document,
         * and before the shape of what is inside it is judged - an older
         * document is better explained by its version than by the key that
         * moved. Two versions disagree about where things live, and a
         * comparison that ran anyway would report those moves as differences
         * between the two implementations: false findings in the one tool
         * meant to surface real ones.
         *
         * Language is deliberately not gated: comparing zephir against php is
         * the whole point, so it is carried for reporting only.
         */
        $version = $decoded[Document::VERSION] ?? null;
        if ($version !== ClassDefinition::MODEL_VERSION) {
            throw new IncompatibleDocument(
                $path,
                is_int($version) ? $version : null,
                ClassDefinition::MODEL_VERSION
            );
        }

        if (!is_array($decoded[Document::DEFINITIONS] ?? null)) {
            throw new MalformedDocument($path);
        }

        $language   = $decoded[Document::LANGUAGE] ?? null;
        $repository = $decoded[Document::REPOSITORY] ?? null;

        /** @var array<string, mixed> $definitions */
        $definitions = $decoded[Document::DEFINITIONS];

        return new self(
            is_string($language) ? $language : 'unknown',
            is_string($repository) ? $repository : 'unknown',
            $definitions
        );
    }

    /**
     * The short repository name, which gives a column heading and - by its
     * first letter - the value that selects it.
     */
    public function label(): string
    {
        if (!str_contains($this->repository, '/')) {
            return $this->repository;
        }

        return substr((string) strrchr($this->repository, '/'), 1);
    }
}
