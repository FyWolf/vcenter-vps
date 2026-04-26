<x-filament-panels::page>
    @php
        $instance = $this->instance;
        $order    = $instance->order;
        $price    = $order->packPrice;
    @endphp

    <div class="space-y-6">

        <x-filament::section>
            <x-slot name="heading">Server Information</x-slot>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-950 dark:text-white">
                        Display Name
                    </label>
                    <div class="flex gap-3">
                        <input wire:model="name"
                               type="text"
                               placeholder="{{ $order->packPrice->pack->name }}"
                               class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                      placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                                      dark:border-white/20 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                        <x-filament::button wire:click="saveName" color="primary">
                            Save
                        </x-filament::button>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Used as the display name across the panel. Defaults to the pack name if left empty.
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Server Details</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pack</p>
                    <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $price->pack->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">IP Address</p>
                    <p class="mt-1 font-mono text-sm text-gray-950 dark:text-white">{{ $instance->vm_ip ?? '—' }}</p>
                </div>
                @if($price->cores)
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">vCPU</p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $price->cores }} cores</p>
                    </div>
                @endif
                @if($price->memory)
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">RAM</p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ number_format($price->memory / 1024, 1) }} GB</p>
                    </div>
                @endif
                @if($price->disk)
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Disk</p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $price->disk }} GB</p>
                    </div>
                @endif
                @if($order->expires_at)
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Expires</p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">{{ $order->expires_at->diffForHumans() }}</p>
                    </div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
