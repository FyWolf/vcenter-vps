<x-filament-panels::page>
    @php $instances = $this->getInstances(); @endphp

    @if($instances->isEmpty())
        <div class="py-12 flex flex-col items-center justify-center gap-4 text-center">
            <p class="text-base font-semibold text-gray-950 dark:text-white">You have no active VPS instances.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach($instances as $instance)
                @php
                    $order     = $instance->order;
                    $price     = $order->packPrice;
                    $pack      = $price->pack;
                    $isPending = $instance->isAwaitingInstall();
                    $isRunning = $instance->isRunning();

                    if ($isPending) {
                        $statusColor = 'warning';
                        $statusIcon  = 'tabler-clock-hour-4';
                        $statusLabel = 'Installing';
                        $borderClass = 'bg-warning-500';
                    } elseif ($isRunning) {
                        $statusColor = 'success';
                        $statusIcon  = 'tabler-circle-check';
                        $statusLabel = 'Running';
                        $borderClass = 'bg-success-500';
                    } else {
                        $statusColor = 'danger';
                        $statusIcon  = 'tabler-circle-x';
                        $statusLabel = 'Stopped';
                        $borderClass = 'bg-danger-500';
                    }
                @endphp

                <div class="relative cursor-pointer"
                     x-on:click="window.location = '{{ \Fywolf\VcenterVps\Filament\Vps\Pages\VpsConsole::getUrl(panel: 'vps', tenant: $instance) }}'">

                    <div class="absolute left-0 top-1 bottom-0 w-1 rounded-lg {{ $borderClass }}"></div>

                    <div class="flex-1 rounded-lg overflow-hidden p-3 bg-white dark:bg-gray-800 dark:text-white">

                        <div class="flex items-center gap-2 mb-3">
                            <x-filament::icon-button
                                :icon="$statusIcon"
                                :color="$statusColor"
                                :tooltip="$statusLabel"
                                size="lg"
                            />
                            <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                                {{ $instance->name ?? $pack->name }}
                            </h2>
                        </div>

                        <div class="flex justify-between text-center items-center gap-4">
                            @if($price->cores)
                                <div>
                                    <p class="text-sm dark:text-gray-400">CPU</p>
                                    <p class="text-md font-semibold">{{ $price->cores }} cores</p>
                                </div>
                            @endif
                            @if($price->memory)
                                <div>
                                    <p class="text-sm dark:text-gray-400">RAM</p>
                                    <p class="text-md font-semibold">{{ number_format($price->memory / 1024, 1) }} GB</p>
                                </div>
                            @endif
                            @if($price->disk)
                                <div>
                                    <p class="text-sm dark:text-gray-400">Disk</p>
                                    <p class="text-md font-semibold">{{ $price->disk }} GB</p>
                                </div>
                            @endif
                            <div class="hidden sm:block">
                                <p class="text-sm dark:text-gray-400">IP Address</p>
                                <p class="text-md font-semibold">{{ $instance->vm_ip ?? 'None' }}</p>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
