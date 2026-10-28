<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Architecture;

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

    public function test_exceptions_depend_on_no_layer_but_support(): ArchitectureRule
    {
        return PHPat::rule()
            ->classes(self::layer('Exceptions'))
            ->shouldNotDependOn()
            ->classes(...self::layers(...array_slice(self::LAYERS, 1)))
            ->because('exceptions are usable by every layer.');
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
