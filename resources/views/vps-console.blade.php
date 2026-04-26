<x-filament-panels::page>
    @php
        $instance  = $this->instance;
        $order     = $instance->order;
        $price     = $order->packPrice;
        $isPending = $instance->isAwaitingInstall();
        $isRunning = $instance->isRunning();
        $isStopped = $instance->isStopped();
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Server Information</x-slot>

                <div class="grid grid-cols-2 gap-6 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">IP Address</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-gray-950 dark:text-white">
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

        @if($isPending)
            <div class="lg:col-span-3">
                <x-filament::section color="warning">
                    <x-slot name="heading">OS Installation in Progress</x-slot>
                    <x-slot name="description">Open the console to complete the OS installation, then click Done when finished. You can also switch to the ISO tab to use a different installation image.</x-slot>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('vcenter-vps.console', $instance->id) }}"
                           target="_blank" rel="noopener"
                           class="fi-btn fi-btn-size-md inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1
                                  bg-primary-600 text-white ring-primary-600 hover:bg-primary-500
                                  dark:bg-primary-500 dark:ring-primary-500 dark:hover:bg-primary-400">
                            Open Install Console
                        </a>
                        <a href="{{ \Fywolf\VcenterVps\Filament\Vps\Pages\VpsIso::getUrl(panel: 'vps', tenant: $instance) }}"
                           class="fi-btn fi-btn-size-md inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1
                                  bg-white text-gray-950 ring-gray-950/10 hover:bg-gray-50
                                  dark:bg-gray-800 dark:text-white dark:ring-white/20 dark:hover:bg-gray-700">
                            Manage ISO
                        </a>
                    </div>
                </x-filament::section>
            </div>
        @endif

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
