<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Support;

use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * A console output whose standard and error outputs are kept apart, in memory.
 */
final class SplitOutput extends StreamOutput implements ConsoleOutputInterface
{
    private OutputInterface $error;

    public function __construct()
    {
        parent::__construct(self::stream());
        $this->error = new StreamOutput(self::stream());
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->error;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->error = $error;
    }

    public function section(): ConsoleSectionOutput
    {
        $sections = [];

        return new ConsoleSectionOutput(self::stream(), $sections, $this->getVerbosity(), $this->isDecorated(), $this->getFormatter());
    }

    public function standard(): string
    {
        return self::contents($this);
    }

    public function error(): string
    {
        return $this->error instanceof StreamOutput ? self::contents($this->error) : '';
    }

    /**
     * @return resource
     */
    private static function stream()
    {
        $stream = fopen('php://memory', 'w+');

        return $stream === false ? throw new RuntimeException('cannot open a memory stream') : $stream;
    }

    private static function contents(StreamOutput $output): string
    {
        rewind($output->getStream());

        return (string) stream_get_contents($output->getStream());
    }
}
