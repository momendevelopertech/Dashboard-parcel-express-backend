<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InventoryItem;
use App\Models\LowStockAlert;
use Illuminate\Support\Facades\Notification;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';
    protected $description = 'Generate alerts for items that fell below minimum stock';

    public function handle()
    {
        $items = InventoryItem::whereColumn('current_stock', '<', 'minimum_stock')->get();

        foreach ($items as $item) {
            if (!$item->alerts()->where('status', 'active')->exists()) {
                $alert = LowStockAlert::create([
                    'inventory_item_id' => $item->id,
                    'minimum_stock_level' => $item->minimum_stock,
                    'notification_methods' => ['email', 'in_system'],
                ]);

                Notification::route('mail', config('notifications.ops_email'))
                    ->notify(new \App\Notifications\StockLowNotification($alert));
            }
        }

        $this->info('Low stock check completed successfully');
        return 0;
    }
}
