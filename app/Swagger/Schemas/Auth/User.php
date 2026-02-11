<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     title="User",
 *     description="User model for authentication",
 *     @OA\Xml(name="User")
 * )
 */
class User
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="User ID",
     *     format="int64",
     *     example=9
     * )
     *
     * @var integer
     */
    public $id;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="User's full name",
     *     example="driver"
     * )
     *
     * @var string
     */
    public $name;

    /**
     * @OA\Property(
     *     title="Email",
     *     description="User's email address",
     *     format="email",
     *     example="driver@gmail.com"
     * )
     *
     * @var string
     */
    public $email;

    /**
     * @OA\Property(
     *     title="Token",
     *     description="Personal access token for API authentication",
     *     example="32|mgoHW19Sijuy94c2Nl..."
     * )
     *
     * @var string
     */
    public $token;

    /**
     * @OA\Property(
     *     title="Workspace",
     *     description="Selected workspace information",
     *     ref="#/components/schemas/Workspace"
     * )
     *
     * @var \App\Swagger\Schemas\Auth\Workspace
     */
    public $workspace;

    /**
     * @OA\Property(
     *     title="Settings",
     *     description="User settings",
     *     type="array",
     *     @OA\Items(type="object")
     * )
     *
     * @var array
     */
    public $settings;

    /**
     * @OA\Property(
     *     title="Role",
     *     description="User role information",
     *     ref="#/components/schemas/Role"
     * )
     *
     * @var \App\Swagger\Schemas\Auth\Role
     */
    public $role;
} 