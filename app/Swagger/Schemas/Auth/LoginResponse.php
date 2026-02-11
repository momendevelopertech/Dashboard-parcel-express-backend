<?php

namespace App\Swagger\Schemas\Auth;

use App\Swagger\Schemas\Common\BaseResponse;

/**
 * @OA\Schema(
 *     title="LoginResponse",
 *     description="Successful login response",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BaseResponse")
 *     }
 * )
 */
class LoginResponse extends BaseResponse
{
    /**
     * @OA\Property(
     *     title="Success",
     *     description="Login success status",
     *     example=true
     * )
     *
     * @var boolean
     */
    public $success;

    /**
     * @OA\Property(
     *     title="Message",
     *     description="Login success message",
     *     example="Login successful."
     * )
     *
     * @var string
     */
    public $message;

    /**
     * @OA\Property(
     *     title="Data",
     *     description="User authentication data",
     *     ref="#/components/schemas/User"
     * )
     *
     * @var \App\Swagger\Schemas\Auth\User
     */
    public $data;

    /**
     * @OA\Property(
     *     title="Errors",
     *     description="Validation errors (empty on success)",
     *     type="array",
     *     @OA\Items(type="string"),
     *     example={}
     * )
     *
     * @var array
     */
    public $errors;
} 