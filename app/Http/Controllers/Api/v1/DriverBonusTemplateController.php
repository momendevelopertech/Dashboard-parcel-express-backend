<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DriverBonusTemplate;
use Illuminate\Http\Request;

class DriverBonusTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $facilityId = facility('id');
        $facilityType = facility('type');

        $templates = DriverBonusTemplate::with('state')
            ->where('owner_id', $facilityId)
            ->where('owner_type', $facilityType)
            ->get();

        return sendResponse("Driver bonus templates retrieved successfully.", $templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'state_id' => 'nullable|exists:states,id',
            'delivery_bonus' => 'required|numeric|min:0',
            'pickup_bonus' => 'required|numeric|min:0',
        ]);

        $facilityId = facility('id');
        $facilityType = facility('type');

        // Check if template already exists for this state (or global) for this facility
        $exists = DriverBonusTemplate::where('owner_id', $facilityId)
            ->where('owner_type', $facilityType)
            ->where('state_id', $request->state_id)
            ->exists();

        if ($exists) {
            $scope = $request->state_id ? 'this state' : 'global default';
            return sendResponse("A template for $scope already exists.", [], false, [], 422);
        }

        $template = DriverBonusTemplate::create([
            'owner_id' => $facilityId,
            'owner_type' => $facilityType,
            'state_id' => $request->state_id,
            'delivery_bonus' => $request->delivery_bonus,
            'pickup_bonus' => $request->pickup_bonus,
        ]);

        return sendResponse("Driver bonus template created successfully.", $template->load('state'));
    }

    /**
     * Display the specified resource.
     */
    public function show(DriverBonusTemplate $driverBonusTemplate)
    {
        // Ensure ownership
        if ($driverBonusTemplate->owner_id != facility('id') || $driverBonusTemplate->owner_type != facility('type')) {
            return sendResponse("Unauthorized access to this template.", [], false, [], 403);
        }

        return sendResponse("Driver bonus template retrieved successfully.", $driverBonusTemplate->load('state'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DriverBonusTemplate $driverBonusTemplate)
    {
        // Ensure ownership
        if ($driverBonusTemplate->owner_id != facility('id') || $driverBonusTemplate->owner_type != facility('type')) {
            return sendResponse("Unauthorized access to this template.", [], false, [], 403);
        }

        $request->validate([
            'delivery_bonus' => 'required|numeric|min:0',
            'pickup_bonus' => 'required|numeric|min:0',
        ]);

        $driverBonusTemplate->update([
            'delivery_bonus' => $request->delivery_bonus,
            'pickup_bonus' => $request->pickup_bonus,
        ]);

        return sendResponse("Driver bonus template updated successfully.", $driverBonusTemplate->load('state'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DriverBonusTemplate $driverBonusTemplate)
    {
        // Ensure ownership
        if ($driverBonusTemplate->owner_id != facility('id') || $driverBonusTemplate->owner_type != facility('type')) {
            return sendResponse("Unauthorized access to this template.", [], false, [], 403);
        }

        $driverBonusTemplate->delete();

        return sendResponse("Driver bonus template deleted successfully.", []);
    }
}
