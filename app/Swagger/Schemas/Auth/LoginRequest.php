<?php

namespace App\Swagger\Schemas\Auth;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     title="LoginRequest",
 *     description="Login request payload",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Xml(name="LoginRequest")
 * )
 */
class LoginRequest
{
    /**
     * @OA\Property(
     *     property="email",
     *     title="Email",
     *     description="User's email address",
     *     type="string",
     *     format="email",
     *     example="admin@gmail.com"
     * )
     *
     * @var string
     */
    public $email;

    /**
     * @OA\Property(
     *     property="password",
     *     title="Password",
     *     description="User's password",
     *     type="string",
     *     format="password",
     *     example="12345678"
     * )
     *
     * @var string
     */
    public $password;

    /**
     * @OA\Property(
     *     property="workspace",
     *     title="Workspace",
     *     description="Optional workspace selection string (e.g. encrypted workspace JSON)",
     *     type="string",
     *     example="ENCRYPTED_WORKSPACE_STRING",
     *     nullable=true
     * )
     *
     * @var string|null
     */
    public $workspace;
}
