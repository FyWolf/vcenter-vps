<x-filament-panels::page>
    @php $instances = $this->getInstances(); @endphp

    @if($instances->isEmpty())
        <x-filament::section>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">You have no active VPS instances.</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($instances as $instance)
                @php
                    $order     = $instance->order;
                    $pack      = $order->packPrice->pack;
                    $isPending = $instance->isAwaitingInstall();
                    $isRunning = $instance->isRunning();
                    $isStopped = $instance->isStopped();
                @endphp

                <div class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                    {{-- Card header --}}
                    <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                            <x-filament::icon
                                icon="tabler-server"
                                class="h-5 w-5 text-primary-600 dark:text-primary-400"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $pack->name }}
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $instance->vm_ip ?? 'IP not assigned' }}
                            </p>
                        </div>

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

                    {{-- Specs + expiry --}}
                    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-3">
                        @php $price = $order->packPrice; @endphp
                        @if($price->cores)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $price->cores }}</span> vCPU
                            </span>
                        @endif
                        @if($price->memory)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($price->memory / 1024, 1) }}</span> GB RAM
                            </span>
                        @endif
                        @if($price->disk)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $price->disk }}</span> GB Disk
                            </span>
                        @endif
                        @if($order->expires_at)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Expires {{ $order->expires_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-gray-100 px-4 py-3 dark:border-white/10">
                        <a href="{{ \Fywolf\VcenterVps\Filament\App\Pages\VpsConsole::getUrl(['instance' => $instance->id]) }}"
                           class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm ring-1
                                  bg-primary-600 text-white ring-primary-600 hover:bg-primary-500
                                  dark:bg-primary-500 dark:ring-primary-500 dark:hover:bg-primary-400">
                            Manage
                        </a>

                        @if($isRunning)
                            <a href="{{ route('vcenter-vps.console', $instance->id) }}"
                               target="_blank" rel="noopener"
                               class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm ring-1
                                      bg-white text-gray-950 ring-gray-950/10 hover:bg-gray-50
                                      dark:bg-gray-800 dark:text-white dark:ring-white/20 dark:hover:bg-gray-700">
                                <x-filament::icon icon="tabler-terminal" class="h-3.5 w-3.5"/>
                                Console
                            </a>
                        @endif

                        @if($isPending)
                            <a href="{{ route('vcenter-vps.console', $instance->id) }}"
                               target="_blank" rel="noopener"
                               class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm ring-1
                                      bg-warning-500 text-white ring-warning-500 hover:bg-warning-400
                                      dark:bg-warning-500 dark:ring-warning-500 dark:hover:bg-warning-400">
                                <x-filament::icon icon="tabler-terminal" class="h-3.5 w-3.5"/>
                                Install Console
                            </a>
                        @endif
                    </div>

                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
