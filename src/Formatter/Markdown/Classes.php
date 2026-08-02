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

/**
 * Every CSS class the Markdown output emits.
 *
 * These names are an integration contract with whatever renders the pages -
 * `resources/api.css` styles them, and a documentation site's own stylesheet
 * supplies the `--api-*` palette they read. Scattered as string literals, a
 * rename was invisible here and surfaced as an unstyled page on a published
 * site; gathered here, the set is something you can read, diff and version.
 *
 * Changing a value is a breaking change for the stylesheet. Adding one means
 * adding a rule to api.css in the same commit.
 */
final class Classes
{
    public const BADGE        = 'badge';
    public const BADGE_PREFIX = 'badge--';

    public const GROUP   = 'api-group';
    public const ITEM    = 'api-item';
    public const LIST    = 'api-list';
    public const TREE    = 'api-tree';
    public const USED_BY = 'api-used-by';
    public const USES    = 'api-uses';

    public const SOURCE_BUTTON = 'src-btn';

    public const DESCRIPTION      = 'desc';
    public const PARAMETER        = 'prm';
    public const RETURN_TYPE      = 'ret';
    public const SIGNATURE        = 'sig';
    public const VISIBILITY       = 'vis';
    public const VISIBILITY_PREFIX = 'vis-';

    public const TOKEN_CONSTANT = 'sc';
    public const TOKEN_FUNCTION = 'sf';
    public const TOKEN_MUTED    = 'sm';
    public const TOKEN_TYPE     = 'st';
    public const TOKEN_VARIABLE = 'sv';
}
