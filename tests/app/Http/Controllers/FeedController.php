<?php

namespace Test\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Test\app\Http\Resources\FeedResource;
use Test\app\Models\Post;

class FeedController extends Controller
{
    /**
     * @param Request $request
     * @param string $id
     * @return FeedResource
     */
    public function show(Request $request, string $id): FeedResource
    {
        return new FeedResource(Post::find($id));
    }
}
