<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DriverStop;
use App\Models\DriverStopList;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class DriverStopController extends Controller
{
    public function index(Request $request, int|string $driver_id, string $list_id)
    {

        $stops = DriverStop::where('driver_id', $driver_id)
            ->where('list_id', $list_id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('optimized'), fn($q) => $q->where('is_optimized', (bool) $request->boolean('optimized')))
            ->orderByRaw('CASE WHEN optimized_shipment IS NULL THEN 1 ELSE 0 END')
            ->orderBy('optimized_shipment')
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $stops]);
    }

    /** POST /api/drivers/{driver_id}/stop-lists/{list_id}/stops */
    public function store(Request $request, int|string $driver_id, string $list_id)
    {

       
        $data = $this->validatedStop($request);

            // Upload stop image
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = uploadFile($request->file('image'), 'public/stop_images');

                // Check if upload was successful before saving to database
                if (!$imagePath) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Failed to upload stop image to S3.',
                        'errors' => ['File upload failed'],
                        'status' => 500,
                    ];
                }
            }
        $stop = new DriverStop(array_merge($data, [
            'driver_id' => $driver_id,
            'list_id' => $list_id,
            'image' => $imagePath,
        ]));
        $stop->save();

        return response()->json(['success' => true, 'data' => $stop], 201);
    }

    /** PUT /api/drivers/{driver_id}/stops/{stop_id} */
    public function update(Request $request, int|string $driver_id, string $stop_id)
    {

        $stop = DriverStop::where('driver_id', $driver_id)->findOrFail($stop_id);

        $data = $this->validatedStop($request, true);
        $stop->fill($data)->save();

        return response()->json(['success' => true, 'data' => $stop]);
    }

    /** DELETE /api/drivers/{driver_id}/stops/{stop_id} */
    public function destroy(Request $request, int|string $driver_id, string $stop_id)
    {

        $stop = DriverStop::where('driver_id', $driver_id)->findOrFail($stop_id);
        $stop->delete();

        return response()->json(['success' => true, 'message' => 'Stop deleted.']);
    }

    /** POST /api/drivers/{driver_id}/stop-lists/{list_id}/stops/bulk */
    public function bulkStore(Request $request, int|string $driver_id, string $list_id)
    {

        $payload = $request->validate([
            'stops' => ['required', 'array', 'min:1', 'max:1000'],
            'stops.*.name' => ['required', 'string', 'max:255'],
            'stops.*.address' => ['required', 'string'],
            'stops.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'stops.*.notes' => ['nullable', 'string'],
            'stops.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'stops.*.is_optimized' => ['nullable', 'boolean'],
            'stops.*.optimized_shipment' => ['nullable', 'integer', 'min:1'],
            'stops.*.place_id' => ['nullable', 'string', 'max:255'],
            'stops.*.contact_person' => ['nullable', 'string', 'max:255'],
            'stops.*.phone_number' => ['nullable', 'string', 'max:20'],
            'stops.*.delivery_instructions' => ['nullable', 'string'],
            'stops.*.is_priority' => ['nullable', 'boolean'],
            'stops.*.status' => ['nullable', Rule::in(['pending', 'in_transit', 'delivered', 'failed', 'cancelled'])],
        ]);

        $created = [];
        foreach ($payload['stops'] as $item) {
            $stop = new DriverStop(array_merge($item, [
                'driver_id' => $driver_id,
                'list_id' => $list_id,
            ]));
            $stop->save();
            $created[] = $stop;
        }

        DriverStopList::find($list_id)?->recalcStats();

        return response()->json(['success' => true, 'data' => $created], 201);
    }



private function validatedStop(Request $request, bool $partial = false): array
{
    $rules = [
        'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
        'address' => [$partial ? 'sometimes' : 'required', 'string'],
        'latitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
        'longitude' => [$partial ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],

        'notes' => ['sometimes', 'nullable', 'string'],
        'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
        'is_optimized' => ['sometimes', 'boolean'],
        'optimized_shipment' => ['sometimes', 'nullable', 'integer', 'min:1'],
        'place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
        'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
        'delivery_instructions' => ['sometimes', 'nullable', 'string'],
        'is_priority' => ['sometimes', 'boolean'],
        'status' => ['sometimes', Rule::in(['pending', 'in_transit', 'delivered', 'failed', 'cancelled'])],

        // ✅ NEW FIELDS
        'image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],

        'package_count' => ['sometimes', 'integer', 'min:1'],

        'order' => ['sometimes', Rule::in(['first', 'last', 'auto'])],

        'type' => ['sometimes', Rule::in(['pickup', 'delivery'])],

        'arrival_time' => [
            'sometimes',
            'nullable',
            'date_format:H:i'
        ],

        'access_instructions' => ['sometimes', 'nullable', 'string'],
    ];

    return $request->validate($rules);
}




}
