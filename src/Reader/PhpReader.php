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

namespace Phalcon\Quill\Reader;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Reader;
use Phalcon\Quill\Model\ClassDefinition;
use Phalcon\Quill\Model\ClassDefinitionCollection;
use Phalcon\Quill\Model\ConstantDefinition;
use Phalcon\Quill\Model\ConstantDefinitionCollection;
use Phalcon\Quill\Model\Imports;
use Phalcon\Quill\Model\Location;
use Phalcon\Quill\Model\Members;
use Phalcon\Quill\Model\MethodDefinition;
use Phalcon\Quill\Model\MethodDefinitionCollection;
use Phalcon\Quill\Model\ParameterDefinition;
use Phalcon\Quill\Model\ParameterDefinitionCollection;
use Phalcon\Quill\Model\PropertyDefinition;
use Phalcon\Quill\Model\PropertyDefinitionCollection;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Model\Relations;
use Phalcon\Quill\Model\Structure;
use Phalcon\Quill\Reader\Php\TypeRenderer;
use Phalcon\Quill\Reader\Php\ValueRenderer;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

use function file_get_contents;
use function implode;
use function is_string;

use const DIRECTORY_SEPARATOR;

/**
 * Reads `.php` sources through nikic/php-parser.
 *
 * Produces the same model as ZephirReader, so the two can be compared. Three
 * things exist here that Zephir has no equivalent for: function and constant
 * imports (excluded - they are not class imports), enum cases (mapped to
 * constants) and constructor property promotion (emitted as properties as
 * well as parameters, because that is what they are).
 */
final class PhpReader implements Reader
{
    private readonly TypeRenderer $types;
    private readonly ValueRenderer $values;

    public function __construct()
    {
        $this->types  = new TypeRenderer();
        $this->values = new ValueRenderer();
    }

    public function read(Config $config): Registry
    {
        $parser      = (new ParserFactory())->createForNewestSupportedVersion();
        $prefix      = $config->sourceRoot() . DIRECTORY_SEPARATOR;
        $definitions = [];

        foreach (SourceFiles::collect($config) as $relPath) {
            $ast   = $parser->parse((string) file_get_contents($prefix . $relPath)) ?? [];
            $class = $this->readFile($ast, $relPath);

            if ($class !== null) {
                $definitions[] = $class;
            }
        }

        return new Registry(ClassDefinitionCollection::fromDefinitions($definitions));
    }

    private function docComment(Node $node): ?string
    {
        return $node->getDocComment()?->getText();
    }

    /**
     * A parameter's name, or null for the variable-variable form that has no
     * static name to record.
     */
    private function paramName(Node\Param $param): ?string
    {
        if (!$param->var instanceof Node\Expr\Variable) {
            return null;
        }

        return is_string($param->var->name) ? $param->var->name : null;
    }

    /**
     * Promoted constructor parameters are properties, so the model records
     * them as both. Without this, every promoted class would look
     * property-less beside its Zephir twin, which declares them explicitly.
     *
     * @return list<PropertyDefinition>
     */
    private function promoted(Stmt\ClassMethod $method): array
    {
        if ($method->name->toString() !== '__construct') {
            return [];
        }

        $properties = [];
        foreach ($method->params as $param) {
            $name = $this->paramName($param);
            if ($param->flags === 0 || $name === null) {
                continue;
            }

            $properties[] = new PropertyDefinition(
                $name,
                $this->visibility(
                    ($param->flags & Stmt\Class_::MODIFIER_PRIVATE) !== 0,
                    ($param->flags & Stmt\Class_::MODIFIER_PROTECTED) !== 0
                ),
                ($param->flags & Stmt\Class_::MODIFIER_READONLY) !== 0,
                $this->values->render($param->default),
                $this->types->render($param->type) ?? 'mixed',
                '',
                []
            );
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    private function readExtends(Stmt\ClassLike $node): array
    {
        if ($node instanceof Stmt\Class_) {
            return $node->extends === null ? [] : [$node->extends->toString()];
        }

        if ($node instanceof Stmt\Interface_) {
            $names = [];
            foreach ($node->extends as $name) {
                $names[] = $name->toString();
            }

            return $names;
        }

        return [];
    }

    /**
     * @param array<Stmt> $ast
     */
    private function readFile(array $ast, string $relPath): ?ClassDefinition
    {
        $header = $this->readHeader($ast);
        if ($header === null) {
            return null;
        }

        $node      = $header['node'];
        $namespace = $header['namespace'];
        $fqcn      = $namespace === ''
            ? (string) $node->name
            : $namespace . '\\' . (string) $node->name;

        return new ClassDefinition(
            new Location($fqcn, $namespace, $relPath),
            $this->structure($node),
            (new Docblock($this->docComment($node)))->description(),
            new Imports($header['uses'], $header['aliases']),
            new Relations(
                $this->readExtends($node),
                $this->readImplements($node),
                $this->readTraits($node)
            ),
            $this->readMembers($node)
        );
    }

    /**
     * Walks the top level until the declaration, gathering what precedes it.
     *
     * @param array<Stmt> $ast
     *
     * @return array{
     *     namespace: string,
     *     uses: list<string>,
     *     aliases: array<string, string>,
     *     node: Stmt\ClassLike
     * }|null
     */
    private function readHeader(array $ast): ?array
    {
        foreach ($ast as $statement) {
            if ($statement instanceof Stmt\ClassLike) {
                return [
                    'namespace' => '',
                    'uses'      => [],
                    'aliases'   => [],
                    'node'      => $statement,
                ];
            }

            if (!$statement instanceof Stmt\Namespace_) {
                continue;
            }

            $namespace = $statement->name === null ? '' : $statement->name->toString();
            $uses      = [];
            $aliases   = [];

            foreach ($statement->stmts as $inner) {
                if ($inner instanceof Stmt\Use_) {
                    // Only class imports; `use function` and `use const` are
                    // a different relation and would corrupt the Uses list.
                    if ($inner->type !== Stmt\Use_::TYPE_NORMAL) {
                        continue;
                    }

                    foreach ($inner->uses as $use) {
                        $name            = $use->name->toString();
                        $uses[]          = $name;
                        $aliases[$use->getAlias()->toString()] = $name;
                    }

                    continue;
                }

                if ($inner instanceof Stmt\ClassLike) {
                    return [
                        'namespace' => $namespace,
                        'uses'      => $uses,
                        'aliases'   => $aliases,
                        'node'      => $inner,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function readImplements(Stmt\ClassLike $node): array
    {
        if (!$node instanceof Stmt\Class_ && !$node instanceof Stmt\Enum_) {
            return [];
        }

        $names = [];
        foreach ($node->implements as $name) {
            $names[] = $name->toString();
        }

        return $names;
    }

    /**
     * Everything the body declares.
     */
    private function readMembers(Stmt\ClassLike $node): Members
    {
        $constants  = [];
        $properties = [];
        $methods    = [];

        foreach ($node->stmts as $member) {
            if ($member instanceof Stmt\ClassConst) {
                foreach ($member->consts as $const) {
                    $doc          = new Docblock($this->docComment($member));
                    $constants[]  = new ConstantDefinition(
                        $const->name->toString(),
                        $this->values->render($const->value),
                        $doc->varType() ?? $this->scalarType($const->value),
                        $doc->description()
                    );
                }

                continue;
            }

            // An enum case is a named value on the declaration - constant
            // shaped, and the formatter renders enums as classes anyway.
            if ($member instanceof Stmt\EnumCase) {
                $doc         = new Docblock($this->docComment($member));
                $constants[] = new ConstantDefinition(
                    $member->name->toString(),
                    $this->values->render($member->expr),
                    $doc->varType() ?? $this->scalarType($member->expr),
                    $doc->description()
                );

                continue;
            }

            if ($member instanceof Stmt\Property) {
                $doc = new Docblock($this->docComment($member));
                foreach ($member->props as $prop) {
                    $properties[] = new PropertyDefinition(
                        $prop->name->toString(),
                        $this->visibility($member->isPrivate(), $member->isProtected()),
                        $member->isReadonly(),
                        $this->values->render($prop->default),
                        $doc->varType() ?? $this->types->render($member->type) ?? 'mixed',
                        $doc->description(),
                        []
                    );
                }

                continue;
            }

            if ($member instanceof Stmt\ClassMethod) {
                $methods[] = $this->readMethod($member);

                foreach ($this->promoted($member) as $property) {
                    $properties[] = $property;
                }
            }
        }

        return new Members(
            (new ConstantDefinitionCollection($constants))->sortedByName(),
            (new PropertyDefinitionCollection($properties))->sortedByName(),
            new MethodDefinitionCollection($methods)
        );
    }

    private function readMethod(Stmt\ClassMethod $method): MethodDefinition
    {
        $visibility = $this->visibility($method->isPrivate(), $method->isProtected());

        $modifiers = [$visibility];
        if ($method->isStatic()) {
            $modifiers[] = 'static';
        }

        $parameters = [];
        foreach ($method->params as $param) {
            $name = $this->paramName($param);
            if ($name === null) {
                continue;
            }

            $parameters[] = new ParameterDefinition(
                $name,
                $this->types->render($param->type) ?? 'mixed',
                $this->values->render($param->default)
            );
        }

        return new MethodDefinition(
            $method->name->toString(),
            $modifiers,
            $visibility,
            new ParameterDefinitionCollection($parameters),
            $method->returnType === null ? null : $this->types->render($method->returnType),
            (new Docblock($this->docComment($method)))->description()
        );
    }

    /**
     * @return list<string>
     */
    private function readTraits(Stmt\ClassLike $node): array
    {
        $traits = [];
        foreach ($node->stmts as $member) {
            if (!$member instanceof Stmt\TraitUse) {
                continue;
            }

            foreach ($member->traits as $trait) {
                $traits[] = $trait->toString();
            }
        }

        return $traits;
    }

    /**
     * The type a literal default implies, when no `@var` says otherwise.
     */
    private function scalarType(?Node\Expr $expr): string
    {
        return match (true) {
            $expr instanceof Node\Scalar\String_                     => 'string',
            $expr instanceof Node\Scalar\Int_                        => 'int',
            $expr instanceof Node\Scalar\Float_                      => 'float',
            $expr instanceof Node\Expr\Array_                        => 'array',
            $expr instanceof Node\Expr\ConstFetch
                && implode('', $expr->name->getParts()) !== 'null'   => 'bool',
            default                                                  => 'mixed',
        };
    }

    private function structure(Stmt\ClassLike $node): Structure
    {
        if ($node instanceof Stmt\Interface_) {
            return Structure::interface();
        }

        if ($node instanceof Stmt\Trait_) {
            return Structure::trait();
        }

        if ($node instanceof Stmt\Enum_) {
            return Structure::enum();
        }

        return Structure::classType(
            $node instanceof Stmt\Class_ && $node->isAbstract(),
            $node instanceof Stmt\Class_ && $node->isFinal()
        );
    }

    /**
     * @return 'public'|'protected'|'private'
     */
    private function visibility(bool $isPrivate, bool $isProtected): string
    {
        if ($isPrivate) {
            return 'private';
        }

        return $isProtected ? 'protected' : 'public';
    }
}
