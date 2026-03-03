<div class="space-y-4">
    <div class="flex justify-center">
        <img 
            src="{{ $imageUrl }}" 
            alt="{{ $caption ?? 'Vehicle Image' }}"
            class="rounded-lg shadow-lg max-w-full max-h-[70vh] object-contain"
        >
    </div>
    
    @if($caption || $angle)
    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($caption)
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Caption</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $caption }}</dd>
            </div>
            @endif
            
            @if($angle)
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">View Angle</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @switch($angle)
                            @case('front') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 @break
                            @case('side') bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300 @break
                            @case('rear') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 @break
                            @case('interior') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 @break
                            @default bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300
                        @endswitch">
                        {{ ucfirst($angle) }} View
                    </span>
                </dd>
            </div>
            @endif
        </dl>
    </div>
    @endif
    
    <div class="flex justify-end space-x-2 pt-4 border-t dark:border-gray-700">
        <x-filament::button 
            wire:click="$dispatch('close-modal', id: 'filament-modal')"
            color="gray"
        >
            Close
        </x-filament::button>
    </div>
</div>