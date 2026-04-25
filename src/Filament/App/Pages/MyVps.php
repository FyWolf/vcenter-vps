<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MyVps extends Page
{
    protected string $view = 'vcenter-vps::my-vps';

    protected static ?string $slug = 'my-vps';

    protected static ?string $navigationLabel = 'My VPS';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-server';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return false;
        }

        return VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )->exists();
    }

    public function getInstances(): Collection
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return collect();
        }

        return VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )
            ->with(['order.packPrice.pack', 'order.customer'])
            ->get();
    }
}
