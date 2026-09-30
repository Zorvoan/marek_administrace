<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="navbar">
        <div class="container navbar-inner">
            <a class="brand" href="{{ route('posts.index') }}">{{ config('app.name') }}</a>

            <nav class="nav-links" aria-label="Main">
                <a href="{{ route('posts.index') }}" @class(['active' => request()->routeIs('posts.*')])>Posts</a>
                <a href="{{ route('categories.index') }}" @class(['active' => request()->routeIs('categories.*')])>Categories</a>
            </nav>

            <form class="search" method="GET" action="{{ route('posts.index') }}" role="search">
                <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search posts…" aria-label="Search posts">
                <select name="category" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($selectedCategory === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="button">Search</button>
            </form>

            <div class="nav-user">
                @auth
                    <a class="button" href="{{ route('posts.create') }}">New post</a>
                    <span class="nav-name">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link-button">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    <a class="button" href="{{ route('register') }}">Sign up</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
