<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ConsigneePickupNotification extends Notification
{
    use Queueable;

    protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $shipment = $this->shipment;
        $consignee = $shipment->consignee;
        $otp = $shipment->shipment_delivery->delivery_otp ?? 'N/A';
        $formattedAmount = number_format($shipment->amount, 2);

        $merchantName = optional($shipment->merchant)->name;
        $shipperName = optional($shipment->shipper)->name;

        $senderInfo = [];
        if ($merchantName && $shipperName) {
            $senderInfo[] = "<strong>Merchant:</strong> $merchantName";
            $senderInfo[] = "<strong>Shipper:</strong> $shipperName";
        } elseif ($merchantName) {
            $senderInfo[] = "<strong>Merchant:</strong> $merchantName";
        } elseif ($shipperName) {
            $senderInfo[] = "<strong>Shipper:</strong> $shipperName";
        }

        $tableRows = [];
        if (!empty($senderInfo)) {
            $tableRows[] = "<tr><td style='padding: 5px;'>" . implode('</td></tr><tr><td style="padding: 5px;">', $senderInfo) . "</td></tr>";
        }
        $tableRows[] = "<tr><td style='padding: 5px;'><strong>Amount to Collect:</strong></td><td style='padding: 5px;'>{$formattedAmount}</td></tr>";

        $tableHtml = "<table style='width: 100%'>" . implode('', $tableRows) . "</table>";

        return (new MailMessage)
            ->subject("Your Shipment #{$shipment->tracking_no} has been Picked Up!")
            ->greeting("Hello {$consignee->name},")
            ->line("Great news! Your shipment with tracking number **{$shipment->tracking_no}** has been successfully picked up from the sender and is now in transit to you.")
            ->line("**Delivery OTP:** {$otp}")
            ->line("Please keep this OTP ready - you'll need to provide it to our delivery agent when your package arrives.")
            ->line(new HtmlString("<hr>"))
            ->line(new HtmlString($tableHtml))
            ->action('Track Your Shipment', url('/tracking/' . $shipment->tracking_no))
            ->line('We will notify you again when your package is out for delivery. Thank you for choosing our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    /**
     * Build the WhatsApp message text.
     */
    public function toWhatsapp($notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'PICKUP_CONFIRMATION')->first();
        $merchantName = optional($shipment->merchant)->name;
        $shipperName = optional($shipment->shipper)->name;
        
        if ($merchantName && $shipperName) {
            $senderInfo = "Merchant: $merchantName, Shipper: $shipperName";
        } elseif ($merchantName) {
            $senderInfo = "Merchant: $merchantName";
        } elseif ($shipperName) {
            $senderInfo = "Shipper: $shipperName";
        } else {
            $senderInfo = 'N/A';
        }

        $base_url = request()->getScheme() . '://' . request()->getHttpHost();
        $qr_url = $base_url . '/driver/shipments/confirm_otp/' . $shipment->tracking_no . '/' . $shipment->shipment_delivery->delivery_otp . '/' . $shipment->driver_id;
        
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $qr_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        try {
            $result = $builder->build();
            $fileName = 'qr_pickup_' . $shipment->tracking_no . '_' . time() . '.png';
            $filePath = 'qrcodes/' . $fileName;
            
            // Ensure qrcodes directory exists
            if (!Storage::disk('public')->exists('qrcodes')) {
                Storage::disk('public')->makeDirectory('qrcodes');
                Log::info('Created qrcodes directory');
            }
            
            // Get the PNG data
            $pngData = $result->getString();
            Log::info('Pickup QR Code generation details', [
                'tracking_no' => $shipment->tracking_no,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'data_size' => strlen($pngData),
                'qr_url' => $qr_url,
                'storage_path' => Storage::disk('public')->path($filePath)
            ]);
            
            // Store the file
            $stored = Storage::disk('public')->put($filePath, $pngData);
            
            if ($stored) {
                Log::info('Pickup QR Code stored successfully', ['path' => $filePath]);
            } else {
                Log::error('Failed to store Pickup QR Code', ['path' => $filePath]);
            }
            
            $qrImageUrl = $base_url . '/storage/' . $filePath;
            
            // Verify file exists
            if (Storage::disk('public')->exists($filePath)) {
                Log::info('Pickup QR Code file verified to exist', ['url' => $qrImageUrl]);
            } else {
                Log::error('Pickup QR Code file does not exist after storage attempt', ['path' => $filePath]);
            }
            
        } catch (\Exception $e) {
            Log::error('Pickup QR Code generation/storage failed', [
                'error' => $e->getMessage(),
                'tracking_no' => $shipment->tracking_no,
                'trace' => $e->getTraceAsString()
            ]);
            $qrImageUrl = null;
        }

        if ($template) {
            $message = str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{otp}}', '{{merchant_name}}', '{{cod}}', '{{qr_url}}', '{{address_update_url}}'],
                [
                    $shipment->consignee->name,
                    $shipment->tracking_no,
                    $shipment->shipment_delivery->delivery_otp,
                    $senderInfo,
                    $shipment->amount,
                    "![Scan QR](" . $qrImageUrl . ")",
                    $shipment->consignee->address_update_url
                ],
                $template->message
            );
        } else {
            $message = "Your shipment {$shipment->tracking_no} has been picked up successfully and is now in transit to you.";
        }

        $recipients = array_filter([
            $shipment->consignee->cellphone,
            $shipment->consignee->alternatePhone,
        ]);

        return [
            'message' => $message,
            'recipients' => $recipients,
            'image' => $qrImageUrl,
        ];
    }
}
