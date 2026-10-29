<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Attributes;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Exceptions\InvalidAttributeException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Callback::class)]
#[CoversClass(InvalidAttributeException::class)]
final class CallbackTest extends TestCase
{
    /**
     * @return iterable<string, array{array{webhook?: string, resource?: class-string, type?: string, status?: int}, string}>
     */
    public static function invalid(): iterable
    {
        yield 'webhook and type' => [['webhook' => 'order.created', 'type' => 'int'], 'webhook, resource and type are mutually exclusive'];
        yield 'resource and type' => [['resource' => ArticleResource::class, 'type' => 'int'], 'webhook, resource and type are mutually exclusive'];
        yield 'webhook and status' => [['webhook' => 'order.created', 'status' => 204], 'the responses of the webhook apply, status cannot be given with webhook'];
    }

    /**
     * @param array{webhook?: string, resource?: class-string, type?: string, status?: int} $arguments
     */
    #[DataProvider('invalid')]
    public function test_a_callback_has_one_source_and_no_status_with_a_webhook(array $arguments, string $message): void
    {
        $this->expectException(InvalidAttributeException::class);
        $this->expectExceptionMessage($message);

        new Callback('callback', '{$request.body#/url}', ...$arguments);
    }

    public function test_the_message_of_an_attribute_names_it_and_its_target(): void
    {
        self::assertSame('#[Response] on App\UserController::show: resource and type are mutually exclusive', InvalidAttributeException::on('Ark4ne\OpenApi\Attributes\Response', 'App\UserController::show', 'resource and type are mutually exclusive')->getMessage());
    }
}
