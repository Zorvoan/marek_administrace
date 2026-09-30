<x-layout>
    @if ($search || $category)
        <h1>Search results</h1>
        <p class="muted">
            {{ $posts->total() }} {{ Str::plural('post', $posts->total()) }} found
            @if ($search) for “{{ $search }}” @endif
            @if ($category) in <strong>{{ $category->name }}</strong> @endif
            · <a href="{{ route('posts.index') }}">Clear filters</a>
        </p>
    @else
        <h1>Latest posts</h1>
    @endif

    @forelse ($posts as $post)
        <article class="card">
            <h2><a href="{{ route('posts.show', $post) }}">{{ $post->title }}</a></h2>
            <p class="meta">
                <a class="badge" href="{{ route('posts.index', ['category' => $post->category_id]) }}">{{ $post->category->name }}</a>
                by {{ $post->user->name }} ·
                <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
            </p>
            <p>{{ Str::limit($post->body, 220) }}</p>
        </article>
    @empty
        <p class="empty">No posts found.</p>
    @endforelse

    {{ $posts->links() }}
</x-layout>
