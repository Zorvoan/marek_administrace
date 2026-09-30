<x-layout title="Edit post">
    <h1>Edit post</h1>
    @include('posts._form', [
        'action' => route('posts.update', $post),
        'method' => 'PUT',
        'submit' => 'Save changes',
        'cancel' => route('posts.show', $post),
    ])
</x-layout>
