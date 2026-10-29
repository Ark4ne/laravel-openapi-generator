<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

/**
 * Lists the users. Sorted by name.
 *
 * @deprecated use v2
 */
final class Documented
{
    /**
     * List the users.
     * Second line of the summary.
     *
     * First paragraph
     * of the description.
     *
     * Second paragraph.
     *
     * @deprecated
     */
    public function index(): void {}

    /**
     * Show a user. More details on the same line.
     */
    public function show(): void {}

    /**
     * Store a user.
     * Details on the next line.
     */
    public function store(): void {}

    /** @return int */
    public function tagsOnly(): int
    {
        return 1;
    }

    public function none(): void {}
}
