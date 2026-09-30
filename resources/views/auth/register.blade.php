<x-layout title="Sign up">
    <h1>Sign up</h1>
    <form method="POST" action="{{ route('register') }}" class="narrow">
        @csrf

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required autofocus>
        @error('name') <p class="error">{{ $message }}</p> @enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <p class="hint">At least 8 characters.</p>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>

        <div class="actions">
            <button type="submit" class="button">Create account</button>
            <a href="{{ route('login') }}">Already registered? Log in</a>
        </div>
    </form>
</x-layout>
