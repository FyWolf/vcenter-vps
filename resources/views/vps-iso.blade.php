<x-filament-panels::page>
    @php
        $instance  = $this->instance;
        $isPending = $instance->isAwaitingInstall();
        $uploadLib = config('vcenter-vps.upload_library_id');
    @endphp

    <div class="space-y-6">

        {{-- Install complete banner --}}
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
                        Open Console
                    </a>
                    <x-filament::button
                        wire:click="markInstallComplete"
                        wire:confirm="Confirm the OS installation is complete?"
                        color="success"
                        icon="tabler-circle-check"
                    >
                        Mark as Done
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Attach from library (existing ISOs on vCenter) --}}
        <x-filament::section>
            <x-slot name="heading">Attach from Library</x-slot>
            <x-slot name="description">Choose an ISO that already exists in the vCenter content library and attach it directly to your VPS.</x-slot>

            @if(!$uploadLib)
                <p class="text-sm text-gray-500 dark:text-gray-400">No content library configured. Contact your administrator.</p>
            @elseif(empty($this->availableIsos))
                <p class="text-sm text-gray-500 dark:text-gray-400">No ISOs found in the content library.</p>
                <div class="mt-3">
                    <x-filament::button wire:click="loadAvailableIsos" color="gray" size="sm">
                        Refresh
                    </x-filament::button>
                </div>
            @else
                <div class="flex gap-3">
                    <div class="flex-1">
                        <select wire:model="selectedLibraryItemId"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                       focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                                       dark:border-white/20 dark:bg-gray-800 dark:text-white">
                            <option value="">— Select an ISO —</option>
                            @foreach($this->availableIsos as $iso)
                                <option value="{{ $iso['id'] }}">{{ $iso['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedLibraryItemId')
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-filament::button wire:click="attachFromLibrary" color="primary">
                        Attach
                    </x-filament::button>
                </div>
                <div class="mt-2">
                    <x-filament::button wire:click="loadAvailableIsos" color="gray" size="sm">
                        Refresh list
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        {{-- Add new ISO via URL --}}
        @if($uploadLib)
            <x-filament::section>
                <x-slot name="heading">Download ISO from URL</x-slot>
                <x-slot name="description">Provide a direct download link. vCenter will download it into the content library and attach it automatically.</x-slot>

                <div class="flex gap-3">
                    <div class="flex-1">
                        <input wire:model="isoUrl"
                               type="url"
                               placeholder="https://example.com/debian-12-netinst.iso"
                               class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm
                                      placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                                      dark:border-white/20 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                        @error('isoUrl')
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-filament::button wire:click="swapIsoFromUrl" color="gray">
                        Download
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Upload ISO from your Computer</x-slot>
                <x-slot name="description">Upload an ISO directly. Maximum size: 2 GB. The file will be queued and attached once complete.</x-slot>

                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <input wire:model="isoFile"
                               type="file"
                               accept=".iso,.img"
                               class="block w-full text-sm text-gray-700 dark:text-gray-300
                                      file:mr-3 file:rounded-lg file:border-0
                                      file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700
                                      dark:file:bg-gray-700 dark:file:text-gray-200">
                        @error('isoFile')
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-filament::button wire:click="swapIsoFromFile" color="gray">
                        Upload
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
