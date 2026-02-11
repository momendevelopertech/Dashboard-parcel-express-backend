<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TrackingGFSService
{
    public function track($data)
    {
        $data = json_decode($data);

        $api_url = env('GFS_ENV', 'production') === 'production'
            ? 'https://gli-gw.gfsxpress.com/gli'
            : 'https://test-gli-gw.gfsxpress.com/gli';

        $endpoint = $api_url . '/dwp.serpens.logistics_track_get/2';

        $ct = (string) round(microtime(true) * 1000);

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'gw-ver' => '1',
            'gw-user-group' => env('GFS_USER_GROUP'),
            'ct' => $ct,
            'sign' => ''
        ];

        $requestBody = [
            'waybillNo' => $data->waybillNo,
            'lan' => $data->lan
        ];
        $jsonData = json_encode($requestBody);
        $postData = ['data' => $jsonData];

        $signString = '1'                                 // gw-ver
            . 'dwp.serpens.logistics_track_get'   // api name
            . '2'                                 // api version 
            . env('GFS_USER_GROUP')               // user group
            . $ct                                 // timestamp
            . $jsonData                           // JSON body
            . env('GFS_CIPHER');                  // secret key

        $headers['sign'] = md5($signString);

        $response = Http::withHeaders($headers)
            ->asForm()
            ->post($endpoint, $postData);

        $responseData = $response->json();

        // =====================
        // Mapping GFS Response to Internal Format
        // =====================

        if ($responseData && isset($responseData['data']['trackingList'])) {
            $trackingList = $responseData['data']['trackingList'];

            // Map GFS tracking events to our internal format
            $shipment_histories = collect($trackingList)->map(function ($event) {
                $gfsCodeInfo = $this->getGfsCodeName($event['code']);
                $location = $event['location'] ?? null;

                // Use official message and add location if available
                $message = $event['description'] ?? (is_array($gfsCodeInfo) ? $gfsCodeInfo['message'] : $event['description']);
                if ($location) {
                    $message .= " in $location";
                }

                return [
                    'name' => is_array($gfsCodeInfo) ? $gfsCodeInfo['name'] : $event['code'],
                    'message' => $message,
                    'data' => $location ? [
                        'location' => $location
                    ] : null,
                    'time' => Carbon::parse($event['time'])->toIso8601String(),
                ];
            })->values()->toArray();

            // Determine current status and next step based on latest event
            $latestEvent = collect($trackingList)->first();
            $currentStatus = $latestEvent ? $this->mapGfsCodeToStatus($latestEvent['code']) : 'IN_TRANSIT';

            // Create response in similar format to internal tracking
            $mappedResponse = [
                'tracking_no' => $responseData['data']['waybillNo'],
                'status' => $currentStatus,
                'from_country' => $responseData['data']['fromCountry'] ?? null,
                'to_country' => $responseData['data']['toCountry'] ?? null,
                'delivery_no' => $responseData['data']['deliveryNo'] ?? null,
                'shipment_histories' => $shipment_histories,
                // 'next_step' => $this->getGfsNextStep($currentStatus),
                'is_external' => true, // Flag to indicate this is external tracking
            ];

            return $mappedResponse;
        }

        return $responseData;
    }

    /**
     * Map GFS code to internal status using official codes
     */
    private function mapGfsCodeToStatus($code)
    {
        $statusMapping = [
            'TTC06' => 'DELIVERED',
            'TTC07' => 'DELIVERED',
            'TTC' => 'OFD',
            'TTC02' => 'OFD',
            'TTC30' => 'DELIVERY_EXCEPTION',
            'TTC31' => 'DELIVERY_EXCEPTION',
            'TTC32' => 'DELIVERY_EXCEPTION',
            'TTC33' => 'DELIVERY_EXCEPTION',
            'TTC34' => 'DELIVERY_EXCEPTION',
            'TTC35' => 'DELIVERY_EXCEPTION',
            'TTC36' => 'DELIVERY_EXCEPTION',
            'TTC37' => 'DELIVERY_EXCEPTion',
            'TCC38' => 'DELIVERY_EXCEPTION',
            'TTC39' => 'DELIVERY_EXCEPTION',
            'TTC40' => 'DELIVERY_EXCEPTION',
            'TTC41' => 'DELIVERY_EXCEPTION',
            'TTC42' => 'DELIVERY_EXCEPTION',
            'TTC43' => 'DELIVERY_EXCEPTION',
            'TTC45' => 'DELIVERY_EXCEPTION',
            'TTC46' => 'DELIVERY_EXCEPTION',
            'TTC48' => 'DELIVERY_EXCEPTION',
            'OTC' => 'LOADED',
            'OTC01' => 'COLLECTED',
            'OTC04' => 'ORDER_INBOUNDED',
            'OTC05' => 'LOADED',
            'CTC' => 'ORDER_INBOUNDED',
            'CTC02' => 'ORDER_INBOUNDED',
            'CTC03' => 'ORDER_INBOUNDED',
            'STC' => 'LOADED',
            'STC02' => 'LOADED',
            'STC03' => 'ORDER_INBOUNDED',
            'STC04' => 'ORDER_INBOUNDED',
            'STC05' => 'LOADED',
            'DTC02' => 'COLLECTED',
            'DTC03' => 'ORDER_INBOUNDED',
            'DTC04' => 'LOADED',
            'DTC17' => 'DELIVERY_EXCEPTION',
            'DTC31' => 'OUT_FOR_PICKUP',
            'DRC01' => 'CREATED',
            'RTC01' => 'RETURN_INITIATED',
            'TTC47' => 'DELIVERY_SCHEDULED',
            'TTC86' => 'DELIVERY_EXCEPTION',
            'TTC104' => 'DELIVERY_ATTEMPTED',
            'TTC110' => 'OFD',
            'TTC401' => 'PROCESSING',
        ];
        return $statusMapping[$code] ?? 'IN_TRANSIT';
    }

    /**
     * Get next step for GFS tracking
     */
    private function getGfsNextStep($currentStatus)
    {
        $nextSteps = [
            'CREATED' => ['name' => 'COLLECTED', 'message' => 'Package will be collected'],
            'COLLECTED' => ['name' => 'ORDER_INBOUNDED', 'message' => 'Package will arrive at processing center'],
            'ORDER_INBOUNDED' => ['name' => 'LOADED', 'message' => 'Package will be loaded for transit'],
            'LOADED' => ['name' => 'OFD', 'message' => 'Package will be out for delivery'],
            'OFD' => ['name' => 'DELIVERED', 'message' => 'Package will be delivered'],
            'DELIVERED' => null,
            'DELIVERY_EXCEPTION' => ['name' => 'OFD', 'message' => 'Delivery will be reattempted'],
        ];

        return $nextSteps[$currentStatus] ?? null;
    }

    /**
     * Name for codes I mean it's an array where code refers to a name which is humanly readable.
     */
    private function getGfsCodeName($code)
    {
        $codeNameMapping = [
            'DRC01' => [
                'name' => 'Data Registered',
                'message' => 'Shipping information has been registered successfully.',
            ],
            'DTC01' => [
                'name' => 'Parcel sent',
                'message' => 'The parcel has been sent.',
            ],
            'DTC02' => [
                'name' => 'Parcel picked up',
                'message' => 'The parcel has been picked up from the sender.',
            ],
            'DTC03' => [
                'name' => 'Domestic warehouse inbound',
                'message' => 'The parcel has arrived at the domestic warehouse.',
            ],
            'DTC04' => [
                'name' => 'Domestic warehouse outbound',
                'message' => 'The parcel has left the domestic warehouse.',
            ],
            'STC' => [
                'name' => 'International transit',
                'message' => 'The parcel is currently in international transit.',
            ],
            'STC02' => [
                'name' => 'Departed from original airport',
                'message' => 'The parcel has departed from the origin airport and is en route.',
            ],
            'STC03' => [
                'name' => 'Arrived at destination airport',
                'message' => 'The parcel has arrived at the destination country\'s airport.',
            ],
            'STC04' => [
                'name' => 'Arrived at international transit port',
                'message' => 'The parcel has reached an international transit facility.',
            ],
            'STC05' => [
                'name' => 'Departed from international transit port',
                'message' => 'The parcel has left the international transit facility.',
            ],
            'CTC' => [
                'name' => 'On clearance',
                'message' => 'The parcel is currently undergoing customs clearance.',
            ],
            'CTC02' => [
                'name' => 'On clearance',
                'message' => 'The parcel is being processed by customs.',
            ],
            'CTC03' => [
                'name' => 'Clearance completed',
                'message' => 'The customs clearance has been completed successfully.',
            ],
            'OTC' => [
                'name' => 'Local transit',
                'message' => 'The parcel is in local transit within the destination country.',
            ],
            'OTC01' => [
                'name' => 'Parcel picked up from customs',
                'message' => 'The parcel has been released from customs and picked up for delivery.',
            ],
            'OTC04' => [
                'name' => 'Accepted at local sorting center',
                'message' => 'The parcel has been received at a local sorting facility.',
            ],
            'OTC05' => [
                'name' => 'Departed from local sorting center',
                'message' => 'The parcel has left the local sorting center and is on its way.',
            ],
            'TTC' => [
                'name' => 'Out for delivery',
                'message' => 'The parcel is out for delivery to the recipient.',
            ],
            'TTC02' => [
                'name' => 'Arrived at delivery station',
                'message' => 'The parcel has reached the final delivery station.',
            ],
            'TTC05' => [
                'name' => 'Held for pick-up',
                'message' => 'The parcel is being held for customer pick-up.',
            ],
            'TTC06' => [
                'name' => 'Delivered',
                'message' => 'The parcel has been successfully delivered to the recipient.',
            ],
            'TTC07' => [
                'name' => 'Collected by customer',
                'message' => 'The customer has collected the parcel.',
            ],
            'TTC30' => [
                'name' => 'Delivery abnormal - Refused by customer',
                'message' => 'Delivery failed because the customer refused to accept the parcel.',
            ],
            'TTC31' => [
                'name' => 'Delivery Failed - Refused by customer',
                'message' => 'Delivery attempt failed; the customer refused the parcel.',
            ],
            'TTC32' => [
                'name' => 'Address inaccessible',
                'message' => 'Delivery could not be completed due to an inaccessible address.',
            ],
            'TTC33' => [
                'name' => 'Wrong phone number',
                'message' => 'Delivery failed due to an incorrect phone number.',
            ],
            'TTC34' => [
                'name' => 'Parcel undeliverable',
                'message' => 'The parcel could not be delivered for unspecified reasons.',
            ],
            'TTC35' => [
                'name' => 'Customer requested to open parcel',
                'message' => 'Delivery failed because the customer requested to open the parcel before accepting.',
            ],
            'TTC36' => [
                'name' => 'Parcel damaged',
                'message' => 'Delivery was unsuccessful as the parcel was found damaged.',
            ],
            'TTC37' => [
                'name' => 'No answer from customer',
                'message' => 'Delivery attempt failed as there was no response from the customer.',
            ],
            'TCC38' => [
                'name' => 'Money not ready',
                'message' => 'Delivery failed because the customer was not prepared with payment.',
            ],
            'TTC39' => [
                'name' => 'Customer absent',
                'message' => 'The parcel could not be delivered because the customer was not present.',
            ],
            'TTC40' => [
                'name' => 'Customer not available',
                'message' => 'Delivery failed as the customer was unavailable at the delivery address.',
            ],
            'TTC41' => [
                'name' => 'Vehicle breakdown',
                'message' => 'Delivery could not be completed due to a vehicle breakdown.',
            ],
            'TTC42' => [
                'name' => 'No network',
                'message' => 'Delivery failed due to lack of network connectivity.',
            ],
            'TTC43' => [
                'name' => 'Bad weather',
                'message' => 'Delivery was delayed or failed due to adverse weather conditions.',
            ],
            'TTC45' => [
                'name' => 'Held for pick-up',
                'message' => 'Delivery was not completed; the parcel is available for pick-up.',
            ],
            'TTC46' => [
                'name' => 'Delivery delay',
                'message' => 'There is a delay in delivery due to unforeseen circumstances.',
            ],
            'TTC48' => [
                'name' => 'Address change requested',
                'message' => 'Delivery delayed due to customer request to change the delivery address.',
            ],
            'TTC04' => [
                'name' => 'Out of delivery',
                'message' => 'The parcel is out of delivery.',
            ],
            'OTC03' => [
                'name' => 'Departed from distribution center in Muscat',
                'message' => 'The parcel has departed from the distribution center in Muscat.',
            ],
            'CTC01' => [
                'name' => 'Parcel picked up from destination airport',
                'message' => 'Parcel picked up from destination airport.',
            ],
            'CTC08' => [
                'name' => 'Customs Clearance Finished',
                'message' => 'The customs clearance has been completed successfully.',
            ],
            'CTC06' => [
                'name' => 'Customs Clearance Delivery',
                'message' => 'The parcel has been delivered to the destination country.',
            ],
            'DTC00' => [
                'name' => 'Parcel received and inbound',
                'message' => 'The parcel has been received and is inbound.',
            ],
            'DTC17' => [
                'name' => 'Pickup failed - Driver did not contact customer',
                'message' => 'Pickup failed as the driver did not contact the customer on time.',
            ],
            'DTC31' => [
                'name' => 'DTO out for pickup',
                'message' => 'Driver is on the way to pickup the parcel.',
            ],
            'OTC02' => [
                'name' => 'Parcel picked up from distribution center in Muscat',
                'message' => 'The parcel has been picked up from the distribution center in Muscat.',
            ],
            'RTC01' => [
                'name' => 'Initiate return shipment',
                'message' => 'The return shipment process has been initiated.',
            ],
            'TTC47' => [
                'name' => 'Delivery date already scheduled',
                'message' => 'A delivery date has already been scheduled with the customer.',
            ],
            'TTC86' => [
                'name' => 'Delivery exception - Area error',
                'message' => 'Delivery exception occurred due to an area error.',
            ],
            'TTC104' => [
                'name' => 'End of delivery reminder - Unreachable',
                'message' => 'End of delivery reminder: Customer was unreachable.',
            ],
            'TTC110' => [
                'name' => 'Start delivery reminder',
                'message' => 'Delivery process has been initiated.',
            ],
            'TTC401' => [
                'name' => 'Preparing for dispatch',
                'message' => 'The parcel is being prepared for dispatch.',
            ],
        ];

        return $codeNameMapping[$code] ?? $code;
    }
}
