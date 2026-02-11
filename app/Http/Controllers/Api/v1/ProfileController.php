<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="WMS", description="Profile Management")
 * @OA\Server(url="/api")
 */
class ProfileController extends Controller
{
    public function index()
    {
        return sendResponse("Profile retrieved successfully", new SettingResource(Auth::user()));
    }
    public function update(UpdateSettingRequest $request)
    {
        $request->validated();
        $logo = uploadFile($request->logo, 'settings');
        $profile = User::find(1);
        $profile->update([
            "name" => $request->name,
            "email" => $request->email,
            "logo" => $logo
        ]);
        return sendResponse("Profile updated successfully", new SettingResource($profile));
    }
    public function update_password(UpdatePasswordRequest $request)
    {
        $request->validated();
        $user = User::find(Auth::id());
        if (Hash::check($request->current_password, $user->password)) {
            $user->update(["password" => Hash::make($request->password)]);
            $user->save();
            return sendResponse("Password changed successfully.", []);
        }
        return sendResponse("", [], ["Password is wrong, please type your password carefully."], 422);
    }
}
