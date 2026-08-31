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

use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinition;

use function implode;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The member shapes as nimbus takes them: properties that a component reads.
 *
 * Nothing here builds markup. A parameter list goes out as a JSON array,
 * which is also a JSX expression, so the component owns every span the page
 * shows and the stylesheet quill used to ship is not needed.
 *
 * The fenced signature is the same text mkdocs gets - it is plain, it sits in
 * a code fence, and there is no reason for two of it.
 */
final class NimbusRows implements Rows
{
    public function __construct(
        private readonly Html $html,
        private readonly Signature $signature,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function constantRow(ConstantDefinition $constant): array
    {
        return [
            'default' => $this->html->escape($constant->default ?? ''),
            'name'    => $this->html->escape($constant->name),
            'type'    => $this->html->escape($constant->varType),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function methodBlock(MethodDefinition $method, string $anchor, string $description): array
    {
        return [
            'anchor'      => $anchor,
            'description' => $description,
            'name'        => $method->name,
            'signature'   => implode("\n", $this->signature->lines($method)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function propertyRow(PropertyDefinition $property): array
    {
        return [
            'default'    => $this->html->escape($property->default ?? ''),
            'name'       => $this->html->escape($property->name),
            'type'       => $this->html->escape($property->varType),
            'visibility' => $property->visibility,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function summaryRow(
        MethodDefinition $method,
        string $anchor,
        string $visibility,
        string $description
    ): array {
        return [
            'anchor'      => $anchor,
            'description' => $description,
            'name'        => $this->html->escape($method->name),
            'params'      => $this->params($method->parameters),
            'visibility'  => $visibility,
        ];
    }

    /**
     * The parameters as a JSON array, which JSX reads as an expression. The
     * encoder does the quoting, so a default value that carries a quote of
     * its own cannot end the attribute.
     */
    private function params(ParameterDefinitionCollection $parameters): string
    {
        $rendered = [];
        foreach ($parameters as $parameter) {
            $rendered[] = [
                'type'    => $parameter->type,
                'name'    => $parameter->name,
                'default' => $parameter->default,
            ];
        }

        return (string) json_encode($rendered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
