<x-filament-panels::page>
    @php $instances = $this->getInstances(); @endphp

    @if($instances->isEmpty())
        <div class="fi-section rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div style="padding: 2rem 1.5rem; text-align: center;">
                <p class="text-sm text-gray-500 dark:text-gray-400">You have no active VPS instances.</p>
            </div>
        </div>
    @else
        <div class="fi-section rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div style="padding: 1.25rem 1.5rem 0.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600;" class="text-gray-950 dark:text-white">
                    My VPS
                </h3>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; padding: 0 1.5rem 1.5rem;">
                @foreach($instances as $instance)
                    @php
                        $order     = $instance->order;
                        $pack      = $order->packPrice->pack;
                        $isPending = $instance->isAwaitingInstall();
                        $isRunning = $instance->isRunning();
                    @endphp

                    <a href="{{ \Fywolf\VcenterVps\Filament\App\Pages\VpsConsole::getUrl(['vpsId' => $instance->id]) }}"
                       class="fi-section rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10"
                       style="display: block; padding: 1rem; text-decoration: none; transition: background 150ms;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">

                            <div style="width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem; flex-shrink: 0;"
                                 class="bg-primary-50 dark:bg-primary-500/10">
                                <svg style="width: 1.25rem; height: 1.25rem;" class="text-primary-600 dark:text-primary-400"
                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/>
                                    <line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>
                                </svg>
                            </div>

                            <div style="min-width: 0; flex: 1;">
                                <div style="font-size: 0.875rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                     class="text-gray-950 dark:text-white">
                                    {{ $pack->name }}
                                </div>
                                <div style="font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem;"
                                     class="text-gray-500 dark:text-gray-400">
                                    {{ $instance->vm_ip ?? 'IP not assigned' }}
                                    &middot;
                                    @if($isPending)
                                        <span class="text-warning-600 dark:text-warning-400">Installing</span>
                                    @elseif($isRunning)
                                        <span class="text-success-600 dark:text-success-400">Running</span>
                                    @else
                                        <span class="text-danger-600 dark:text-danger-400">Stopped</span>
                                    @endif
                                </div>
                            </div>

                            <svg style="width: 1rem; height: 1rem; flex-shrink: 0;" class="text-gray-400 dark:text-gray-500"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                            </svg>

                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
