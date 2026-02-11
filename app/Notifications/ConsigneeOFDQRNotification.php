<?php

namespace App\Notifications;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Consignee;
use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ConsigneeOFDQRNotification extends Notification
{
    use Queueable;

    protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;

        // Allow Laravel to let the database auto-increment the primary key instead of using UUID strings
        $this->id = null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Send via WhatsApp only for QR code
        $channles = [
            'database',
            'mail',
            'whatsapp'
        ];

        return $channles;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $shipment = $this->shipment;

        $title = "QR Code for Shipment #{$shipment->tracking_no}";

        $data = [
            'type' => 'ofd_qr_notification',
            'title' => $title,
            'body' => "QR Code for shipment {$shipment->tracking_no} delivery confirmation.",
            'notifiable_id' => $shipment->consignee_id,
            'notifiable_type' => 'App\Models\Consignee',
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->tracking_no,
                'consignee_id' => $shipment->consignee_id,
                'consignee_name' => $shipment->consignee->name,
                'consignee_cellphone' => $shipment->consignee->cellphone,
                'consignee_alternate_phone' => $shipment->consignee->alternatePhone,
                'qr_url' => $this->toWhatsapp($notifiable)['image'] ?? null,
            ]
        ];

        return $data;
    }

    /**
     * Build the WhatsApp message text for QR code only.
     */
    public function toWhatsapp($notifiable)
    {
        $shipment = $this->shipment;

        $base_url = request()->getScheme() . '://' . request()->getHttpHost();
        $qr_url = $base_url . '/driver/shipments/confirm_otp/' . $shipment->tracking_no . '/' . $shipment->shipment_delivery->delivery_otp . '/' . $shipment->driver_id;

        // Generate QR code image
        $qrImageUrl = $this->generateQRCode($shipment, $qr_url);

        // Simple message for QR code notification
        $message = "QR Code for shipment {$shipment->tracking_no} delivery confirmation. Please show this QR code to the delivery agent.";

        $recipients = array_filter([
            $shipment->consignee->cellphone,
            $shipment->consignee->alternatePhone,
        ]);
        $data = [
            'message' => $message,
            'recipients' => $recipients,
            'image' => $qrImageUrl,
        ];

        info("qr CODe Recipients", ["" => $data]);
        return $data;
    }

    /**
     * Generate QR code and return image URL
     */
    private function generateQRCode($shipment, $qr_url): ?string
    {
        $base_url = request()->getScheme() . '://' . request()->getHttpHost();

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
            $fileName = 'qr_' . $shipment->tracking_no . '_' . time() . '.png';
            $filePath = 'qrcodes/' . $fileName;

            // Ensure qrcodes directory exists
            if (!Storage::disk('public')->exists('qrcodes')) {
                Storage::disk('public')->makeDirectory('qrcodes');
                Log::info('Created qrcodes directory');
            }

            // Get the PNG data
            $pngData = $result->getString();
            Log::info('QR Code generation details', [
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
                Log::info('QR Code stored successfully', ['path' => $filePath]);
            } else {
                Log::error('Failed to store QR Code', ['path' => $filePath]);
            }

            // Use the proper storage URL
            $qrImageUrl = asset('storage/' . $filePath);

            // Verify file exists and URL is accessible
            if (Storage::disk('public')->exists($filePath)) {
                Log::info('QR Code file verified to exist', [
                    'url' => $qrImageUrl,
                    'file_path' => $filePath,
                    'full_storage_path' => Storage::disk('public')->path($filePath)
                ]);

                // Test if the URL is accessible
                $headers = @get_headers($qrImageUrl);
                if ($headers && strpos($headers[0], '200') !== false) {
                    Log::info('QR Code URL is accessible', ['url' => $qrImageUrl]);
                } else {
                    Log::warning('QR Code URL might not be accessible', [
                        'url' => $qrImageUrl,
                        'headers' => $headers
                    ]);
                }
            } else {
                Log::error('QR Code file does not exist after storage attempt', ['path' => $filePath]);
            }

            return $qrImageUrl;
        } catch (\Exception $e) {
            Log::error('QR Code generation/storage failed', [
                'error' => $e->getMessage(),
                'tracking_no' => $shipment->tracking_no,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
