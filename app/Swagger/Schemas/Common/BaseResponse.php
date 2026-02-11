<?php

namespace App\Swagger\Schemas\Common;

/**
 * @OA\Schema(
 *     title="BaseResponse",
 *     description="Standard API response format",
 *     @OA\Xml(name="BaseResponse")
 * )
 */
class BaseResponse
{
    /**
     * @OA\Property(
     *     title="Success",
     *     description="Indicates if the request was successful",
     *     example=true
     * )
     *
     * @var boolean
     */
    public $success;

    /**
     * @OA\Property(
     *     title="Message",
     *     description="Response message",
     *     example="Operation completed successfully"
     * )
     *
     * @var string
     */
    public $message;

    /**
     * @OA\Property(
     *     title="Data",
     *     description="Response data",
     *     type="object"
     * )
     *
     * @var mixed
     */
    public $data;

    /**
     * @OA\Property(
     *     title="Errors",
     *     description="Validation or other errors",
     *     type="array",
     *     @OA\Items(type="string")
     * )
     *
     * @var array
     */
    public $errors;
} 