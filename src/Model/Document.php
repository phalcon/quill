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

namespace Phalcon\Quill\Model;

/**
 * The published shape of a model document.
 *
 * A document outlives the run that wrote it. It is read back by a later
 * invocation, normally from a different repository and a separately installed
 * copy of quill, which makes it an integration contract rather than an
 * internal detail. The key names and the version therefore live here, on the
 * producing side, and every reader asks for them instead of spelling them
 * again.
 *
 * The version is deliberately in the envelope as well as on each definition:
 * a reader has to be able to reject a document before it trusts anything
 * inside it.
 */
final class Document
{
    public const DEFINITIONS = 'definitions';
    public const DESCRIPTION = 'description';
    public const LANGUAGE    = 'language';
    public const MEMBERS     = 'members';
    public const NAME        = 'name';
    public const REPOSITORY  = 'repository';
    public const VERSION     = 'version';

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    public static function envelope(string $language, string $repository, array $definitions): array
    {
        return [
            self::VERSION     => ClassDefinition::MODEL_VERSION,
            self::LANGUAGE    => $language,
            self::REPOSITORY  => $repository,
            self::DEFINITIONS => $definitions,
        ];
    }
}
