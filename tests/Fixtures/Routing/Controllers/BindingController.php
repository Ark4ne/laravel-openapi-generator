<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Priority;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Country;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Order;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Ticket;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;

final class BindingController
{
    public function user(User $user): void {}

    public function post(Post $post): void {}

    public function order(Order $order): void {}

    public function ticket(Ticket $ticket): void {}

    public function country(Country $country): void {}

    public function status(Status $status): void {}

    public function priority(Priority $priority): void {}

    public function scalar(int $page, float $ratio, string $slug): void {}

    public function snake(User $blogAuthor): void {}

    public function untyped(mixed $anything): void {}
}
