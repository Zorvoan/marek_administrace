<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth', only: ['store'])];
    }

    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::withCount('posts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:categories'],
        ]));

        return redirect()->route('categories.index')->with('status', 'Category created.');
    }
}
