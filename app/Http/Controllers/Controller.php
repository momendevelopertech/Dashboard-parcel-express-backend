<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Dashboard Parcel Express API",
 *      description="API Documentation for Dashboard Parcel Express",
 *      @OA\Contact(
 *          email="admin@example.com"
 *      ),
 *      @OA\License(
 *          name="Apache 2.0",
 *          url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *      )
 * )
 *
 * @OA\Server(
 *      url="/api/v1",
 *      description="API v1 Server"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="apiKey",
 *      in="header",
 *      name="Authorization",
 *      description="Enter token in format (Bearer <token>)"
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error occurred"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
 * )
 *
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"login", "password"},
 *     @OA\Property(property="login", type="string", example="user@example.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password"),
 *     @OA\Property(property="workspace", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Login successful"),
 *     @OA\Property(property="data", type="object",
 *         @OA\Property(property="token", type="string", example="1|laravel_sanctum_token"),
 *         @OA\Property(property="user", ref="#/components/schemas/User")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890")
 * )
 * @OA\Schema(
 *     schema="Shipment",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tracking_no", type="string", example="TRK123"),
 *     @OA\Property(property="status", type="string", example="DELIVERED"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="shipper", type="object"),
 *     @OA\Property(property="consignee", type="object")
 * )
 */
abstract class Controller
{
    //
}
