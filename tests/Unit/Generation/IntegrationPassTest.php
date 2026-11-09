<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Passes\IntegrationPass;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\FailingPass;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\NotePass;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IntegrationPass::class)]
final class IntegrationPassTest extends TestCase
{
    public function test_the_pass_of_an_integration_processes_the_document(): void
    {
        $document = (new IntegrationPass('json_api', new NotePass('done')))->process(DocumentDescription::create(), $this->context());

        self::assertSame(['done'], $document->notes);
    }

    public function test_a_failing_pass_of_an_integration_is_reported_and_the_document_kept(): void
    {
        $context = $this->context();
        $document = DocumentDescription::create()->notes('before');

        self::assertSame($document, (new IntegrationPass('json_api', new FailingPass()))->process($document, $context));
        self::assertDiagnostics(['[warning] pass: the pass ' . FailingPass::class . ' of the integration json_api failed, left out: settings of an unexpected shape'], $context);
    }
}
