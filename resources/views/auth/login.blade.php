<x-layout title="Log in">
    <h1>Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="narrow">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>

        <div class="actions">
            <button type="submit" class="button">Log in</button>
            <a href="{{ route('register') }}">Need an account? Sign up</a>
        </div>
    </form>
</x-layout>
