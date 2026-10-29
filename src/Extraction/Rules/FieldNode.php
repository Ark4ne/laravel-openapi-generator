<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;

/**
 * One segment of a field name in a FieldTree: the field it ends, if any, and the segments below it.
 */
final class FieldNode
{
    /** @var array<string, FieldNode> segment → node, `*` for the items of a list */
    public array $children = [];

    public ?FieldDescription $field = null;

    /**
     * @param list<string> $path
     */
    public function insert(array $path, FieldDescription $field): void
    {
        $segment = array_shift($path);

        if ($segment === null) {
            $this->field = $field;

            return;
        }

        $this->children[$segment] ??= new self();
        $this->children[$segment]->insert($path, $field);
    }

    public function required(): bool
    {
        return $this->field !== null && $this->field->required;
    }
}
