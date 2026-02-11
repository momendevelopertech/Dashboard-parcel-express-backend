<?php

namespace App\Observers;

use App\Models\Merchant;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\MerchantSetting;
use App\Models\MerchantCommission;
use App\Models\CommissionTemplate;
use App\Models\State;
use App\Models\Setting;
use App\Models\Station;
use App\Services\MerchantCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class MerchantObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function created(Merchant $merchant)
    {
        // Create merchant settings
        if (is_null($merchant->user_id)) {
            MerchantSetting::create([
                'merchant_id' => $merchant->id,
            ]);
        } else {
            MerchantSetting::create([
                'merchant_id' => $merchant->user_id,
            ]);
        }

        // Create default commissions for all states
        $this->createDefaultCommissions($merchant);
        $defaults = config('commissions.defaults', []);

        $deliveryFee = max(0, ($defaults['base_delivery_fee'] ?? 0) - ($defaults['delivery_discount_amount'] ?? 0));
        $returnFee = max(0, ($defaults['base_return_fee'] ?? 0) - ($defaults['return_discount_amount'] ?? 0));

        DB::transaction(function () use ($merchant) {
            $globals = CommissionTemplate::whereNull('state_id')->get();

            foreach ($globals as $tpl) {
                MerchantCommission::firstOrCreate(
                    ['merchant_id' => $merchant->id, 'country_id' => $tpl->country_id, 'state_id' => null],
                    [
                        'base_delivery_fee' => $tpl->base_delivery_fee,
                        'base_return_fee' => $tpl->base_return_fee,
                        'delivery_discount_amount' => $tpl->delivery_discount_amount,
                        'return_discount_amount' => $tpl->return_discount_amount,
                        'delivery_fee' => $tpl->delivery_fee,
                        'return_fee' => $tpl->return_fee,
                    ]
                );
            }

            // 2) Per-state defaults
            $perState = CommissionTemplate::whereNotNull('state_id')->get();

            foreach ($perState as $tpl) {
                MerchantCommission::updateOrCreate(
                    ['merchant_id' => $merchant->id, 'country_id' => $tpl->country_id, 'state_id' => $tpl->state_id],
                    [
                        'base_delivery_fee' => $tpl->base_delivery_fee,
                        'base_return_fee' => $tpl->base_return_fee,
                        'delivery_discount_amount' => $tpl->delivery_discount_amount,
                        'return_discount_amount' => $tpl->return_discount_amount,
                        'delivery_fee' => $tpl->delivery_fee,
                        'return_fee' => $tpl->return_fee,
                    ]
                );
            }

        });
    }

    /**
     * Create default commissions for the merchant
     *
     * @param Merchant $merchant
     * @return void
     */
    protected function createDefaultCommissions(Merchant $merchant)
    {
        $defaultValues = $this->getDefaultCommissionValues();

        $states = State::select('id')->get();

        $commissions = [];
        foreach ($states as $state) {
            $commissions[] = [
                'merchant_id' => $merchant->id,
                'country_id' => $merchant->country_id,
                'state_id' => $state->id,
                'base_delivery_fee' => $defaultValues['base_delivery_fee'],
                'base_return_fee' => $defaultValues['base_return_fee'],
                'delivery_discount_amount' => $defaultValues['delivery_discount_amount'],
                'return_discount_amount' => $defaultValues['return_discount_amount'],
                'delivery_fee' => $defaultValues['delivery_fee'],
                'return_fee' => $defaultValues['return_fee'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Use bulk insert for better performance
        if (!empty($commissions)) {
            MerchantCommission::insert($commissions);
        }
    }

    /**
     * Get default commission values from settings or use defaults
     *
     * @return array
     */
    protected function getDefaultCommissionValues()
    {
        $defaultDeliveryFee = (float) (Setting::where('key', 'default_merchant_commission')->value('value') ?? 1);

        return [
            'base_delivery_fee' => $defaultDeliveryFee,
            'base_return_fee' => 1.0, // Default return fee
            'delivery_discount_amount' => 0.0,
            'return_discount_amount' => 0.0,
            'delivery_fee' => $defaultDeliveryFee,
            'return_fee' => 1.0,
        ];
    }

    /**
     * Handle the Merchant "creating" event.
     */
    public function creating(Merchant $merchant)
    {
        $facility = facility();

        if ($facility) {
            $merchant->owner_type = $facility->type;
            $merchant->owner_id = $facility->id;
        }
    }

    /**
     * Handle the Merchant "updating" event.
     */
    public function updating(Merchant $merchant)
    {
        $facility = facility();

        if ($facility) {
            $merchant->owner_type = $facility->type;
            $merchant->owner_id = $facility->id;
        }
    }
}
