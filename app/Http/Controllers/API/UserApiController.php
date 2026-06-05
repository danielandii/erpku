<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Hanya admin yang bisa melihat semua user
        // if (!auth()->user()->role_id==1) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        return User::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // if (!auth()->user()->role_id==1) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'username' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', Rules\Password::defaults()],
            'role_id' => 'integer'
        ]);

        $cek = User::where('username',$request->name)->first();
        if ($cek) {
            return response()->json(['message' => 'Username Already Exist'], 200);
        }

        $user = User::create([
            'username' => $request->name,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // User hanya bisa melihat profil sendiri atau admin bisa melihat semua
        // if (auth()->id() !== $user->id && !auth()->user()->role_id==1) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        return $user;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // User hanya bisa update profil sendiri atau admin bisa update semua
        // if (auth()->id() !== $user->id && !auth()->user()->role_id==1) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'username' => 'required|string|max:255',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255,email,'.$user->id,
            'password' => ['sometimes', Rules\Password::defaults()],
            'role_id' => 'integer'
        ]);

        $data = $request->all();

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Hanya admin yang bisa mengubah status admin
        // if (!$auth->user()->role_id==1) {
        //     unset($data['is_admin']);
        // }

        $user->update($data);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // if (!auth()->user()->role_id==1) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $user->delete();

        return response()->json(null, 204);
    }
}