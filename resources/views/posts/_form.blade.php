<form method="POST" action="{{ $action }}">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <label for="title">Title</label>
    <input id="title" name="title" value="{{ old('title', $post->title) }}" maxlength="255" required>
    @error('title') <p class="error">{{ $message }}</p> @enderror

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
        <option value="" disabled @selected(! old('category_id', $post->category_id))>Choose a category…</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) old('category_id', $post->category_id) === $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <p class="hint">Missing the right one? <a href="{{ route('categories.index') }}">Create a category</a> first.</p>
    @error('category_id') <p class="error">{{ $message }}</p> @enderror

    <label for="body">Text</label>
    <textarea id="body" name="body" rows="12" maxlength="10000" required>{{ old('body', $post->body) }}</textarea>
    @error('body') <p class="error">{{ $message }}</p> @enderror

    <div class="actions">
        <button type="submit" class="button">{{ $submit }}</button>
        <a href="{{ $cancel }}">Cancel</a>
    </div>
</form>
