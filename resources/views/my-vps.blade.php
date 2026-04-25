<x-filament-panels::page>
    @php
        $instances = $this->getInstances();
    @endphp

    @if($instances->isEmpty())
        <div class="fi-section rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900"
             style="padding: 2rem; text-align: center;">
            <p class="text-gray-500 dark:text-gray-400">You have no active VPS instances.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem;">
            @foreach($instances as $instance)
                @php
                    $order      = $instance->order;
                    $pack       = $order->packPrice->pack;
                    $isRunning  = $instance->state_cache === 'POWERED_ON';
                    $isStopped  = $instance->state_cache === 'POWERED_OFF';
                    $isPending  = $instance->isAwaitingInstall();
                    $isReady    = $instance->isReady();
                    $uploadLib  = config('vcenter-vps.upload_library_id');
                @endphp

                <div class="fi-section rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900"
                     style="padding: 1.25rem;">

                    {{-- Header --}}
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center;
                                    border-radius: 0.5rem; flex-shrink: 0;"
                             class="bg-primary-50 dark:bg-primary-500/10">
                            <svg style="width: 1.25rem; height: 1.25rem;" class="text-primary-600 dark:text-primary-400"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/>
                                <line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>
                            </svg>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 0.9rem;" class="text-gray-950 dark:text-white truncate">
                                {{ $pack->name }}
                            </div>
                            <div style="font-size: 0.75rem;" class="text-gray-500 dark:text-gray-400">
                                {{ $instance->vm_ip ?? 'IP not assigned' }}
                            </div>
                        </div>

                        {{-- Status badge --}}
                        @if($isPending)
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 9999px;"
                                  class="bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400">
                                Installing
                            </span>
                        @else
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 9999px;"
                                  @class([
                                      'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $isRunning,
                                      'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400'   => $isStopped,
                                      'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400'           => !$isRunning && !$isStopped,
                                  ])>
                                {{ $isRunning ? 'Running' : ($isStopped ? 'Stopped' : 'Unknown') }}
                            </span>
                        @endif
                    </div>

                    {{-- Specs --}}
                    @php $price = $instance->order->packPrice; @endphp
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        @if($price->cores)
                            <div style="font-size: 0.75rem;" class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium">{{ $price->cores }}</span> vCPU
                            </div>
                        @endif
                        @if($price->memory)
                            <div style="font-size: 0.75rem;" class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium">{{ number_format($price->memory / 1024, 1) }}</span> GB RAM
                            </div>
                        @endif
                        @if($price->disk)
                            <div style="font-size: 0.75rem;" class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium">{{ $price->disk }}</span> GB Disk
                            </div>
                        @endif
                    </div>

                    @if($order->expires_at)
                        <div style="font-size: 0.75rem; margin-bottom: 1rem;" class="text-gray-500 dark:text-gray-400">
                            Expires {{ $order->expires_at->diffForHumans() }}
                        </div>
                    @endif

                    {{-- ── INSTALLING STATE ── --}}
                    @if($isPending)
                        <div style="border-radius: 0.5rem; padding: 0.875rem; margin-bottom: 1rem;"
                             class="bg-warning-50 dark:bg-warning-500/10 ring-1 ring-warning-200 dark:ring-warning-500/20">
                            <p style="font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;"
                               class="text-warning-800 dark:text-warning-300">
                                OS installation in progress
                            </p>
                            <p style="font-size: 0.75rem;" class="text-warning-700 dark:text-warning-400">
                                Open the console to complete the installation. Click "Done" when finished.
                            </p>
                        </div>

                        {{-- Console for install --}}
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                            <button wire:click="openConsole({{ $instance->id }})"
                                    style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                    class="bg-primary-100 hover:bg-primary-200 text-primary-700 dark:bg-primary-500/20 dark:hover:bg-primary-500/30 dark:text-primary-400">
                                Open Console
                            </button>
                            <button wire:click="markInstallComplete({{ $instance->id }})"
                                    wire:confirm="Confirm the OS installation is complete?"
                                    style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                    class="bg-success-100 hover:bg-success-200 text-success-700 dark:bg-success-500/20 dark:hover:bg-success-500/30 dark:text-success-400">
                                Installation Done
                            </button>
                        </div>

                        {{-- ISO swap --}}
                        @if($uploadLib)
                            <details style="margin-top: 0.5rem;">
                                <summary style="font-size: 0.75rem; cursor: pointer; user-select: none;"
                                         class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                                    Use a different ISO
                                </summary>
                                <div style="margin-top: 0.75rem; padding: 0.75rem; border-radius: 0.5rem;"
                                     class="bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10">

                                    {{-- URL source --}}
                                    <p style="font-size: 0.7rem; font-weight: 600; margin-bottom: 0.4rem;"
                                       class="text-gray-600 dark:text-gray-300">From URL</p>
                                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;">
                                        <input wire:model="isoUrls.{{ $instance->id }}"
                                               type="url"
                                               placeholder="https://example.com/debian.iso"
                                               style="flex: 1; font-size: 0.75rem; padding: 0.35rem 0.5rem; border-radius: 0.375rem;"
                                               class="border border-gray-300 dark:border-white/20 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                        <button wire:click="swapIsoFromUrl({{ $instance->id }})"
                                                style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 0.375rem; cursor: pointer; white-space: nowrap; border: none;"
                                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-white/10 dark:hover:bg-white/20 dark:text-gray-200">
                                            Attach
                                        </button>
                                    </div>

                                    {{-- File upload --}}
                                    <p style="font-size: 0.7rem; font-weight: 600; margin-bottom: 0.4rem;"
                                       class="text-gray-600 dark:text-gray-300">Upload ISO (max 2 GB)</p>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input wire:model="isoFile"
                                               type="file"
                                               accept=".iso"
                                               style="flex: 1; font-size: 0.75rem;"
                                               class="text-gray-700 dark:text-gray-300">
                                        <button wire:click="swapIsoFromFile({{ $instance->id }})"
                                                style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 0.375rem; cursor: pointer; white-space: nowrap; border: none;"
                                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-white/10 dark:hover:bg-white/20 dark:text-gray-200">
                                            Upload
                                        </button>
                                    </div>
                                </div>
                            </details>
                        @endif

                    {{-- ── READY STATE ── --}}
                    @else
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            @if($isRunning)
                                <button wire:click="powerOff({{ $instance->id }})"
                                        wire:confirm="Stop your VPS?"
                                        style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                        class="bg-danger-100 hover:bg-danger-200 text-danger-700 dark:bg-danger-500/20 dark:hover:bg-danger-500/30 dark:text-danger-400">
                                    Stop
                                </button>
                                <button wire:click="reboot({{ $instance->id }})"
                                        wire:confirm="Restart your VPS?"
                                        style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                        class="bg-warning-100 hover:bg-warning-200 text-warning-700 dark:bg-warning-500/20 dark:hover:bg-warning-500/30 dark:text-warning-400">
                                    Restart
                                </button>
                                <button wire:click="openConsole({{ $instance->id }})"
                                        style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                        class="bg-primary-100 hover:bg-primary-200 text-primary-700 dark:bg-primary-500/20 dark:hover:bg-primary-500/30 dark:text-primary-400">
                                    Console
                                </button>
                            @else
                                <button wire:click="powerOn({{ $instance->id }})"
                                        wire:confirm="Start your VPS?"
                                        style="font-size: 0.75rem; padding: 0.4rem 0.75rem; border-radius: 0.375rem; cursor: pointer; border: none;"
                                        class="bg-success-100 hover:bg-success-200 text-success-700 dark:bg-success-500/20 dark:hover:bg-success-500/30 dark:text-success-400">
                                    Start
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('open-console', ({ url }) => {
                    window.open(url, '_blank', 'noopener');
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
