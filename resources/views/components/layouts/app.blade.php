<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CA Sion Stats' }}</title>
    
    @vite('resources/css/app.css')
</head>
<body class="bg-base-200 min-h-screen">
    <div class="container mx-auto py-8 px-4">
        {{ $slot }}
    </div>
</body>
</html>
