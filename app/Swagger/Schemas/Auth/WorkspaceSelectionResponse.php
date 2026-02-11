<?php

namespace App\Swagger\Schemas\Auth;

use App\Swagger\Schemas\Common\BaseResponse;

/**
 * @OA\Schema(
 *     title="WorkspaceSelectionResponse",
 *     description="Response when workspace selection is required",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/BaseResponse")
 *     }
 * )
 */
class WorkspaceSelectionResponse extends BaseResponse
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
     *     description="Workspace selection required message",
     *     example="Please select a workspace to continue."
     * )
     *
     * @var string
     */
    public $message;

    /**
     * @OA\Property(
     *     title="Data",
     *     description="Available workspaces for selection",
     *     type="object",
     *     @OA\Property(
     *         property="workspace",
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Main Branch"),
     *             @OA\Property(property="type", type="string", example="App\\Models\\Branch")
     *         )
     *     )
     * )
     *
     * @var object
     */
    public $data;

    /**
     * @OA\Property(
     *     title="Errors",
     *     description="Validation errors (empty for workspace selection)",
     *     type="array",
     *     @OA\Items(type="string"),
     *     example={}
     * )
     *
     * @var array
     */
    public $errors;
} 