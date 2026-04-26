<?php

namespace Fywolf\VcenterVps\Filament\App\Concerns;

use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;

trait HasVpsNavigation
{
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public VpsInstance $instance;

    protected function mountVps(int $vpsId): void
    {
        $customer = Customer::where('user_id', auth()->id())->firstOrFail();

        $this->instance = VpsInstance::whereHas('order', fn ($q) => $q
            ->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )
            ->with(['order.packPrice.pack'])
            ->findOrFail($vpsId);
    }

    public function getTitle(): string
    {
        return $this->instance->name ?? ($this->instance->order->packPrice->pack->name ?? 'VPS');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubNavigation(): array
    {
        $id = $this->instance->id;

        return [
            NavigationItem::make('Overview')
                ->icon('tabler-server')
                ->url(route('filament.app.pages.vps-console', ['vpsId' => $id]))
                ->isActiveWhen(fn () => request()->routeIs('filament.app.pages.vps-console')),
            NavigationItem::make('ISO')
                ->icon('tabler-disc')
                ->url(route('filament.app.pages.vps-iso', ['vpsId' => $id]))
                ->isActiveWhen(fn () => request()->routeIs('filament.app.pages.vps-iso')),
            NavigationItem::make('Settings')
                ->icon('tabler-settings')
                ->url(route('filament.app.pages.vps-settings', ['vpsId' => $id]))
                ->isActiveWhen(fn () => request()->routeIs('filament.app.pages.vps-settings')),
        ];
    }
}
