<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="ChatSession",
 *     type="object",
 *     description="Single chat session object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         example="+201234567890"
 *     ),
 *     @OA\Property(
 *         property="customer_name",
 *         type="string",
 *         example="Ahmed Khaled"
 *     ),
 *     @OA\Property(
 *         property="last_message",
 *         type="string",
 *         example="Hello, I need help with my shipment."
 *     ),
 *     @OA\Property(
 *         property="last_message_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T10:15:00Z"
 *     ),
 *     @OA\Property(
 *         property="unread_count",
 *         type="integer",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         example="open"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MerchantChatMessage",
 *     type="object",
 *     description="Single merchant chat message",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="merchant_chat_session_id",
 *         type="integer",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="sender_id",
 *         type="integer",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="sender_name",
 *         type="string",
 *         example="Support Agent"
 *     ),
 *     @OA\Property(
 *         property="sender_type",
 *         type="string",
 *         example="admin"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Hello, how can I help you?"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T10:15:00Z"
 *     ),
 *     @OA\Property(
 *         property="is_read",
 *         type="boolean",
 *         example=false
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MerchantChatSession",
 *     type="object",
 *     description="Merchant chat session info",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         example="MCH-20251203-0001"
 *     ),
 *     @OA\Property(
 *         property="merchant_id",
 *         type="integer",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="merchant_name",
 *         type="string",
 *         example="Test Merchant"
 *     ),
 *     @OA\Property(
 *         property="subject",
 *         type="string",
 *         example="General Chat"
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         example="MEDIUM"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         example="ACTIVE"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T10:15:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T11:00:00Z"
 *     )
 * )
 */
class Chat
{
}
