<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
