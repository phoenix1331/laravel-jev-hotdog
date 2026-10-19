<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Drop a photo and find out whether it is a hot dog. A Laravel playground for Jev image input.">
        <meta name="theme-color" content="#ffffff">
        <title>{{ $title ?? config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        {{ $slot }}
    </body>
</html>
