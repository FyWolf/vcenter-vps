<x-filament-panels::page>
    @php
        $instance  = $this->instance;
        $order     = $instance->order;
        $pack      = $order->packPrice->pack;
        $price     = $order->packPrice;
        $isPending = $instance->isAwaitingInstall();
        $isRunning = $instance->isRunning();
        $isStopped = $instance->isStopped();
        $uploadLib = config('vcenter-vps.upload_library_id');
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Status & info --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Server Information</x-slot>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">IP Address</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $instance->vm_ip ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</p>
                        <div class="mt-1">
                            @if($isPending)
                                <x-filament::badge color="warning">Installing</x-filament::badge>
                            @elseif($isRunning)
                                <x-filament::badge color="success">Running</x-filament::badge>
                            @elseif($isStopped)
                                <x-filament::badge color="danger">Stopped</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">Unknown</x-filament::badge>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Order Status</p>
                        <div class="mt-1">
                            <x-filament::badge>{{ $order->status->getLabel() }}</x-filament::badge>
                        </div>
                    </div>
                    @if($order->expires_at)
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Expires</p>
                            <p class="mt-1 text-sm text-gray-950 dark:text-white">
                                {{ $order->expires_at->diffForHumans() }}
                            </p>
                        </div>
                    @endif
                    @if($instance->state_checked_at)
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">State refreshed</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $instance->state_checked_at->diffForHumans() }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- Specs --}}
        <div class="lg:col-span-1">
            <x-filament::section>
                <x-slot name="heading">Specifications</x-slot>

                <div class="space-y-3">
                    @if($price->cores)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">vCPU</span>
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $price->cores }} cores</span>
                        </div>
                    @endif
                    @if($price->memory)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">RAM</span>
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ number_format($price->memory / 1024, 1) }} GB</span>
                        </div>
                    @endif
                    @if($price->disk)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Disk</span>
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $price->disk }} GB</span>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        </div>

        {{-- OS Installation (pending only) --}}
        @if($isPending)
            <div class="lg:col-span-3">
                <x-filament::section>
                    <x-slot name="heading">OS Installation</x-slot>
                    <x-slot name="description">Complete the OS installation using the console, then click Done.</x-slot>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('vcenter-vps.console', $instance->id) }}"
                           target="_blank" rel="noopener"
                           class="fi-btn fi-btn-size-md fi-btn-color-primary fi-color-primary fi-color-custom inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1
                                  bg-primary-600 text-white ring-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:ring-primary-500 dark:hover:bg-primary-400">
                            <x-filament::icon icon="tabler-terminal" class="h-4 w-4"/>
                            Open Console
                        </a>

                        <x-filament::button
                            wire:click="markInstallComplete"
                            wire:confirm="Confirm the OS installation is complete?"
                            color="success"
                            icon="tabler-circle-check"
                        >
                            Installation Done
                        </x-filament::button>
                    </div>

                    @if($uploadLib)
                        <div class="mt-6 border-t border-gray-200 pt-6 dark:border-white/10">
                            <p class="mb-4 text-sm font-medium text-gray-950 dark:text-white">Use a different ISO</p>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {{-- URL --}}
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">From URL</p>
                                    <div class="flex gap-2">
                                        <input wire:model="isoUrl"
                                               type="url"
                                               placeholder="https://example.com/debian.iso"
                                               class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                                      placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                                                      dark:border-white/20 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                                        <x-filament::button wire:click="swapIsoFromUrl" color="gray" size="sm">
                                            Attach
                                        </x-filament::button>
                                    </div>
                                </div>

                                {{-- File --}}
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Upload ISO (max 2 GB)</p>
                                    <div class="flex gap-2">
                                        <input wire:model="isoFile"
                                               type="file"
                                               accept=".iso"
                                               class="block w-full text-sm text-gray-700 dark:text-gray-300
                                                      file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700
                                                      dark:file:bg-gray-700 dark:file:text-gray-300">
                                        <x-filament::button wire:click="swapIsoFromFile" color="gray" size="sm">
                                            Upload
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        @endif

    </div>
</x-filament-panels::page>
