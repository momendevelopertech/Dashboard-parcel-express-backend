<?php

use App\Models\Shipment;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\ShelfController;
use App\Models\MerchantWaybill;
use App\Models\PickuptaskTransaction;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;


if (App::environment('local')) {
Route::get('routes', function () {
$routeCollection = Route::getRoutes();

echo "<table style='width:100%; border: 1px solid black; border-collapse:
collapse;'>";
echo "<tr>";
echo "<th style='border: 1px solid black;'>HTTP
Method</th>";
echo "<th style='border: 1px solid black;'>Route</th>";
echo "<th style='border: 1px solid black;'>Name</th>";
echo "<th style='border: 1px solid black;'>Corresponding
Action</th>";
echo "</tr>";

foreach ($routeCollection as $value) {
echo "<tr>";
echo "<td style='border: 1px solid black;'>" .
$value->methods()[0] . "</td>";
echo "<td style='border: 1px solid black;'>" . $value->uri()
. "</td>";
echo "<td style='border: 1px solid black;'>" .
($value->getName() ?? 'N/A') . "</td>";
echo "<td style='border: 1px solid black;'>" .
$value->getActionName() . "</td>";
echo "</tr>";
}

echo "</table>";
});
}

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

Route::get('/pdf', function () {

    /* -----------------------------
     | Fake Merchant Data
     |-----------------------------*/
    $merchant_name = 'Test Merchant LLC';
    $merchant_address = 'Muscat, Oman';
    $merchant_contact = '+968 9000 0000';



    /* -----------------------------
     | Fake Pickup Transactions
     |-----------------------------*/
     $userId=4;
    $pickup_tasks = PickuptaskTransaction::with('pickuptask')
        ->whereHas('pickuptask', function ($query) use ($userId) {
            $query->where('merchant_id', $userId);
        })
        ->where('amount', '>', 0)
        ->get();

        $shipments=Shipment::where('merchant_id',$userId)->where('status','delivered')->get();

    /* -----------------------------
     | Other Invoice Data
     |-----------------------------*/
    return view('pdf.merchant-invoice', [
        'merchant_name'    => $merchant_name,
        'merchant_address' => $merchant_address,
        'merchant_contact' => $merchant_contact,
        'reference'        => 'INV-TEST-001',
        'date'             => now()->format('Y-m-d'),
        'amount'           => $pickup_tasks->sum('amount'),
        'notes'            => 'This is a test invoice for design preview only.',
        'shipments'        => $shipments,
        'pickup_tasks'     => $pickup_tasks,
    ]);
});


Route::get('/printShipment', function () {
    $shipment = Shipment::with(
        "consignee.city",
        "shipper.country",
        "shipper.city",
        "shipment_information",
        "shipment_amounts"
    )
        ->where('id', operator: 2)
        ->firstOrFail();
    return view('printShipment', compact('shipment'));
});


Route::get('/waybill', function () {
    $waybill = MerchantWaybill::with(
        "merchant.merchant.country",
        "merchant.merchant.governorate",
        "merchant.merchant.state",
        "merchant.merchant.place",
        "merchant.owner",
    )->findOrFail(1);
    return view('printWaybill', compact('waybill'));
});

Route::get('/shelf/print/{id}', [ShelfController::class, 'printShelf'])->name('shelf.print');

// Broadcasting authentication route is now defined in routes/api.php with the
// correct /api prefix. Keeping it here would duplicate the same endpoint and
// might lead to unexpected behaviour, so it has been commented out.
// Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);


// Debug route to test authentication


Route::get('/test-fcm-send', function () {
    $path = storage_path('app/firebase/parcel-express-driver-ap-9689f-firebase-adminsdk-fbsvc-44a.json');
    $messaging = (new Factory)->withServiceAccount($path)->createMessaging();

    $topic = 'driver_1'; 
    $message = CloudMessage::fromArray([
        'topic' => $topic,
        'notification' => [
            'title' => '📦 إشعار تجريبي',
            'body' => 'لو وصلك ده على الموبايل يبقى كل حاجة تمام!',
        ],
        'data' => [
            'type' => 'test',
        ],
    ]);

    $messaging->send($message);
    return '✅ تم إرسال إشعار إلى الـ topic: ' . $topic;
});

Route::get('/test-groq', function () {
    try {
        $apiKey = config('services.groq.key');
        $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
        
        if (empty($apiKey)) {
            return response()->json(['error' => 'API Key is empty']);
        }
        
        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post($apiUrl, [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => 'مرحبا، قل لي: "أنا أعمل بشكل صحيح"']
                ],
                'max_tokens' => 100,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return response()->json([
                'success' => true,
                'message' => $data['choices'][0]['message']['content'] ?? 'No content',
                'full_response' => $data
            ]);
        } else {
            return response()->json([
                'error' => 'API Error',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Exception',
            'message' => $e->getMessage()
        ]);
    }
});