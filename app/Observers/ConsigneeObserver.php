<?php

namespace App\Observers;
use App\Models\Hub;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\Station;
use App\Models\Consignee;
use Illuminate\Support\Facades\Auth;

class ConsigneeObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(Consignee $obs)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {

                $obs->owner_type = Branch::class;
                $obs->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {

                $obs->owner_type = Station::class;
                $obs->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $obs->owner_type = Hub::class;
                $obs->owner_id = $selectedWorkspaceId;
            }
        }
    }

    public function updating(Consignee $obs)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $obs->owner_type = Branch::class;
                $obs->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                $obs->owner_type = Station::class;
                $obs->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {
                $obs->owner_type = Hub::class;
                $obs->owner_id = $selectedWorkspaceId;
            }
        }
    }


    /**
     * Handle the Consignee "updated" event.
     *
     * @param  \Models\Consignee  $Consignee
     * @return void
     */
    public function updated(Consignee $consignee)
    {
        $changes = $consignee->getChanges();
        $shipment_id = session()->get("current_shipment_id");
        $user = Auth::user();
        $status = "UPDATED";

        $changedFields = [];
        foreach ($changes as $field => $newValue) {
            if ($field !== 'updated_at') {
                $oldValue = $consignee->getOriginal($field);
                $changedFields[] = $field . ' changed from ' . $oldValue . ' to ' . $newValue;
            }
        }

        if (!empty($changedFields)) {
            $historyData = [
                "type" => status($status)['label'],
                "description" => 'Consignee details updated: ' . implode(', ', $changedFields) . ' by ' . ($user->name ?? 'System'),
                "shipment_id" => $shipment_id,
            ];

            shipmentHistory($historyData);
        }

        updateShipmentStatus($shipment_id, "EDITED");
    }
}
