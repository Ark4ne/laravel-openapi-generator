<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;

/**
 * Builds the samples of a resource by hand (#[ResourceFactory]).
 */
final class ArticleResourceFactory
{
    public function make(string $title): Article
    {
        return new Article(['title' => $title]);
    }

    public function __invoke(): Article
    {
        return new Article(['title' => 'Invoked']);
    }
}
