<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <style>
            * {
                touch-action: manipulation;
            }
        </style>
        <!-- UnoCSS Runtime for dynamic class generation -->
        <script src="https://cdn.jsdelivr.net/npm/@unocss/runtime@latest/uno.global.js"></script>
        <script>
            // Initialize UnoCSS Runtime with our configuration
            window.__unocss = {
                theme: {
                    colors: {
                        primary: "#1976d2",
                        secondary: "#424242"
                    }
                },
                shortcuts: {
                    btn: "px-4 py-2 rounded inline-block bg-teal-600 text-white cursor-pointer hover:bg-teal-700 disabled:cursor-default disabled:bg-gray-600 disabled:opacity-50",
                    "btn-primary": "bg-blue-500 hover:bg-blue-600",
                    "btn-secondary": "bg-gray-500 hover:bg-gray-600"
                },
                presets: [
                    () => import('https://cdn.jsdelivr.net/npm/@unocss/preset-uno@latest/index.mjs'),
                    () => import('https://cdn.jsdelivr.net/npm/@unocss/preset-attributify@latest/index.mjs')
                ]
            }
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
