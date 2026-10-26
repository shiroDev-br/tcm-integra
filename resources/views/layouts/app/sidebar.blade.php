<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        {{-- A classe "dark" força o estilo escuro dos itens Flux na sidebar azul-marinho em ambos os temas. --}}
        <flux:sidebar sticky collapsible="mobile" class="dark border-e border-navy-950 bg-navy-900 [html.dark_&]:border-white/10 [html.dark_&]:bg-navy-950">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <x-app.sidebar-nav />

            <flux:sidebar.spacer />

            <div class="hidden items-center gap-1 border-t border-white/10 pt-3 lg:flex">
                <x-desktop-user-menu class="min-w-0 flex-1" />
                <x-app.theme-toggle />
            </div>
        </flux:sidebar>

        <x-app.mobile-header />

        {{ $slot }}

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
