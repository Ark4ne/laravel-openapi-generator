<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Security;
use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * `#[Operation]`, `#[Tag]` and PHP's `#[\Deprecated]`.
 *
 * - The attributes of an inherited or redefined action are merged along the controllers and along the declarations
 *   of the method, from the highest parent down: a field given by the child wins, `#[Tag]` add up.
 * - `#[Operation]` on the action overrides the PHPDoc and the naming strategies. On the controller class it gives
 *   defaults: it fills only what the action, its PHPDoc and the strategies left empty, except `tags` and
 *   `deprecated: true`, which apply to every action.
 * - `#[Operation(tags: […])]` replaces the tags of the strategy; each `#[Tag]` adds its tag, with its details.
 * - `#[Operation(deprecated: false)]` on the action cancels a `@deprecated`.
 * - `#[Security]`: each attribute is an alternative requirement; those of the action replace those of the class.
 * - `#[Webhook]` names the webhook: the name of the action's attribute, else the route name; on the controller, a
 *   prefix completed by the name of the action's attribute, else by the method name. Without a name, a warning:
 *   the operation is excluded. Its name is its operationId, unless `#[Operation(operationId:)]` gives one:
 *   the naming strategies read the route, which is not what is called.
 */
final readonly class AttributeOperationExtractor implements OperationExtractor
{
    public const PRIORITY = 200;

    /**
     * PHP 8.4 attribute, read by name so that PHP 8.3 is supported.
     */
    private const DEPRECATED = 'Deprecated';

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return $operation->action !== null;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        $defaults = array_reduce($operation->classAttributes(Operation::class, $context), self::merge(...));

        $operationId = $defaults?->operationId;

        if ($defaults !== null) {
            $description = self::defaults($defaults, $description);
        }

        foreach ($operation->actionAttributes(Operation::class, $context) as $attribute) {
            $description = self::operation($attribute, $description);
            $operationId = $attribute->operationId ?? $operationId;
        }

        foreach ($operation->attributes(Tag::class, $context) as $tag) {
            $description = $description->addDeclaredTags(self::tag($tag));

            if (! in_array($tag->name, $description->tags, true)) {
                $description = $description->addTags($tag->name);
            }
        }

        $security = $operation->actionAttributes(Security::class, $context);
        $security = $security === [] ? $operation->classAttributes(Security::class, $context) : $security;

        if ($security !== []) {
            $description = $description->security(...array_map(static fn(Security $attribute): SecurityDescription => SecurityDescription::create([$attribute->scheme => $attribute->scopes]), $security));
        }

        if ($operation->action?->getAttributes(self::DEPRECATED) !== []) {
            $description = $description->deprecated();
        }

        return self::webhook($operation, $description, $operationId, $context);
    }

    /**
     * @param string|null $operationId given by an `#[Operation]`
     */
    private static function webhook(OperationContext $operation, OperationDescription $description, ?string $operationId, GenerationContext $context): OperationDescription
    {
        $prefix = array_reverse($operation->classAttributes(Webhook::class, $context))[0] ?? null;
        $action = array_reverse($operation->actionAttributes(Webhook::class, $context))[0] ?? null;

        if ($prefix === null && $action === null) {
            return $description;
        }

        $name = match (true) {
            $prefix?->name !== null => "{$prefix->name}." . ($action->name ?? $operation->action?->getName()),
            default => $action->name ?? $operation->route->name,
        };

        if ($name === null) {
            $context->diagnostics->warning(Stage::Operation, '#[Webhook] without name on a route without name, route left out', $operation->route->label());

            return $description->exclude();
        }

        return $description->webhook($name)->operationId($operationId ?? $name);
    }

    private static function operation(Operation $attribute, OperationDescription $description): OperationDescription
    {
        $description = $description
            ->summary($attribute->summary ?? $description->summary)
            ->description($attribute->description ?? $description->description)
            ->operationId($attribute->operationId ?? $description->operationId)
            ->deprecated($attribute->deprecated ?? $description->deprecated);

        if ($attribute->tags !== []) {
            $description = $description->tags(...$attribute->tags);
        }

        $externalDocs = self::externalDocs($attribute->externalDocs, $attribute->externalDocsDescription);

        return $externalDocs === null ? $description : $description->externalDocs($externalDocs);
    }

    /**
     * Two `#[Operation]` of a parent and of its child: each field given by the child wins.
     */
    private static function merge(?Operation $parent, Operation $child): Operation
    {
        if ($parent === null) {
            return $child;
        }

        $externalDocs = $child->externalDocs === null ? $parent : $child;

        return new Operation(
            $child->summary ?? $parent->summary,
            $child->description ?? $parent->description,
            $child->operationId ?? $parent->operationId,
            $child->tags === [] ? $parent->tags : $child->tags,
            $child->deprecated ?? $parent->deprecated,
            $externalDocs->externalDocs,
            $externalDocs->externalDocsDescription,
        );
    }

    private static function defaults(Operation $attribute, OperationDescription $description): OperationDescription
    {
        $description = $description
            ->summary($description->summary ?? $attribute->summary)
            ->description($description->description ?? $attribute->description)
            ->operationId($description->operationId ?? $attribute->operationId)
            ->deprecated($description->deprecated || $attribute->deprecated === true)
            ->externalDocs($description->externalDocs ?? self::externalDocs($attribute->externalDocs, $attribute->externalDocsDescription));

        return $attribute->tags === [] ? $description : $description->tags(...$attribute->tags);
    }

    private static function tag(Tag $tag): TagDescription
    {
        return TagDescription::create($tag->name)
            ->description($tag->description)
            ->parent($tag->parent)
            ->summary($tag->summary)
            ->kind($tag->kind)
            ->externalDocs(self::externalDocs($tag->externalDocs, $tag->externalDocsDescription));
    }

    /**
     * @return array{url: string, description?: string}|null
     */
    private static function externalDocs(?string $url, ?string $description): ?array
    {
        return match (true) {
            $url === null => null,
            $description === null => ['url' => $url],
            default => ['url' => $url, 'description' => $description],
        };
    }
}
