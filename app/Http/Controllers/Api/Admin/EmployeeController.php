<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\EmployeeStoreRequest;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Admin can list all employees of the company
        return User::where('company_id', $request->user()->company_id)->paginate(20);
    }

    public function store(EmployeeStoreRequest $request)
    {
        $this->authorize('create', User::class);

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
        $employee = User::findOrFail($id);
        $this->authorize('view', $employee);

        return $employee;
    }

    public function update($id, EmployeeStoreRequest $request)
    {
        $employee = User::findOrFail($id);
        $this->authorize('update', $employee);

        $employee->update($request->only(['name','email']));

        if ($request->password) {
            $employee->update(['password' => Hash::make($request->password)]);
        }

        return $employee;
    }

    public function destroy($id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->json(['message' => 'Deletado']);
    }
}
