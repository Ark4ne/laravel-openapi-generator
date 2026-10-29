<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

use BackedEnum;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConditionalTypeForParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConditionalTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnitEnum;

/**
 * Reads PHP types from native declarations and PHPDoc tags (`phpstan/phpdoc-parser`), names resolved against the
 * namespace, the `use` imports, `self` / `static` / `parent` and the templates.
 *
 * The PHPDoc type wins over the native one when both exist: it is the more precise. Parsed comments and imports are
 * cached by the instance, never statically.
 */
final class TypeResolver
{
    private const TEMPLATE_TAG = '/^@(?:phpstan-|psalm-)?template(?:-covariant|-contravariant)?$/';

    private readonly Lexer $lexer;

    private readonly TypeParser $types;

    private readonly PhpDocParser $phpDoc;

    private readonly UseStatements $uses;

    /** @var array<string, DocBlock> */
    private array $docBlocks = [];

    public function __construct()
    {
        $config = new ParserConfig([]);
        $constants = new ConstExprParser($config);
        $this->lexer = new Lexer($config);
        $this->types = new TypeParser($config, $constants);
        $this->phpDoc = new PhpDocParser($config, $this->types, $constants);
        $this->uses = new UseStatements();
    }

    /**
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract|ReflectionProperty $reflector
     */
    public function docBlock(ReflectionClass|ReflectionFunctionAbstract|ReflectionProperty $reflector): DocBlock
    {
        $comment = $reflector->getDocComment();

        if ($comment === false || $comment === '') {
            return DocBlock::empty();
        }

        if (isset($this->docBlocks[$comment])) {
            return $this->docBlocks[$comment];
        }

        try {
            $node = $this->phpDoc->parse(new TokenIterator($this->lexer->tokenize($comment)));
        } catch (ParserException) {
            $node = new PhpDocNode([]);
        }

        return $this->docBlocks[$comment] = new DocBlock($node);
    }

    /**
     * The declaration whose PHPDoc documents a method: the method itself, or, when it has no PHPDoc or only
     * `{@inheritDoc}`, the method it overrides or implements, recursively.
     */
    public function documented(ReflectionFunctionAbstract $function): ReflectionFunctionAbstract
    {
        while ($function instanceof ReflectionMethod && self::inheritsDoc($function)) {
            try {
                $function = $function->getPrototype();
            } catch (Throwable) {
                $parent = $function->getDeclaringClass()->getParentClass();

                if ($parent === false || ! $parent->hasMethod($function->getName())) {
                    break;
                }

                $function = $parent->getMethod($function->getName());
            }
        }

        return $function;
    }

    /**
     * `@return` (inherited with the PHPDoc, see documented()), else the native return type; `null` when neither
     * is declared.
     */
    public function returnType(ReflectionFunctionAbstract $function): ?Type
    {
        $documented = $this->documented($function);
        $tag = $this->docBlock($documented)->node->getReturnTagValues()[0] ?? null;

        if ($tag !== null) {
            return $this->fromNode($tag->type, $this->functionScope($documented));
        }

        return $this->fromNative($function->getReturnType(), $this->functionScope($function));
    }

    /**
     * `@param` of the function for this parameter, else its native type.
     */
    public function parameterType(ReflectionParameter $parameter): ?Type
    {
        $function = $parameter->getDeclaringFunction();
        $documented = $this->documented($function);

        foreach ($this->docBlock($documented)->node->getParamTagValues() as $tag) {
            if ($tag->parameterName === '$' . $parameter->getName()) {
                return $this->fromNode($tag->type, $this->functionScope($documented));
            }
        }

        return $this->fromNative($parameter->getType(), $this->functionScope($function));
    }

    /**
     * `@var` of the property, else its native type.
     */
    public function propertyType(ReflectionProperty $property): ?Type
    {
        $scope = $this->classScope($property->getDeclaringClass());
        $tag = $this->docBlock($property)->node->getVarTagValues()[0] ?? null;

        return $tag !== null ? $this->fromNode($tag->type, $scope) : $this->fromNative($property->getType(), $scope);
    }

    /**
     * Exceptions of `@throws`, unions flattened, in declaration order.
     *
     * @return list<Type>
     */
    public function throws(ReflectionFunctionAbstract $function): array
    {
        $function = $this->documented($function);
        $scope = $this->functionScope($function);
        $types = [];

        foreach ($this->docBlock($function)->node->getThrowsTagValues() as $tag) {
            $type = $this->fromNode($tag->type, $scope);
            array_push($types, ...($type instanceof UnionType ? $type->types : [$type]));
        }

        return $types;
    }

    /**
     * `@property`, `@property-read` and `@property-write` of the class and of its parents; the child's win.
     *
     * @param ReflectionClass<covariant object> $class
     *
     * @return array<string, Type> property name → type
     */
    public function properties(ReflectionClass $class): array
    {
        $parent = $class->getParentClass();
        $properties = $parent === false ? [] : $this->properties($parent);
        $node = $this->docBlock($class)->node;
        $scope = $this->classScope($class);

        foreach ([...$node->getPropertyTagValues(), ...$node->getPropertyReadTagValues(), ...$node->getPropertyWriteTagValues()] as $tag) {
            $properties[ltrim($tag->propertyName, '$')] = $this->fromNode($tag->type, $scope);
        }

        return $properties;
    }

    /**
     * Type arguments the class gives to an ancestor through `@extends` / `@implements`, templates of the classes in
     * between substituted: `@extends JsonResource<User>` → `[User]`. Empty when none is declared.
     *
     * @param ReflectionClass<covariant object> $class
     * @param class-string $ancestor
     *
     * @return list<Type>
     */
    public function genericArguments(ReflectionClass $class, string $ancestor, TypeScope $bindings = new TypeScope()): array
    {
        $scope = $this->classScope($class)->withTemplates($bindings->templates);
        $node = $this->docBlock($class)->node;

        foreach ([...$node->getExtendsTagValues(), ...$node->getImplementsTagValues()] as $tag) {
            $name = $scope->resolveClass(self::identifier($tag->type->type));
            $arguments = array_values(array_map(fn(TypeNode $argument): Type => $this->fromNode($argument, $scope), $tag->type->genericTypes));

            if (strcasecmp($name, $ancestor) === 0) {
                return $arguments;
            }

            if (class_exists($name) || interface_exists($name)) {
                $parent = new ReflectionClass($name);
                $found = $this->genericArguments($parent, $ancestor, new TypeScope(templates: $this->bind($parent, $arguments)));

                if ($found !== []) {
                    return $found;
                }
            }
        }

        $parent = $class->getParentClass();

        return $parent === false ? [] : $this->genericArguments($parent, $ancestor);
    }

    /**
     * A type written in a PHPDoc tag, in the scope of a class or a function.
     *
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract $context
     */
    public function parse(string $type, ReflectionClass|ReflectionFunctionAbstract $context): Type
    {
        $scope = $context instanceof ReflectionClass ? $this->classScope($context) : $this->functionScope($context);

        try {
            return $this->fromNode($this->types->parse(new TokenIterator($this->lexer->tokenize($type))), $scope);
        } catch (ParserException) {
            return new MixedType();
        }
    }

    public function fromNative(?ReflectionType $type, TypeScope $scope = new TypeScope()): ?Type
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $types = [];

            foreach ($type->getTypes() as $member) {
                $resolved = $this->fromNative($member, $scope);

                if ($resolved !== null) {
                    $types[] = $resolved;
                }
            }

            return $type instanceof ReflectionUnionType ? self::union($types) : new IntersectionType($types);
        }

        if (! $type instanceof ReflectionNamedType) {
            return new MixedType();
        }

        // A native class name is already fully qualified.
        $named = $type->isBuiltin() || in_array(strtolower($type->getName()), ['self', 'static', 'parent'], true)
            ? $this->name($type->getName(), $scope)
            : new NamedType($type->getName());

        return $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed' ? new NullableType($named) : $named;
    }

    private function fromNode(TypeNode $node, TypeScope $scope): Type
    {
        return match (true) {
            $node instanceof IdentifierTypeNode => $this->name($node->name, $scope),
            $node instanceof NullableTypeNode => new NullableType($this->fromNode($node->type, $scope)),
            $node instanceof UnionTypeNode => self::union(array_values(array_map(fn(TypeNode $type): Type => $this->fromNode($type, $scope), $node->types))),
            $node instanceof IntersectionTypeNode => new IntersectionType(array_values(array_map(fn(TypeNode $type): Type => $this->fromNode($type, $scope), $node->types))),
            $node instanceof ArrayTypeNode => new ListType($this->fromNode($node->type, $scope)),
            $node instanceof GenericTypeNode => $this->generic($node, $scope),
            $node instanceof ArrayShapeNode => $this->shape($node, $scope),
            $node instanceof ConstTypeNode => $this->constant($node, $scope),
            $node instanceof ThisTypeNode => $scope->self === null ? new MixedType() : new NamedType($scope->self),
            $node instanceof CallableTypeNode => $this->name(self::identifier($node->identifier), $scope),
            $node instanceof ObjectShapeNode => new NamedType('object'),
            $node instanceof ConditionalTypeNode, $node instanceof ConditionalTypeForParameterNode => self::union([$this->fromNode($node->if, $scope), $this->fromNode($node->else, $scope)]),
            default => new MixedType(),
        };
    }

    private function name(string $name, TypeScope $scope): Type
    {
        $lower = strtolower($name);

        return match (true) {
            $lower === 'mixed' => new MixedType(),
            isset(NamedType::ALIASES[$lower]) => new NamedType(NamedType::ALIASES[$lower]),
            in_array($lower, NamedType::BUILTIN, true) => new NamedType($lower),
            in_array($lower, ['self', 'static', '$this'], true) => $scope->self === null ? new MixedType() : new NamedType($scope->self),
            $lower === 'parent' => $scope->parent === null ? new MixedType() : new NamedType($scope->parent),
            isset($scope->templates[$name]) => $scope->templates[$name],
            default => new NamedType($scope->resolveClass($name)),
        };
    }

    private function generic(GenericTypeNode $node, TypeScope $scope): Type
    {
        $arguments = array_values(array_map(fn(TypeNode $type): Type => $this->fromNode($type, $scope), $node->genericTypes));
        $lower = strtolower(self::identifier($node->type));

        if ($lower === 'list' || $lower === 'non-empty-list') {
            return new ListType($arguments[0] ?? new MixedType(), nonEmpty: $lower === 'non-empty-list');
        }

        $base = $this->name(self::identifier($node->type), $scope);

        return match (true) {
            $arguments === [], ! $base instanceof NamedType => $base,
            default => new GenericType($base->name, $arguments),
        };
    }

    private function shape(ArrayShapeNode $node, TypeScope $scope): ArrayShapeType
    {
        $items = array_map(fn(ArrayShapeItemNode $item): ArrayShapeItem => new ArrayShapeItem(
            match (true) {
                $item->keyName instanceof ConstExprIntegerNode => (int) $item->keyName->value,
                $item->keyName instanceof ConstExprStringNode => $item->keyName->value,
                $item->keyName instanceof IdentifierTypeNode => $item->keyName->name,
                default => null,
            },
            $this->fromNode($item->valueType, $scope),
            $item->optional,
        ), $node->items);

        return new ArrayShapeType(array_values($items), $node->kind === ArrayShapeNode::KIND_LIST || $node->kind === ArrayShapeNode::KIND_NON_EMPTY_LIST, $node->sealed);
    }

    private function constant(ConstTypeNode $node, TypeScope $scope): Type
    {
        $value = $node->constExpr;

        return match (true) {
            $value instanceof ConstExprStringNode => new LiteralType($value->value),
            $value instanceof ConstExprIntegerNode => new LiteralType((int) str_replace('_', '', $value->value)),
            $value instanceof ConstExprFloatNode => new LiteralType((float) str_replace('_', '', $value->value)),
            $value instanceof ConstExprTrueNode => new LiteralType(true),
            $value instanceof ConstExprFalseNode => new LiteralType(false),
            $value instanceof ConstExprNullNode => new NamedType('null'),
            $value instanceof ConstFetchNode => $this->classConstant($value, $scope),
            default => new MixedType(),
        };
    }

    /**
     * `Status::DRAFT`, `Status::*` and `Status::DRAFT_*`: the values of the matching constants and enum cases.
     */
    private function classConstant(ConstFetchNode $node, TypeScope $scope): Type
    {
        $name = $this->name($node->className, $scope);

        if (! $name instanceof NamedType || ! (class_exists($name->name) || interface_exists($name->name))) {
            return new MixedType();
        }

        $pattern = '/^' . str_replace('\*', '.*', preg_quote($node->name, '/')) . '$/';
        $values = [];

        try {
            $class = enum_exists($name->name) ? new ReflectionEnum($name->name) : new ReflectionClass($name->name);

            foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
                $value = $constant->getValue();

                if (preg_match($pattern, $constant->getName()) !== 1) {
                    continue;
                }

                // A case of a backed enum stands for its value; a case of a pure enum, for the enum.
                $values[] = match (true) {
                    $value instanceof BackedEnum => new LiteralType($value->value),
                    $value instanceof UnitEnum => new NamedType($value::class),
                    is_scalar($value) => new LiteralType($value),
                    default => new MixedType(),
                };
            }
        } catch (Throwable) {
            return new MixedType();
        }

        return $values === [] ? new MixedType() : self::union($values);
    }

    /**
     * Templates of a class bound to the given arguments, in declaration order; unbound ones get their bound.
     *
     * @param ReflectionClass<covariant object> $class
     * @param list<Type> $arguments
     *
     * @return array<string, Type>
     */
    private function bind(ReflectionClass $class, array $arguments): array
    {
        $templates = [];

        foreach (self::templates($this->docBlock($class)->node) as $position => $template) {
            $templates[$template->name] = $arguments[$position] ?? ($template->bound === null ? new MixedType() : $this->fromNode($template->bound, $this->classScope($class)));
        }

        return $templates;
    }

    /**
     * @param ReflectionClass<covariant object> $class
     */
    private function classScope(ReflectionClass $class): TypeScope
    {
        $file = $class->getFileName();
        $parent = $class->getParentClass();
        $scope = new TypeScope(
            $class->getNamespaceName(),
            $file === false ? [] : $this->uses->at($file, (int) $class->getStartLine()),
            $class->getName(),
            $parent === false ? null : $parent->getName(),
        );

        return $scope->withTemplates($this->unbound($class->getDocComment() === false ? null : $class, $scope));
    }

    private function functionScope(ReflectionFunctionAbstract $function): TypeScope
    {
        if ($function instanceof ReflectionMethod) {
            $class = $function->getDeclaringClass();
            $trait = self::declaringTrait($class, $function);
            $scope = $this->classScope($class);

            if ($trait !== null) {
                // The PHPDoc of a trait method is written with the imports of the trait; `self` is the class.
                $traitScope = $this->classScope($trait);
                $scope = new TypeScope($traitScope->namespace, $traitScope->uses, $scope->self, $scope->parent, $scope->templates);
            }
        } else {
            $file = $function->getFileName();
            $line = (int) $function->getStartLine();
            $class = $function instanceof ReflectionFunction ? $function->getClosureScopeClass() : null;
            $parent = $class?->getParentClass();
            $scope = new TypeScope(
                // A closure has no namespace of its own from PHP 8.4 (`{closure:file:line}`): read its file's.
                $file === false ? $function->getNamespaceName() : $this->uses->namespaceAt($file, $line),
                $file === false ? [] : $this->uses->at($file, $line),
                $class?->getName(),
                $parent instanceof ReflectionClass ? $parent->getName() : null,
            );
        }

        return $scope->withTemplates($this->unbound($function, $scope));
    }

    /**
     * The trait, used by the class or by one of its traits, that declares the method.
     *
     * @param ReflectionClass<covariant object> $class
     *
     * @return ReflectionClass<covariant object>|null
     */
    private static function declaringTrait(ReflectionClass $class, ReflectionMethod $method): ?ReflectionClass
    {
        if ($method->getFileName() === $class->getFileName()) {
            return null;
        }

        foreach ($class->getTraits() as $trait) {
            if ($trait->hasMethod($method->getName()) && $trait->getMethod($method->getName())->getFileName() === $method->getFileName()) {
                return self::declaringTrait($trait, $method) ?? $trait;
            }
        }

        return null;
    }

    /**
     * Templates declared by a class or a function, each standing for its bound (or `mixed`) until bound.
     *
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract|null $reflector
     *
     * @return array<string, Type>
     */
    private function unbound(ReflectionClass|ReflectionFunctionAbstract|null $reflector, TypeScope $scope): array
    {
        if ($reflector === null) {
            return [];
        }

        $templates = [];

        foreach (self::templates($this->docBlock($reflector)->node) as $template) {
            $templates[$template->name] = $template->bound === null ? new MixedType() : $this->fromNode($template->bound, $scope);
        }

        return $templates;
    }

    private static function inheritsDoc(ReflectionMethod $method): bool
    {
        $comment = $method->getDocComment();

        if ($comment === false) {
            return true;
        }

        // The text between `/**` and `*/`, without the leading `*` of each line.
        $text = trim((string) preg_replace('/^\s*\*/m', '', substr($comment, 3, -2)));

        return $text === '' || preg_match('/^\{?@inheritdoc}?$/i', $text) === 1;
    }

    /**
     * `@template`, `@template-covariant`, `@template-contravariant` and their `@phpstan-` / `@psalm-` forms, in
     * declaration order: their position is the position of the type argument they receive.
     *
     * @return list<TemplateTagValueNode>
     */
    private static function templates(PhpDocNode $node): array
    {
        $templates = [];

        foreach ($node->getTags() as $tag) {
            if ($tag->value instanceof TemplateTagValueNode && preg_match(self::TEMPLATE_TAG, $tag->name) === 1) {
                $templates[] = $tag->value;
            }
        }

        return $templates;
    }

    /**
     * `A|B|null` → `NullableType(A|B)`; a single member stays itself.
     *
     * @param list<Type> $types
     */
    private static function union(array $types): Type
    {
        $nullable = false;
        $members = [];

        foreach ($types as $type) {
            if ($type instanceof NamedType && $type->name === 'null') {
                $nullable = true;
            } elseif ($type instanceof NullableType) {
                $nullable = true;
                array_push($members, ...($type->type instanceof UnionType ? $type->type->types : [$type->type]));
            } elseif ($type instanceof UnionType) {
                array_push($members, ...$type->types);
            } else {
                $members[] = $type;
            }
        }

        $unique = [];

        foreach ($members as $member) {
            $unique[$member->toString()] = $member;
        }

        $members = array_values($unique);
        $type = match (count($members)) {
            0 => new NamedType('null'),
            1 => $members[0],
            default => new UnionType($members),
        };

        return $nullable && $members !== [] ? new NullableType($type) : $type;
    }

    /**
     * The name of an identifier node, or of the name node phpdoc-parser 2.4 types next to it (which its parser never
     * produces), as written.
     */
    private static function identifier(TypeNode $node): string
    {
        return $node instanceof IdentifierTypeNode ? $node->name : (string) $node;
    }
}
