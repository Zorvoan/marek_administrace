<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class PostController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth', except: ['index', 'show']),
            new Middleware('can:update,post', only: ['edit', 'update']),
            new Middleware('can:delete,post', only: ['destroy']),
        ];
    }

    /**
     * The home page: all posts, newest first, optionally narrowed by a search
     * term (?q=) and/or a category (?category=).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
        ]);

        $posts = Post::with(['user', 'category'])
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('posts.index', [
            'posts' => $posts,
            'search' => $filters['q'] ?? null,
            'category' => isset($filters['category']) ? Category::find($filters['category']) : null,
        ]);
    }

    public function show(Post $post): View
    {
        return view('posts.show', ['post' => $post->load(['user', 'category'])]);
    }

    public function create(): View
    {
        return view('posts.create', ['categories' => Category::orderBy('name')->get()]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = $request->user()->posts()->create($request->validated());

        return redirect()->route('posts.show', $post)->with('status', 'Post published.');
    }

    public function edit(Post $post): View
    {
        return view('posts.edit', [
            'post' => $post,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $post->update($request->validated());

        return redirect()->route('posts.show', $post)->with('status', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('posts.index')->with('status', 'Post deleted.');
    }
}
