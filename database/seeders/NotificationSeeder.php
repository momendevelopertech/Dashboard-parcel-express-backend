<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Notification;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the merchant created in MerchantSeeder
        $merchant = Merchant::first();

        if (!$merchant) {
            $this->command->error('No merchant found. Please run MerchantSeeder first.');
            return;
        }

        $notifications = [
            [
                "id" => uniqid(),
                'notifiable_id' => $merchant->user_id,
                'notifiable_type' => 'App\Models\User',
                'title' => 'Welcome to Parcel Express!',
                'content' => 'Your account has been successfully created. You can now start sending and tracking parcels with ease.',
                'data' => json_encode([
                    'title' => 'Welcome to Parcel Express!',
                    'content' => 'Your account has been successfully created. You can now start sending and tracking parcels with ease.',
                ]),
                'type' => 'App\Models\User',
                'read_at' => Carbon::now()->subDays(5),
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(5),
            ],
            [
                "id" => uniqid(),
                'notifiable_id' => $merchant->user_id,
                'notifiable_type' => 'App\Models\User',
                'title' => 'Shipment #PE001234 Picked Up',
                'content' => 'Your parcel has been picked up from the sender and is now in transit to the destination.',
                'data' => json_encode([
                    'title' => 'Shipment #PE001234 Picked Up',
                    'content' => 'Your parcel has been picked up from the sender and is now in transit to the destination.',
                ]),
                'type' => 'App\Models\User',
                'read_at' => Carbon::now()->subDays(3),
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subDays(3),
            ],
            [
                "id" => uniqid(),
                'notifiable_id' => $merchant->user_id,
                'notifiable_type' => 'App\Models\User',
                'title' => 'Shipment #PE001234 Out for Delivery',
                'content' => 'Great news! Your parcel is out for delivery and will be delivered today between 9 AM - 6 PM.',
                'data' => json_encode([
                    'title' => 'Shipment #PE001234 Out for Delivery',
                    'content' => 'Great news! Your parcel is out for delivery and will be delivered today between 9 AM - 6 PM.',
                ]),
                'type' => 'App\Models\User',
                'read_at' => Carbon::now()->subDays(2),
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            [
                "id" => uniqid(),
                'notifiable_id' => $merchant->user_id,
                'notifiable_type' => 'App\Models\User',
                'title' => 'Shipment #PE001234 Delivered Successfully',
                'content' => 'Your parcel has been delivered successfully to the recipient. Thank you for choosing Parcel Express!',
                'data' => json_encode([
                    'title' => 'Shipment #PE001234 Delivered Successfully',
                    'content' => 'Your parcel has been delivered successfully to the recipient. Thank you for choosing Parcel Express!',
                ]),
                'type' => 'App\Models\User',
                'read_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                "id" => uniqid(),
                'notifiable_id' => $merchant->user_id,
                'notifiable_type' => 'App\Models\User',
                'title' => 'Payment Received - Invoice #INV-2024-001',
                'content' => 'We have received your payment of 45.50 " . getCurrency("en") . " for invoice #INV-2024-001. Receipt has been sent to your email.',
                'data' => json_encode([
                    'title' => 'Payment Received - Invoice #INV-2024-001',
                    'content' => 'We have received your payment of 45.50 " . getCurrency("en") . " for invoice #INV-2024-001. Receipt has been sent to your email.',
                ]),
                'type' => 'App\Models\User',
                'read_at' => Carbon::now()->subHours(18),
                'created_at' => Carbon::now()->subHours(18),
                'updated_at' => Carbon::now()->subHours(18),
            ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'New Rate Card Available',
            //         'content' => 'Updated shipping rates are now available. Check our new competitive prices for international deliveries.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subHours(12),
            //     'updated_at' => Carbon::now()->subHours(12),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Profile Updated Successfully',
            //         'content' => 'Your profile information has been updated successfully. If this wasn\'t you, please contact support immediately.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subHours(8),
            //     'updated_at' => Carbon::now()->subHours(8),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Shipment #PE001235 Delayed',
            //         'content' => 'Unfortunately, your shipment #PE001235 has been delayed due to weather conditions. Expected delivery: Tomorrow.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subHours(4),
            //     'updated_at' => Carbon::now()->subHours(4),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Security Alert: New Login Detected',
            //         'content' => 'A new login was detected from Windows PC at ' . Carbon::now()->subHours(2)->format('M d, Y H:i') . '. If this wasn\'t you, please secure your account.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subHours(2),
            //     'updated_at' => Carbon::now()->subHours(2),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Special Offer: 20% Off Next Shipment',
            //         'content' => 'Enjoy 20% off your next shipment! Use code SAVE20 at checkout. Valid until end of this month.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subHour(),
            //     'updated_at' => Carbon::now()->subHour(),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Shipment #PE001236 Pickup Scheduled',
            //         'content' => 'Your pickup has been scheduled for tomorrow between 10 AM - 2 PM. Please ensure someone is available at the pickup location.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subMinutes(30),
            //     'updated_at' => Carbon::now()->subMinutes(30),
            // ],
            // [
            //     'notifiable_id' => $merchant->user_id,
            //     'notifiable_type' => 'App\Models\User',
            //     'data' => [
            //         'title' => 'Monthly Statement Available',
            //         'content' => 'Your monthly statement for ' . Carbon::now()->subMonth()->format('F Y') . ' is now available in your account dashboard.',
            //     ],
            //     'type' => 'App\Models\User',
            //     'read_at' => null,
            //     'created_at' => Carbon::now()->subMinutes(15),
            //     'updated_at' => Carbon::now()->subMinutes(15),
            // ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }

        $this->command->info('Created ' . count($notifications) . ' test notifications for merchant: ' . $merchant->user->name);
    }
}
