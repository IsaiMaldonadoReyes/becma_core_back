<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class AuthController extends Controller
{

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required'],
            'password' => ['required'],
        ]);

        $user = User::create($data);

        $token = $user->createToken($request->name);

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        if (Auth::guard()->attempt(['email' => $credentials['user'], 'password' => $credentials['password']])) {
            $user = Auth::guard()->user();

            if ($response = $this->authenticated($request, Auth::guard()->user())) {
                return $response;
            }
        }
    }

    protected function authenticated(Request $request, $user)
    {
        $userLoged = DB::table('users')
            ->where('users.id', '=', $user->id)
            ->select(
                'users.name',
                'users.email',
                'roles.nombre',
                'roles.ruta'
            )
            ->first();

        if ($userLoged != null) {
            return response()->json($userLoged);
        }
    }
}
