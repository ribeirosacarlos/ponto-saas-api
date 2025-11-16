<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index()
    {
        return User::paginate(20);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'company_id' => $request->user()->company_id,
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
        ]);

        if ($request->role) {
            $user->assignRole($request->role);
        }

        return response()->json($user, 201);
    }

    public function show($id)
    {
        return User::findOrFail($id);
    }

    public function update($id, Request $request)
    {
        $user = User::findOrFail($id);

        $user->update($request->only(['name','email']));

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json($user);
    }

    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'Deletado']);
    }
}
