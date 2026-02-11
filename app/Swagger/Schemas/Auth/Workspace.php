<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     title="Workspace",
 *     description="Workspace information",
 *     @OA\Xml(name="Workspace")
 * )
 */
class Workspace
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="Encrypted workspace ID",
     *     example="eyJ0eXA..."
     * )
     *
     * @var string
     */
    public $id;

    /**
     * @OA\Property(
     *     title="Name",
     *     description="Workspace name",
     *     example="Main Branch"
     * )
     *
     * @var string
     */
    public $name;

    /**
     * @OA\Property(
     *     title="Type",
     *     description="Model class type",
     *     example="App\\Models\\Branch"
     * )
     *
     * @var string
     */
    public $type;
} 