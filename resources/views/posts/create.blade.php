<x-layout title="New post">
    <h1>New post</h1>
    @include('posts._form', [
        'post' => new App\Models\Post,
        'action' => route('posts.store'),
        'submit' => 'Publish',
        'cancel' => route('posts.index'),
    ])
</x-layout>
