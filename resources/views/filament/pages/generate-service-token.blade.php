<x-filament::page>
    {{ $this->form }}

    <x-filament::button
        wire:click="generate"
        class="mt-4"
    >
        Generate Token
    </x-filament::button>

    @if ($plainToken)
        <x-filament::card class="mt-6">
            <p class="font-bold text-danger-600">
                Copy this token now. It will not be shown again.
            </p>

            <pre class="mt-2 p-3 bg-gray-100 rounded">
{{ $plainToken }}
            </pre>
        </x-filament::card>
    @endif
</x-filament::page>
