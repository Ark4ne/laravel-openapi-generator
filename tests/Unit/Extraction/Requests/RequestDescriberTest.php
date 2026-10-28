<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Requests;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestDescriber::class)]
final class RequestDescriberTest extends TestCase
{
    public function test_each_method_returns_a_copy(): void
    {
        $describer = RequestDescriber::create();

        $described = $describer->json(['a' => 'string']);

        self::assertNotSame($describer, $described);
        self::assertSame([], $describer->bodies);
    }

    public function test_the_bodies_are_kept_by_media_type_and_add_up(): void
    {
        $describer = RequestDescriber::create()
            ->json(['a' => 'string'])
            ->json(['b' => 'integer'])
            ->form(['c' => 'string'])
            ->multipart(['d' => 'file'])
            ->body(['e' => 'string']);

        self::assertSame([
            'application/json' => ['a' => 'string', 'b' => 'integer'],
            'application/x-www-form-urlencoded' => ['c' => 'string'],
            'multipart/form-data' => ['d' => 'file'],
            '' => ['e' => 'string'],
        ], $describer->bodies);
    }

    public function test_the_query_and_the_parameters_add_up(): void
    {
        $parameter = ParameterDescription::query('page');

        $describer = RequestDescriber::create()->query(['a' => 'string'])->query(['b' => 'integer'])->parameter($parameter);

        self::assertSame(['a' => 'string', 'b' => 'integer'], $describer->query);
        self::assertSame([$parameter], $describer->parameters);
    }
}
