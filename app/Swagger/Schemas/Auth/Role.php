<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     title="Role",
 *     description="User role information",
 *     @OA\Xml(name="Role")
 * )
 */
class Role
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="Role ID",
     *     format="int64",
     *     example=5
     * )
     *
     * @var integer
     */
    public $id;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="Role name",
     *     example="Driver"
     * )
     *
     * @var string
     */
    public $name;
} 