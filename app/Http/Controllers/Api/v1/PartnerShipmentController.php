<?php

namespace App\Http\Controllers\Api\v1;

use Carbon\Carbon;
use App\Models\Shipment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Consignee;
use App\Models\Address;
use App\Models\ShipmentInformation;
use App\Models\ShipmentFinance;
use App\Models\ShipmentDelivery;
use App\Models\Scopes\ConsigneeScope;
use Carbon\Exceptions\InvalidFormatException;

class PartnerShipmentController extends Controller
{
    /**
     * GET /v1/shipments?status=&from=&to=&partner_order_id=&limit=&page=
     * Get all shipments for a partner
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

        private function createConsigneeAddress(int $consigneeId, array $addr): Address
        {
            return Address::create([
                'consignee_id' => $consigneeId,
                'country_id' => $addr['country_id'] ?? null,
                'governorate_id' => $addr['governorate_id'] ?? null,
                'state_id' => $addr['state_id'] ?? null,
                'place_id' => $addr['place_id'] ?? null,
                'city_id' => $addr['city_id'] ?? null,
                'zipcode' => $addr['zipcode'] ?? null,
                'streetAddress' => $addr['streetAddress'] ?? null,
                'longitude' => $addr['longitude'] ?? null,
                'latitude' => $addr['latitude'] ?? null,
                'location_url' => $addr['location_url'] ?? null,
                'label' => $addr['label'] ?? null,
                'approved' => false,
                'is_active' => true,
            ]);
        }

    public function index(Request $request)
    {
        $partner = $request->attributes->get('partner');

        $limit = min((int) $request->query('limit', 50), 200);
        $page = max((int) $request->query('page', 1), 1);

        $q = $partner->marketplaceShipments()->getQuery();

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $partnerOrderId = $request->query('partner_order_id') ?? $request->query('partner_shipment_id');
        if ($partnerOrderId) {
            $q->where('partner_shipment_id', $partnerOrderId);
        }

        foreach (['from' => '>=', 'to' => '<='] as $param => $operator) {
            $value = $request->query($param);
            if (!$value) {
                continue;
            }

            try {
                $date = Carbon::parse($value);
            } catch (InvalidFormatException $exception) {
                return response()->json([
                    'code' => 'invalid_query_parameter',
                    'message' => 'Invalid date filter provided.',
                    'details' => [
                        'parameter' => $param,
                        'value' => $value,
                        'expected' => 'ISO 8601 date or datetime string',
                    ],
                ], 422);
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = $param === 'from'
                    ? $date->startOfDay()
                    : $date->endOfDay();
            }

            $q->where('created_at', $operator, $date);
        }

        // Eager-load what you need for transformation
        $q->with([
            'consignee.country',
            'consignee.state',
            'consignee.governorate',
            'consignee.place',
        ]);

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage($page, $limit)->get();

        $data = $rows->map(fn($shipment) => $this->transformShipmentForPartner($shipment));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit,
            ],
        ]);
    }



    /**
 * POST /v1/shipments
 * Create a new shipment from partner payload.
 */
    public function storeShipment(Request $request)
    {
        $partner = $request->attributes->get('partner');
        $payload = $request->all();

        // Validate required fields minimally
        $request->validate([
            'partner_order_id' => 'required|string|max:191',
            'recipient.name' => 'required|string|max:191',
            'recipient.phone' => 'required|string|max:50',
            'recipient.address.line1' => 'required|string|max:255',
            'recipient.address.city' => 'required|string|max:191',
            'shipping.weight_kg' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
        ]);

        // Build consignee data from recipient
        $recipient = $payload['recipient'];
        $address = $recipient['address'] ?? [];

        $consigneeData = [
            'name' => $recipient['name'] ?? null,
            'email' => $recipient['email'] ?? null,
            'cellphone' => $recipient['phone'] ?? null,
            'alternatePhone' => $recipient['alternate_phone'] ?? null,
            'district' => $recipient['district'] ?? null,
            'country_id' => $address['country_code'] ?? null,
            'governorate_id' => $address['governorate_id'] ?? null,
            'state_id' => $address['state_id'] ?? null,
            'place_id' => $address['place_id'] ?? null,
            'city_id' => $address['city_id'] ?? null,
            'zipcode' => $address['postal_code'] ?? null,
            'streetAddress' => $address['line1'] ?? null,
            'identify' => $recipient['identify'] ?? null,
            'taxNumber' => $recipient['tax_number'] ?? null,
            'longitude' => $address['longitude'] ?? null,
            'latitude' => $address['latitude'] ?? null,
            'location_url' => $address['location_url'] ?? null,
        ];

        // Normalize phone numbers
        $alternatePhoneSplit = $consigneeData['alternatePhone'] 
            ? splitPhoneNumber($consigneeData['alternatePhone']) 
            : ['country_code' => null, 'national_number' => null];
        $cellphoneSplit = $consigneeData['cellphone'] 
            ? splitPhoneNumber($consigneeData['cellphone']) 
            : ['country_code' => null, 'national_number' => null];

        $consigneeData['country_key_cellphone'] = $cellphoneSplit['country_code'];
        $consigneeData['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
        $consigneeData['cellphone'] = $cellphoneSplit['national_number'];
        $consigneeData['alternatePhone'] = $alternatePhoneSplit['national_number'];

        // Find existing consignee
        $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
            ->where(function ($q) use ($cellphoneSplit, $alternatePhoneSplit) {
                if ($cellphoneSplit['country_code'] && $cellphoneSplit['national_number']) {
                    $q->where('country_key_cellphone', $cellphoneSplit['country_code'])
                    ->where('cellphone', $cellphoneSplit['national_number']);
                }
                if ($alternatePhoneSplit['country_code'] && $alternatePhoneSplit['national_number']) {
                    $q->orWhere(function ($qq) use ($alternatePhoneSplit) {
                        $qq->where('country_key_alternatePhone', $alternatePhoneSplit['country_code'])
                        ->where('alternatePhone', $alternatePhoneSplit['national_number']);
                    });
                }
            })
            ->first();

        // Update or create consignee
        if ($existingConsignee) {
            $identityFields = [
                'name', 'email', 'country_key_cellphone', 'cellphone',
                'country_key_alternatePhone', 'alternatePhone',
                'district', 'identify', 'taxNumber'
            ];

            $diff = collect($consigneeData)
                ->only($identityFields)
                ->filter(fn($v) => !is_null($v) && $v !== '')
                ->diffAssoc($existingConsignee->only($identityFields));

            if ($diff->isNotEmpty()) {
                $existingConsignee->update($diff->toArray());
            }
            $consignee = $existingConsignee;
        } else {
            $consignee = Consignee::create($consigneeData);
        }

        $deliveryAddressInput = [
            'country_id' => $consignee['country_id'] ?? null,
            'governorate_id' => $consignee['governorate_id'] ?? null,
            'state_id' => $consignee['state_id'] ?? null,
            'place_id' => $consignee['place_id'] ?? null,
            'city_id' => $consignee['city_id'] ?? null,
            'zipcode' => $consignee['zipcode'] ?? null,
            'streetAddress' => $consignee['streetAddress'] ?? null,
            'longitude' => $consignee['longitude'] ?? null,
            'latitude' => $consignee['latitude'] ?? null,
            'location_url' => $consignee['location_url'] ?? null,
            'label' => null,
            ];

            $deliveryAddress = $this->createConsigneeAddress($consignee->id, $deliveryAddressInput);

        // Create shipment
        $shipment = new Shipment();
        $shipment->partner_id = $partner->id;
        $shipment->delivery_address_id = $deliveryAddress->id;
        $shipment->partner_shipment_id = $payload['partner_order_id'];
        $shipment->partner_shipment_tracking_number = $payload['tracking_number'] ?? null;
        $shipment->status = 'CREATED';
        $shipment->created_source = 'unCreated';
        $shipment->tracking_no = generate_tracking_no();
        $shipment->customer_name = $consignee->name;
        $shipment->customer_phone = $consignee->cellphone;
        // $shipment->customer_email = $consignee->email;
        $shipment->consignee_id = $consignee->id; // link shipment to consignee
        $shipment->streetAddress = $consignee->streetAddress;
        $shipment->city_id = $consignee->city_id;
        $shipment->state_id = $consignee->state_id;
        $shipment->governorate_id = $consignee->governorate_id;
        $shipment->place_id = $consignee->place_id;
        $shipment->zipcode = $consignee->zipcode;
        $shipment->country_id = $consignee->country_id;
        $shipment->latitude = $consignee->latitude;
        $shipment->longitude = $consignee->longitude;
        $shipment->payment_type = $payload['payment']['type'] ?? null;
        $shipment->total_cod = $payload['payment']['cod_amount'] ?? 0;
        // $shipment->currency = $payload['payment']['currency'] ?? null;
        // $shipment->weight_kg = $payload['shipping']['weight_kg'] ?? null;
        $shipment->notes = $payload['notes'] ?? null;
        $shipment->allow_return = 1;
        $shipment->delivery_priority = 'normal';
        $shipment->delivery_time = 'any';
        $shipment->created_by = null; // can be system user or partner id

        if (isset($payload['merchant_id'])) {
            $shipment->merchant_id = $payload['merchant_id'];
        }

        $shipment->save();

        // Save shipment items
        foreach ($payload['items'] as $item) {
            $shipment->shipment_items()->create([
                'sku' => $item['sku'] ?? null,
                'name' => $item['name'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? null,
                'weight_kg' => $item['weight_kg'] ?? 0,
                'hs_code' => $item['hs_code'] ?? null,
                'country_of_origin' => $item['country_of_origin'] ?? null,
            ]);
        }


            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipmentData['merchant_id'] ?? null,
                'unit_id' => $request->unit_id,
                'zone_id' => $request->zone_id,
                'package_id' => $request->package_id,
                'tracking_no' => $shipment->tracking_no,
                'in_warehouse' => true,
                'weight' => $request->weight,
                'height' => $request->height,
                'width' => $request->width,
                'length' => $request->length,
                'status' => $request->status ?? 0,
            ]);

            ShipmentDelivery::create(['shipment_id' => $shipment->id]);
            ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);

        return response()->json([
            'message' => 'Shipment created successfully.',
            'data' => $this->transformShipmentForPartner($shipment->fresh())
        ], 201);
    }


    public function showByInternalId(string $id)
    {
        $partner = request()->attributes->get('partner');

        $shipment = $partner->marketplaceShipments()
            ->where('id', $id)
            ->with([
                'consignee.country',
                'consignee.state',
                'consignee.governorate',
                'consignee.place',
                // add any relations you already rely on
            ])
            ->first();

        if (!$shipment) {
            return response()->json([
                'code' => 'resource_not_found',
                'message' => 'Shipment not found',
                'details' => ['shipment_id' => $id],
            ], 404);
        }

        return response()->json($this->transformShipmentForPartner($shipment));
    }

    /**
	 * GET /v1/trackings?order_id=&from=&limit=
	 * List tracking timelines for partner shipments.
	 */
	public function listAllTrackings(Request $request)
	{
		$partner = $request->attributes->get('partner');

		$limit = min((int) $request->query('limit', 50), 200);
		$page = max((int) $request->query('page', 1), 1);
		$trackingNumber = $request->query('tracking_number');
		$fromParam = $request->query('from');
		$fromDate = null;

		if ($fromParam) {
			try {
				$fromDate = Carbon::parse($fromParam);
				if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromParam)) {
					$fromDate = $fromDate->startOfDay();
				}
			} catch (InvalidFormatException $exception) {
				return response()->json([
					'code' => 'invalid_query_parameter',
					'message' => 'Invalid date filter provided.',
					'details' => [
						'parameter' => 'from',
						'value' => $fromParam,
						'expected' => 'ISO 8601 date or datetime string',
					],
				], 422);
			}
		}

		$q = $partner->marketplaceShipments()->getQuery();
		if ($trackingNumber) {
			$q->where('tracking_no', $trackingNumber);
		}

		$q->with([
			'country',
			'shipmentHistories' => function ($query) use ($fromDate) {
				if ($fromDate) {
					$query->where(function ($sub) use ($fromDate) {
						$sub->where('time', '>=', $fromDate)
							->orWhere('created_at', '>=', $fromDate);
					});
				}
				$query->orderBy('time')->orderBy('id');
			},
		]);

		$total = (clone $q)->count();
		$rows = $q->orderByDesc('id')->forPage($page, $limit)->get();

		$data = $rows->map(function ($shipment) {
			$events = $shipment->shipmentHistories->map(function ($history) use ($shipment) {
				$rawTimestamp = $history->time ?? $history->created_at;
				$timestamp = $rawTimestamp instanceof \Carbon\CarbonInterface
					? $rawTimestamp
					: ($rawTimestamp ? Carbon::parse($rawTimestamp) : null);

				return [
					'status' => $history->name,
					'status_code' => $history->name ? Str::upper(Str::snake($history->name)) : null,
					'description' => $history->description,
					'location' => [
						'city' => $history->operation_hub_name,
						'country' => optional($shipment->country)->iso2,
					],
					'timestamp' => $timestamp?->toIso8601String(),
				];
			})->values()->all();

			$latestStatus = $shipment->shipmentHistories->last();

			return [
				'tracking_number' => $shipment->tracking_no,
				'internal_shipment_id' => (string) $shipment->id,
				'partner_order_id' => $shipment->partner_shipment_id,
				'current_status' => optional($latestStatus)->name ?? $shipment->status,
				'estimated_delivery' => null,
				'events' => $events,
			];
		})->values();

		return response()->json([
			'data' => $data,
			'meta' => [
				'current_page' => $page,
				'total_pages' => (int) ceil($total / $limit),
				'total_records' => $total,
				'per_page' => $limit,
			],
		]);
	}

    // OPTIONAL: GET /v1/tracking/{tracking_no}
    // If you want to keep parity with your existing show($tracking_no)
    public function showByTracking(string $trackingNo)
    {
        $partner = request()->attributes->get('partner');

        $shipment = $partner->marketplaceShipments()
            ->where('tracking_no', $trackingNo)
            ->with([
                'country',
                'shipmentHistories' => function ($query) {
                    $query->orderBy('time')->orderBy('id');
                },
            ])
            ->first();

        if (!$shipment) {
            return response()->json([
                'code' => 'resource_not_found',
                'message' => 'Shipment not found',
                'details' => ['tracking_no' => $trackingNo],
            ], 404);
        }

        $events = $shipment->shipmentHistories->map(function ($history) use ($shipment) {
            $rawTimestamp = $history->time ?? $history->created_at;
            $timestamp = $rawTimestamp instanceof \Carbon\CarbonInterface
                ? $rawTimestamp
                : ($rawTimestamp ? Carbon::parse($rawTimestamp) : null);

            return [
                'status' => $history->name,
                'status_code' => $history->name ? Str::upper(Str::snake($history->name)) : null,
                'description' => $history->description,
                'location' => [
                    'city' => $history->operation_hub_name,
                    'country' => optional($shipment->country)->iso2,
                ],
                'timestamp' => $timestamp?->toIso8601String(),
            ];
        })->values()->all();

        $latestStatus = $shipment->shipmentHistories->last();

        return response()->json([
            'tracking_number' => $shipment->tracking_no,
            'internal_shipment_id' => (string) $shipment->id,
            'current_status' => optional($latestStatus)->name ?? $shipment->status,
            'estimated_delivery' => null,
            'events' => $events,
        ]);
    }

    /**
     * Map your rich Shipment model to the partner response shape.
     * Adjust the field names below to your actual columns.
     */
    private function transformShipmentForPartner($shipment): array
    {
        $trackingNumbers = array_values(array_filter([
            $shipment->tracking_no ? (string) $shipment->tracking_no : null,
        ]));

        $consignee = $shipment->consignee;

        $recipient = [
            'name' => optional($consignee)->name,
            'phone' => optional($consignee)->cellphone,
            'address' => [
                'line1' => optional($consignee)->address,
                'city' => optional(optional($consignee)->place)->en_name
                    ?? optional(optional($consignee)->state)->en_name
                    ?? optional(optional($consignee)->governorate)->en_name,
                'postal_code' => optional($consignee)->zipcode,
                'country' => optional(optional($consignee)->country)->iso2,
            ],
        ];

        $items = optional($shipment->shipment_items)->map(function ($item) {
            $weight = isset($item->weight_grams)
                ? round(((float) $item->weight_grams) / 1000, 3)
                : (float) ($item->weight_kg ?? 0);

            return [
                'sku' => $item->sku ?? null,
                'description' => $item->description ?? ($item->name ?? null),
                'quantity' => (int) ($item->quantity ?? 1),
                'weight_kg' => $weight,
            ];
        })->values()->all() ?? [];

        $shipping = [
            'service_code' => $shipment->service_code ?? null,
            'weight_kg' => isset($shipment->weight_kg) ? (float) $shipment->weight_kg : null,
            'estimated_delivery' => null,
        ];

        return [
            'internal_shipment_id' => (string) $shipment->id,
            'partner_order_id' => $shipment->partner_shipment_id,
            'partner_shipment_id' => $shipment->partner_shipment_id,
            'status' => $shipment->status,
            'tracking_numbers' => $trackingNumbers,
            'created_at' => optional($shipment->created_at)?->toIso8601String(),
            'recipient' => $recipient,
            'items' => $items,
            'shipping' => $shipping,
        ];
    }
}
