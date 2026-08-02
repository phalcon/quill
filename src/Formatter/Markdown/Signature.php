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

use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;

use function count;
use function implode;

/**
 * A method signature in the two shapes the layout needs: one inline HTML
 * string for the summary rows, and fenced code lines for the detail blocks.
 *
 * Both break at two or more parameters - the summary into `.prm` spans the
 * CSS indents, the detail into real lines.
 */
final class Signature
{
    public function __construct(private readonly Html $html)
    {
    }

    /**
     * Inline HTML with highlight spans, for a summary row. With two or more
     * parameters each is wrapped in a `.prm` span that the CSS renders as its
     * own indented line; the markup stays on one line so the markdown pipeline
     * cannot disturb it.
     */
    public function inline(MethodDefinition $method): string
    {
        $name   = '<span class="' . Classes::TOKEN_FUNCTION . '">' . $this->html->escape($method->name) . '</span>';
        $params = $this->htmlParams($method->parameters);

        if (count($method->parameters) < 2) {
            $inline = implode(', ', $params);

            return $name . ($inline !== '' ? '( ' . $inline . ' )' : '()');
        }

        $lines = '';
        $last  = count($params) - 1;
        foreach ($params as $index => $param) {
            $comma  = $index < $last ? ',' : '';
            $lines .= '<span class="' . Classes::PARAMETER . '">' . $param . $comma . '</span>';
        }

        return $name . '(' . $lines . ')';
    }

    /**
     * @return list<string> lines of the fenced signature block
     */
    public function lines(MethodDefinition $method): array
    {
        $prefix = implode(' ', $method->modifiers) . ' function ' . $method->name;
        $suffix = ($method->returnType !== null ? ': ' . $method->returnType : '') . ';';
        $params = $this->params($method->parameters);

        if (count($params) < 2) {
            $inline = implode(', ', $params);
            $inline = $inline !== '' ? '( ' . $inline . ' )' : '()';

            return [$prefix . $inline . $suffix];
        }

        $lines = [$prefix . '('];
        foreach ($params as $index => $param) {
            $comma   = $index < count($params) - 1 ? ',' : '';
            $lines[] = '    ' . $param . $comma;
        }

        $lines[] = ')' . $suffix;

        return $lines;
    }

    /**
     * @return list<string> one HTML-rendered string per parameter
     */
    private function htmlParams(ParameterDefinitionCollection $parameters): array
    {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $rendered[] = '<span class="' . Classes::TOKEN_TYPE . '">'
                . $this->html->escape($parameter->type) . '</span>'
                . ' <span class="' . Classes::TOKEN_VARIABLE . '">$'
                . $this->html->escape($parameter->name) . '</span>'
                . $this->html->default($parameter->default);
        }

        return $rendered;
    }

    /**
     * @return list<string> one plain-text string per parameter
     */
    private function params(ParameterDefinitionCollection $parameters): array
    {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $param = $parameter->type . ' $' . $parameter->name;
            if ($parameter->default !== null) {
                $param .= ' = ' . $parameter->default;
            }

            $rendered[] = $param;
        }

        return $rendered;
    }
}
