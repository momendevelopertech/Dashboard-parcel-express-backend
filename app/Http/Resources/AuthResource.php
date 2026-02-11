<?php

namespace App\Http\Resources;

use App\Models\Hub;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $formattedHubs = $this->hubs->map(function ($hub) {
            return [
                'id' => Crypt::encryptString($hub->id),
                'name' => $hub->name,
                'type' => accountables("hub"),
            ];
        });

        $formattedStations = $this->stations->map(function ($station) {
            return [
                'id' => Crypt::encryptString($station->id),
                'name' => $station->name,
                'type' => accountables("station"),
            ];
        });

        $formattedBranches = $this->branches->map(function ($branch) {
            return [
                'id' => Crypt::encryptString($branch->id),
                'name' => $branch->name,
                'type' => accountables("branch"),
            ];
        });
        $user = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'token' => $this->token,
            'isMerchant' => $this->isMerchant,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role' => new UserRoleResource(
                $this->roles
                    ->where('roleable_type', Hub::class)
                    ->where('roleable_id', optional(getUserHub($this))->id)
                    ->first()
            ),

            'current_workspace' => facilityModel(),
            'workspaces' => collect($formattedHubs)
                ->merge($formattedStations)
                ->merge($formattedBranches)
                ->filter()
                ->values(),
            'settings' => new SettingResource(Setting::all())
        ];

        if (isset($this->workspace)) {
            $user['workspace'] = $this->workspace;
        }

        if ($this->hasRole("HubAdmin")) {
            // $user['hub_user'] = $this->hub_user;
            // $user['hub_user']['hub'] = $this->hub_user->hub;
            $user['hub'] = $this->hub;
        }

        if ($this->hasRole("StationAdmin")) {
            $user['station_user'] = $this->station_user;
            $user['station_user']['station'] = $this->station_user->station;
            $user['station_user']['station']['hub'] = $this->station_user->station->hub;
        }

        if ($this->hasRole("BranchAdmin")) {
            $user['branch_user'] = $this->branch_user;
            $user['branch_user']['branch'] = $this->branch_user->branch;
            $user['branch_user']['branch']['station'] = $this->branch_user->branch->station;
            $user['branch_user']['branch']['station']['hub'] = $this->branch_user->branch->station->hub;
        }

        if ($this->merchant) {
            $user['merchant'] = $this->merchant;
            $user['merchant']['governorate'] = $this->merchant->governorate;
            $user['merchant']['state'] = $this->merchant->state;
            $user['merchant']['place'] = $this->merchant->place;
        }

        if ($this->hasAnyRole(["Driver", "Guest Driver", "Vendor Driver"])) {
            $user['driver'] = $this->driver;
            $user['driver']['status'] = $this->driver_status;
            $user['driver']['company'] = $this->driver->company;
            $user['driver']['settings'] = $this->driver->settings;
        }

        if ($this->hasRole("Truck Driver")) {
            $user['truck_driver'] = $this->truck_driver;
            $user['truck_driver']['trucks'] = $this->trucks;
        }

        return $user;
    }
}
