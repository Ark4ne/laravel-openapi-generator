<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Architecture;

use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\DiagnosticLevel;
use Ark4ne\OpenApi\Context\Diagnostics;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Extraction\Exceptions\ExceptionMapper;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractor;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractor;
use Ark4ne\OpenApi\Extraction\Responses\ResponseExtractor;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Strategies\GroupStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\OperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\QueryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Sampling\Sampler;
use PHPat\Selector\ClassNamespace;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule as ArchitectureRule;
use PHPat\Test\PHPat;

/**
 * Architecture rules of docs/architecture.md, run by PHPStan through phpat (`composer stan`).
 */
final class ArchitectureTest
{
    private const ROOT = 'Ark4ne\OpenApi';

    private const TESTS = 'Ark4ne\OpenApi\Tests';

    /**
     * Layers from left to right: each one depends only on those to its left (docs/architecture.md §2).
     */
    private const LAYERS = [
        'Support',
        'Types',
        'Attributes',
        'Configuration',
        'Description',
        'Context',
        'Discovery',
        'Sampling',
        'Extraction',
        'Generation',
        'Output',
        'Integrations',
        'Laravel',
    ];

    /**
     * Abstract base classes allowed by docs/architecture.md §3.1 and §9.
     */
    private const ABSTRACT_BASES = [
        self::ROOT . '\Description\Description',
    ];

    /**
     * @return iterable<ArchitectureRule>
     */
    public function test_layers_depend_only_on_the_layers_to_their_left(): iterable
    {
        foreach (self::LAYERS as $position => $layer) {
            $right = array_slice(self::LAYERS, $position + 1);

            if ($right === []) {
                continue;
            }

            yield PHPat::rule()
                ->classes(self::layer($layer))
                ->shouldNotDependOn()
                ->classes(...self::layers(...$right))
                ->because("{$layer} depends only on the layers to its left.");
        }
    }

    public function test_exceptions_depend_only_on_support_and_the_diagnostics(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(self::layer('Exceptions'))
            ->shouldNotDependOn()
            ->classes(...self::layers(...array_slice(self::LAYERS, 1)))
            ->excluding(
                Selector::classname(Diagnostics::class),
                Selector::classname(Diagnostic::class),
                Selector::classname(DiagnosticLevel::class),
                Selector::classname(Stage::class),
            )
            ->because('exceptions are usable by every layer; GenerationFailedException carries the diagnostics.');
    }

    /**
     * @return iterable<ArchitectureRule>
     */
    public function test_extension_points_are_named_after_their_role(): iterable
    {
        $roles = [
            'Extractor' => [OperationExtractor::class, RequestExtractor::class, ResponseExtractor::class],
            'RuleMapper' => [RuleMapper::class],
            'Sampler' => [Sampler::class],
            'Pass' => [Pass::class],
            'MiddlewareMapper' => [MiddlewareMapper::class],
            'ExceptionMapper' => [ExceptionMapper::class],
            'Strategy' => [SummaryStrategy::class, TagStrategy::class, GroupStrategy::class, OperationIdStrategy::class, QueryStrategy::class],
        ];

        foreach ($roles as $suffix => $interfaces) {
            yield PHPat::rule()
                ->classes(...array_map(static fn(string $interface) => Selector::implements($interface), $interfaces))
                ->shouldBeNamed('/' . $suffix . '$/', true)
                ->because("an implementation is named after what it handles: <Feature>{$suffix}.");
        }
    }

    public function test_classes_are_final(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::inNamespace(self::TESTS),
                Selector::isInterface(),
                Selector::isEnum(),
                ...self::abstractBases(),
            )
            ->shouldBeFinal()
            ->because('classes are extended by composition, never by inheritance.');
    }

    public function test_only_the_base_classes_are_abstract(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::inNamespace(self::TESTS),
                Selector::isInterface(),
                ...self::abstractBases(),
            )
            ->shouldNotBeAbstract()
            ->because('abstract classes are reserved for the base classes listed in docs/architecture.md.');
    }

    public function test_src_uses_no_trait(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(Selector::inNamespace(self::TESTS))
            ->shouldNotInclude()
            ->classes(Selector::all())
            ->because('traits are forbidden in src/: use a base class or composition.');
    }

    public function test_interfaces_have_no_interface_suffix(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(Selector::inNamespace(self::ROOT), Selector::isInterface()))
            ->shouldBeNamed('/^(?!.*Interface$).*$/', true)
            ->because('interfaces are named after a role: Sampler, Pass, RuleMapper.');
    }

    private static function layer(string $layer): ClassNamespace
    {
        return Selector::inNamespace(self::ROOT . '\\' . $layer);
    }

    /**
     * @return list<ClassNamespace>
     */
    private static function layers(string ...$layers): array
    {
        return array_values(array_map(self::layer(...), $layers));
    }

    /**
     * @return list<\PHPat\Selector\Classname>
     */
    private static function abstractBases(): array
    {
        return array_map(static fn(string $class) => Selector::classname($class), self::ABSTRACT_BASES);
    }
}
