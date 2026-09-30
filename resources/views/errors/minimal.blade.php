{{-- Overrides the framework's error page: used for 403, 404, 419, 429, 500, 503... --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="container error-page">
        <p class="error-code">@yield('code')</p>
        <h1>@yield('message')</h1>
        <p><a class="button" href="{{ url('/') }}">Back to home</a></p>
    </main>
</body>
</html>
