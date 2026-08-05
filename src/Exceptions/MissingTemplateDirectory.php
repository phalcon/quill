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

namespace Phalcon\Quill\Exceptions;

class MissingTemplateDirectory extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct(
            "The configured templates directory '" . $path . "' is not a"
            . ' directory. Remove the `templates` key to use the shipped'
            . ' templates, or correct the path - a run that silently ignored'
            . ' it would apply no override and still report success.'
        );
    }
}
