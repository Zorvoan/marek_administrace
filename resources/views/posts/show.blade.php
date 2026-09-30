<x-layout :title="$post->title">
    <article class="post">
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            <a class="badge" href="{{ route('posts.index', ['category' => $post->category_id]) }}">{{ $post->category->name }}</a>
            by {{ $post->user->name }} ·
            <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->format('F j, Y') }}</time>
            @if ($post->updated_at->gt($post->created_at))
                · edited
            @endif
        </p>
        <div class="post-body">{{ $post->body }}</div>
    </article>

    <div class="actions">
        <a href="{{ route('posts.index') }}">&larr; All posts</a>

        @can('update', $post)
            <a class="button secondary" href="{{ route('posts.edit', $post) }}">Edit</a>
        @endcan

        @can('delete', $post)
            <form method="POST" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Delete this post permanently?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="button danger">Delete</button>
            </form>
        @endcan
    </div>
</x-layout>
