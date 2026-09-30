<x-layout title="Categories">
    <h1>Categories</h1>

    @if ($categories->isEmpty())
        <p class="empty">No categories yet.</p>
    @else
        <ul class="category-list">
            @foreach ($categories as $category)
                <li>
                    <a href="{{ route('posts.index', ['category' => $category->id]) }}">{{ $category->name }}</a>
                    <span class="muted">{{ $category->posts_count }} {{ Str::plural('post', $category->posts_count) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <section class="panel">
        <h2>New category</h2>
        @auth
            <form method="POST" action="{{ route('categories.store') }}" class="inline-form">
                @csrf
                <input name="name" value="{{ old('name') }}" maxlength="50" placeholder="Category name" aria-label="Category name" required>
                <button type="submit" class="button">Create</button>
            </form>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        @else
            <p class="muted"><a href="{{ route('login') }}">Log in</a> to create a category.</p>
        @endauth
    </section>
</x-layout>
