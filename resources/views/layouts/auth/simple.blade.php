<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">
        <div class="grid min-h-svh lg:grid-cols-2">
            <aside class="relative hidden overflow-hidden bg-navy-900 p-10 text-white lg:flex lg:flex-col dark:bg-navy-950">
                <div class="pointer-events-none absolute -end-32 -top-32 size-96 rounded-full bg-navy-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-40 -start-20 size-112 rounded-full bg-navy-400/10 blur-3xl"></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-3 text-lg font-semibold" wire:navigate>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white text-navy-900 shadow-sm">
                        <x-app-logo-icon class="size-6" />
                    </span>
                    {{ config('app.name', 'Laravel') }}
                </a>

                <div class="relative mt-auto max-w-md space-y-4">
                    <h2 class="text-3xl font-semibold tracking-tight">Monitoramento do Diário Oficial</h2>
                    <p class="text-navy-200">Acompanhe ocorrências, processos e multas dos municípios e empresas monitorados em um só lugar.</p>
                </div>
            </aside>

            <main class="flex flex-col items-center justify-center gap-6 p-6 md:p-10">
                <x-app-logo href="{{ route('home') }}" wire:navigate class="lg:hidden" />

                <div class="w-full max-w-sm surface-card p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
