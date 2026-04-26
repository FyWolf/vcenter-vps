<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use BackedEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Pages\Page;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Filament\Vps\Pages\VpsConsole;
use Fywolf\VcenterVps\Models\VpsInstance;

class MyVps extends Page implements HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected string $view = 'vcenter-vps::my-vps';

    protected static ?string $slug = 'my-vps';

    protected static ?string $navigationLabel = 'My VPS';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-server';

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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getInstanceQuery())
            ->columns([
                ViewColumn::make('vps_card')
                    ->view('vcenter-vps::columns.vps-card')
                    ->searchable(['name']),
            ])
            ->recordUrl(fn (VpsInstance $r) => VpsConsole::getUrl(['vpsId' => $r->id], panel: 'vps'))
            ->contentGrid(['default' => 1, 'md' => 2])
            ->paginated([10, 20, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('tabler-server')
            ->emptyStateHeading('No VPS instances')
            ->emptyStateDescription('You don\'t have any active VPS instances yet.');
    }

    private function getInstanceQuery()
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return VpsInstance::query()->whereRaw('0 = 1');
        }

        return VpsInstance::query()
            ->whereHas('order', fn ($q) => $q
                ->where('customer_id', $customer->id)
                ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
            )
            ->with(['order.packPrice.pack']);
    }
}
