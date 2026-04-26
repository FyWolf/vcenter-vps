<x-filament-panels::page>
    @php
        $instance  = $this->instance;
        $isPending = $instance->isAwaitingInstall();
        $uploadLib = config('vcenter-vps.upload_library_id');
    @endphp

    <div class="space-y-6">

        @if($isPending)
            <x-filament::section>
                <x-slot name="heading">Complete OS Installation</x-slot>
                <x-slot name="description">Your VPS is ready for OS installation. Open the console to proceed, then mark it as complete when done.</x-slot>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('vcenter-vps.console', $instance->id) }}"
                       target="_blank" rel="noopener"
                       class="fi-btn fi-btn-size-md inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1
                              bg-primary-600 text-white ring-primary-600 hover:bg-primary-500
                              dark:bg-primary-500 dark:ring-primary-500 dark:hover:bg-primary-400">
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
            </x-filament::section>
        @endif

        @if($uploadLib)
            <x-filament::section>
                <x-slot name="heading">Attach ISO from URL</x-slot>
                <x-slot name="description">Enter a direct download URL for an ISO file. It will be downloaded to the content library and attached to your VPS.</x-slot>

                <div class="flex gap-3">
                    <input wire:model="isoUrl"
                           type="url"
                           placeholder="https://example.com/debian-12-netinst.iso"
                           class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                  placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                                  dark:border-white/20 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    <x-filament::button wire:click="swapIsoFromUrl" color="gray">
                        Attach
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Upload ISO File</x-slot>
                <x-slot name="description">Upload an ISO directly from your computer. Maximum size: 2 GB. The upload will be queued and attached once complete.</x-slot>

                <div class="flex items-center gap-3">
                    <input wire:model="isoFile"
                           type="file"
                           accept=".iso"
                           class="block w-full text-sm text-gray-700 dark:text-gray-300
                                  file:mr-3 file:rounded-lg file:border-0
                                  file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700
                                  dark:file:bg-gray-700 dark:file:text-gray-200">
                    <x-filament::button wire:click="swapIsoFromFile" color="gray">
                        Upload
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">ISO Management</x-slot>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    ISO uploads are not available. No upload library has been configured by your administrator.
                </p>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
