@php
    /** @var \Fywolf\VcenterVps\Models\VpsInstance $instance */
    $instance  = $getRecord();
    $order     = $instance->order;
    $price     = $order?->packPrice;
    $pack      = $price?->pack;

    $isPending = $instance->isAwaitingInstall();
    $isRunning = $instance->isRunning();
    $isStopped = $instance->isStopped();

    [$statusIcon, $statusColor, $statusLabel, $stripeColor] = match (true) {
        $isPending => ['tabler-clock-hour-4', 'warning', 'Installing', '#F59E0B'],
        $isRunning => ['tabler-circle-check', 'success', 'Running',    '#10B981'],
        $isStopped => ['tabler-circle-x',     'danger',  'Stopped',    '#EF4444'],
        default    => ['tabler-question-mark','gray',    'Unknown',    '#6B7280'],
    };

    $displayName = $instance->name ?? $pack?->name ?? 'VPS';
@endphp

<div class="relative cursor-pointer">
    <div class="absolute left-0 top-1 bottom-0 w-1 rounded-lg" style="background-color: {{ $stripeColor }};"></div>

    <div class="flex-1 dark:bg-gray-800 dark:text-white rounded-lg overflow-hidden p-3">
        <div class="flex items-center gap-2 mb-5">
            <x-filament::icon-button
                :icon="$statusIcon"
                :color="$statusColor"
                :tooltip="$statusLabel"
                size="lg"
            />
            <h2 class="text-xl font-bold">
                {{ $displayName }}
                <span class="dark:text-gray-400">
                    ({{ $statusLabel }})
                </span>
            </h2>
        </div>

        <div class="flex justify-between text-center items-center gap-4">
            <div class="hidden sm:block w-full max-w-xs">
                <p class="text-sm dark:text-gray-400">CPU</p>
                <p class="text-md font-semibold">
                    {{ $price?->cores ? $price->cores . ' cores' : '—' }}
                </p>
            </div>

            <div class="hidden sm:block w-full max-w-xs">
                <p class="text-sm dark:text-gray-400">Memory</p>
                <p class="text-md font-semibold">
                    {{ $price?->memory ? number_format($price->memory / 1024, 1) . ' GB' : '—' }}
                </p>
            </div>

            <div class="hidden sm:block w-full max-w-xs">
                <p class="text-sm dark:text-gray-400">Disk</p>
                <p class="text-md font-semibold">
                    {{ $price?->disk ? $price->disk . ' GB' : '—' }}
                </p>
            </div>

            <div class="hidden sm:block">
                <p class="text-sm dark:text-gray-400">Network</p>
                <p class="text-md font-semibold">{{ $instance->vm_ip ?? 'None' }}</p>
            </div>
        </div>
    </div>
</div>
