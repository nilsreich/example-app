<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Willkommen')])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col">
            <header class="flex items-center justify-between px-6 py-5 md:px-10">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-7 fill-current text-black dark:text-white" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ config('app.name', 'B2E-Template') }}
                    </span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-2">
                        @auth
                            <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                                {{ __('Dashboard') }}
                            </flux:button>
                        @else
                            <flux:button variant="ghost" :href="route('login')" wire:navigate>
                                {{ __('Log in') }}
                            </flux:button>

                            @if (Route::has('register'))
                                <flux:button variant="primary" :href="route('register')" wire:navigate>
                                    {{ __('Sign up') }}
                                </flux:button>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 md:py-24">
                <div class="flex w-full max-w-2xl flex-col items-center gap-6 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                        <x-app-logo-icon class="size-8 fill-current text-black dark:text-white" />
                    </span>

                    <h1 class="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                        {{ config('app.name', 'B2E-Template') }}
                    </h1>

                    <p class="max-w-xl text-pretty text-base leading-relaxed text-zinc-600 dark:text-zinc-400">
                        {{ __('Die Basis für deine Schichtplanung: Mitarbeiterverwaltung, Disposition,
                        KI-gestützte Besetzungsvorschläge und ein revisionssicherer Audit-Trail –
                        erweiterbar bis zum Produktivbetrieb.') }}
                    </p>

                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        @auth
                            <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                                {{ __('Zum Dashboard') }}
                            </flux:button>
                        @else
                            <flux:button variant="primary" :href="route('login')" wire:navigate>
                                {{ __('Log in') }}
                            </flux:button>

                            @if (Route::has('register'))
                                <flux:button variant="ghost" :href="route('register')" wire:navigate>
                                    {{ __('Sign up') }}
                                </flux:button>
                            @endif
                        @endauth
                    </div>
                </div>
            </main>

            <footer class="px-6 py-5 text-center text-xs text-zinc-500 dark:text-zinc-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'B2E-Template') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>