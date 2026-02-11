<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Parcel Express API",
 *     description="API Documentation for Parcel Express System",
 *     @OA\Contact(
 *         email="support@parcelexpress.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication Endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Shipments",
 *     description="Shipment Management"
 * )
 *
 * @OA\Tag(
 *     name="Drivers",
 *     description="Driver Management"
 * )
 *
 * @OA\Tag(
 *     name="Merchants",
 *     description="Merchant Management"
 * )
 *
 * @OA\Tag(
 *     name="Common",
 *     description="Common Resources (Hubs, Units, etc.)"
 * )
 */
class OpenApi
{
}
