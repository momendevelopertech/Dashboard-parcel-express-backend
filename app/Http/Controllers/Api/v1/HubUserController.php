<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class HubUserController extends Controller
{
    public function index()
    {
        $users = User::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $users = $users
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with('roles')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $users = $users->where('id', '!=', Auth::id())->with('roles')->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Users reterived successfully.", new UserResource($users), []);
    }

    public function store(StoreUserRequest $request)
    {
        $request->validated();
        try {

            // $image = uploadFile($request->image, 'users/images');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                // 'image' => $image,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $role = Role::find($request->role);
            $user->syncRoles($role);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User created successfully.", [$user->load('roles')]);
    }

    public function edit($id)
    {
        $user = User::with("roles")->find($id);
        return sendResponse("Role", new UserResource($user));
    }

    public function update(UpdateUserRequest $request)
    {
        $request->validated();
        try {
            $user = User::find($request->id);
            $data = $request->all();
            if ($request->image) {
                $image = uploadFile($request->image, 'public/users/images');
                $data['image'] = $image;
            } else {
                $data['image'] = $user->image;
            }

            $user->update($data);
            $role = Role::find($request->role);
            $user->syncRoles($role);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return sendResponse("User updated successfully.", [$user]);
    }

    public function delete(Request $request)
    {
        try {
            $user = User::find($request->id);
            $user->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User deleted successfully.", []);
    }

    public function change_password(Request $request)
    {
        $request->validate([
            'password' => ['required', Password::defaults()],
            'id' => 'required'
        ]);

        try {
            $user = User::find($request->id);
            $user->password = Hash::make($request->password);
            $user->save();
            return response()->json(['message' => 'Password changed successfully.']);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return response()->json(['message' => 'Current password is incorrect.'], 422);
    }
}
