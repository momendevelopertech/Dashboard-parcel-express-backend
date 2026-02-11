<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DriverStopList;
use Illuminate\Http\Request;

class DriverStopListController extends Controller
{
    public function index(Request $request, int|string $driver_id)
    {

        $lists = DriverStopList::where('driver_id', $driver_id)
            ->orderByDesc('is_active')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $lists,
        ]);
    }

    /** POST /api/drivers/{driver_id}/stop-lists */
    public function store(Request $request, int|string $driver_id)
    {

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'total_distance_meters' => ['nullable', 'numeric'],
            'total_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'optimized_route_data' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (!empty($data['is_active']) && $data['is_active']) {
            DriverStopList::where('driver_id', $driver_id)->update(['is_active' => false]);
        }
        if (!empty($data['is_default']) && $data['is_default']) {
            DriverStopList::where('driver_id', $driver_id)->update(['is_default' => false]);
        }

        $list = new DriverStopList(array_merge($data, ['driver_id' => $driver_id]));
        $list->save();

        return response()->json(['success' => true, 'data' => $list], 201);
    }

    public function update(Request $request, int|string $driver_id, string $list_id)
    {

        $list = DriverStopList::where('driver_id', $driver_id)->findOrFail($list_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'total_distance_meters' => ['sometimes', 'nullable', 'numeric'],
            'total_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'optimized_route_data' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('is_active', $data) && $data['is_active']) {
            DriverStopList::where('driver_id', $driver_id)->update(['is_active' => false]);
        }
        if (array_key_exists('is_default', $data) && $data['is_default']) {
            DriverStopList::where('driver_id', $driver_id)->update(['is_default' => false]);
        }

        $list->fill($data)->save();

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function destroy(Request $request, int|string $driver_id, string $list_id)
    {

        $list = DriverStopList::where('driver_id', $driver_id)->findOrFail($list_id);
        $list->delete();

        return response()->json(['success' => true, 'message' => 'List deleted.']);
    }
}
