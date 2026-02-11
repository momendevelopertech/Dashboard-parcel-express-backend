<?php

namespace App\Swagger\Schemas\Common;

/**
 * @OA\Schema(
 *     title="ErrorResponse",
 *     description="Standard error response format",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BaseResponse")
 *     }
 * )
 */
class ErrorResponse extends BaseResponse
{
    /**
     * @OA\Property(
     *     title="Success",
     *     description="Request success status",
     *     example=false
     * )
     *
     * @var boolean
     */
    public $success;

    /**
     * @OA\Property(
     *     title="Message",
     *     description="Error message",
     *     example="Password is incorrect."
     * )
     *
     * @var string
     */
    public $message;

    /**
     * @OA\Property(
     *     title="Data",
     *     description="Response data (empty on error)",
     *     type="array",
     *     @OA\Items(),
     *     example={}
     * )
     *
     * @var array
     */
    public $data;

    /**
     * @OA\Property(
     *     title="Errors",
     *     description="Validation or other errors",
     *     type="array",
     *     @OA\Items(type="string"),
     *     example={}
     * )
     *
     * @var array
     */
    public $errors;
} 