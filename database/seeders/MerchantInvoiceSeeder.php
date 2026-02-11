<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Invoice;
use App\Models\InvoiceShipment;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MerchantInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing merchants
        $merchants = User::whereHas('merchant')->take(5)->get();
        
        if ($merchants->isEmpty()) {
            $this->command->warn('No merchants found. Please run MerchantSeeder first.');
            return;
        }

        foreach ($merchants as $merchant) {
            // Create 3-5 invoices per merchant
            $invoiceCount = rand(3, 5);
            
            for ($i = 0; $i < $invoiceCount; $i++) {
                $invoice = Invoice::create([
                    // 'owner_type' => Merchant::class,
                    // 'owner_id' => $merchant->id,
                    'invoiceable_type' => User::class,
                    'invoiceable_id' => $merchant->id,
                    'driver_runsheet_id' => 1, // Placeholder
                    'invoice_no' => 'INV-' . str_pad($merchant->id, 3, '0', STR_PAD_LEFT) . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'status' => $this->getRandomStatus(),
                    'amount' => round(rand(50, 500) + (rand(0, 99) / 100), 2),
                    'payment_voucher' => null,
                    'driver_payment_proof' => null,
                    'paid_to_driver' => null,
                    'notes' => $this->getRandomNotes(),
                    'created_at' => Carbon::now()->subDays(rand(1, 90)),
                    'updated_at' => Carbon::now()->subDays(rand(1, 30)),
                ]);

                // Create 2-4 invoice shipments per invoice
                $shipmentCount = rand(2, 4);

                $shipmentService = new ShipmentService();
                
                for ($j = 0; $j < $shipmentCount; $j++) {
                    $shipment = $shipmentService->create_shipment();
                    InvoiceShipment::create([
                        'invoice_id' => $invoice->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'status' => $this->getRandomShipmentStatus(),
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                    ]);
                }

                
            }
        }
    }

    private function getRandomStatus(): string
    {
        $statuses = ['pending', 'paid', 'overdue', 'cancelled'];
        return $statuses[array_rand($statuses)];
    }

    private function getRandomShipmentStatus(): string
    {
        $statuses = ['pending', 'delivered', 'returned', 'cancelled'];
        return $statuses[array_rand($statuses)];
    }

    private function getRandomNotes(): ?string
    {
        $notes = [
            'Monthly delivery charges for shipments',
            'COD collection and delivery fees',
            'Standard shipping charges applied',
            'Additional charges for remote area delivery',
            'Bulk shipment discount applied',
            null
        ];
        return $notes[array_rand($notes)];
    }
}
