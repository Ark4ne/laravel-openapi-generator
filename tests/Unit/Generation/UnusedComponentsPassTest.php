<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Generation\Passes\UnusedComponentsPass;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Objects\Discriminator;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnusedComponentsPass::class)]
final class UnusedComponentsPassTest extends TestCase
{
    public function test_a_component_referenced_only_by_a_removed_operation_is_removed(): void
    {
        $document = Descriptions::document(self::returning('User'))->schemas([
            'Label' => Schema::object(),
            'User' => Schema::object(),
        ]);

        self::assertSame(['User'], $this->kept($document));
    }

    public function test_a_component_referenced_by_another_component_is_kept(): void
    {
        $document = Descriptions::document(self::returning('User'))->schemas([
            'Country' => Schema::string(),
            'Address' => Schema::object()->properties(Schema::ref('Country')->objectId('country')),
            'User' => Schema::object()->properties(Schema::array()->items(Schema::ref('Address'))->objectId('addresses')),
        ]);

        self::assertSame(['Country', 'Address', 'User'], $this->kept($document));
    }

    public function test_a_component_a_discriminator_maps_to_is_kept(): void
    {
        $pet = Schema::create()->oneOf(Schema::ref('Cat'))->discriminator(Discriminator::create()->propertyName('type')->mapping(['cat' => '#/components/schemas/Cat', 'dog' => 'Dog'])->defaultMapping('Animal'));
        $document = Descriptions::document(self::returning('Pet'))->schemas([
            'Pet' => $pet,
            'Cat' => Schema::object(),
            'Dog' => Schema::object()->properties(Schema::ref('Bone')->objectId('bone')),
            'Bone' => Schema::object(),
            'Animal' => Schema::object(),
            'Unused' => Schema::object(),
        ]);

        self::assertSame(['Pet', 'Cat', 'Dog', 'Bone', 'Animal'], $this->kept($document));
    }

    public function test_a_component_referenced_only_by_unused_components_is_removed(): void
    {
        $document = Descriptions::document(self::returning('User'))->schemas([
            'User' => Schema::object(),
            'Label' => Schema::object()->properties(Schema::ref('Translation')->objectId('translation')),
            'Translation' => Schema::create()->oneOf(Schema::ref('Label'), Schema::string()),
        ]);

        self::assertSame(['User'], $this->kept($document));
    }

    public function test_the_components_of_parameters_bodies_callbacks_webhooks_and_shared_responses_are_kept(): void
    {
        $operation = Descriptions::operation(HttpMethod::Post);
        $operation = $operation
            ->request($operation->request
                ->parameters(ParameterDescription::query('status', Schema::ref('Status')))
                ->body(BodyDescription::create()->content('application/json', Schema::ref('Input'))))
            ->callbacks(CallbackDescription::create('done', '{$request.body#/url}', self::returning('Callback')));
        $document = Descriptions::document($operation)
            ->webhooks(self::returning('Webhook')->webhook('order.shipped'))
            ->response('Error', ResponseDescription::create(400)->description('Error')->content('application/json', Schema::ref('Error')))
            ->schemas(array_fill_keys(['Status', 'Input', 'Callback', 'Webhook', 'Error', 'Unused'], Schema::object()));

        self::assertSame(['Status', 'Input', 'Callback', 'Webhook', 'Error'], $this->kept($document));
    }

    public function test_the_components_of_the_parameters_of_a_path_are_kept(): void
    {
        $document = Descriptions::document(self::returning('User'))
            ->pathParameters('/api/users/{status}', ParameterDescription::path('status', Schema::ref('Status')))
            ->schemas(['User' => Schema::object(), 'Status' => Schema::string(), 'Unused' => Schema::object()]);

        self::assertSame(['User', 'Status'], $this->kept($document));
    }

    public function test_a_document_without_component_is_unchanged(): void
    {
        $document = Descriptions::document(self::returning('User'));

        self::assertSame($document, (new UnusedComponentsPass())->process($document, $this->context()));
    }

    private static function returning(string $component): OperationDescription
    {
        return Descriptions::operation()->responses(ResponseDescription::ok()->description('OK')->content('application/json', Schema::ref($component)));
    }

    /**
     * @return list<string> the names of the components kept, in order
     */
    private function kept(DocumentDescription $document): array
    {
        return array_keys((new UnusedComponentsPass())->process($document, $this->context())->schemas);
    }
}
