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

        if (Auth::guard()->attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            $user = Auth::guard()->user();

            if ($response = $this->authenticated($request, $user)) {
                return $response;
            }
        } else {
            return response()->json(['error' => 'Credenciales inválidas'], 422);
        }
    }

    protected function authenticated(Request $request, $user)
    {
        $userLoged = DB::table('users')
            ->where('users.id', '=', $user->id)
            ->select(
                'users.name',
                'users.email',
            )
            ->first();

        if ($userLoged != null) {
            return response()->json($userLoged);
        }
    }

    protected function authDirectories(Request $request)
    {
        $directories = DB::table('users')
            ->join('core_usuario_rol', 'users.id', '=', 'core_usuario_rol.id_usuario')
            ->join('core_rol', 'core_usuario_rol.id_rol', '=', 'core_rol.id')
            ->join('core_directorio_rol', 'core_rol.id', '=', 'core_directorio_rol.id_rol')
            ->join('core_directorio', 'core_directorio_rol.id_core_directorio', '=', 'core_directorio.id')
            ->where('users.id', '=', $request->user()->id)
            ->where('core_directorio.id_padre', '>', 0)
            ->selectRaw("
                core_directorio.descripcion AS sistema
                , core_directorio.ruta AS ruta
            ")
            ->get();

        return $directories;
    }

    protected function authUserInformation(Request $request)
    {
        $user = DB::table('users')
            ->join('core_usuario_rol', 'users.id', '=', 'core_usuario_rol.id_usuario')
            ->join('core_rol', 'core_usuario_rol.id_rol', '=', 'core_rol.id')
            ->where('users.id', '=', $request->user()->id)
            ->selectRaw("
                users.name AS nombre
                , users.apellido_parteno AS ap
                , users.apellido_materno AS am
                , users.name + ' ' + COALESCE(users.apellido_parteno, ' ') + ' ' + COALESCE(users.apellido_materno, ' ') AS nombreCompleto
                , SUBSTRING(ISNULL(users.name, ''),1,1) + SUBSTRING(ISNULL(users.apellido_parteno, ''), 1,1) AS iniciales
                , users.imagen AS imagen
            ")->first();

        return response()->json($user);
    }
}
