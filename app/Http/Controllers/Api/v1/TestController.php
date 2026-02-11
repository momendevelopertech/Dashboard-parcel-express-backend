<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Kolirt\Openstreetmap\Facade\Openstreetmap;

class TestController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/test",
     *     summary="Testing.",
     *     description="Just for Testing purposes.",
     *     tags={"Test"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         required=true,
     *         description="Bearer access token, e.g. `Bearer eyJ0eXAiOiJKV1Qi…`",
     *         @OA\Schema(type="string", format="jwt", example="Bearer {token}")
     *     ),
     *     @OA\Parameter(
     *         name="X-Workspace-Key",
     *         in="header",
     *         required=true,
     *         description="Encrypted workspace identifier (Header set by merchant)",
     *         @OA\Schema(type="string", example="eyJpdiI6Ij…")
     *     ),
     *     @OA\Parameter(
     *         name="X-Workspace-Type",
     *         in="header",
     *         required=true,
     *         description="Workspace type, e.g. `Branch`, `Station` or `Hub`",
     *         @OA\Schema(type="string", example="Branch")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Testing."
     *     )
     * )
     */
    public function test()
    {
        dd(generate_otp());
    }
}
