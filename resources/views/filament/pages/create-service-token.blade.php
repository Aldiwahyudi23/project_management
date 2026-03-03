<x-filament::page>
    <div class="space-y-6 max-w-xl">

        {{ $this->form }}

        <x-filament::button
            wire:click="generate"
            color="primary"
            icon="heroicon-o-key"
        >
            Generate Token
        </x-filament::button>

        @if ($plainToken)
            <x-filament::section>
                <x-slot name="heading">
                    ⚠️ Copy Token Sekarang
                </x-slot>

                <p class="text-sm text-gray-500">
                    Token ini hanya ditampilkan <strong>sekali</strong>.
                </p>

                <pre class="mt-3 p-3 bg-gray-100 rounded text-sm break-all">
{{ $plainToken }}
                </pre>
            </x-filament::section>
        @endif

    </div>
</x-filament::page>
