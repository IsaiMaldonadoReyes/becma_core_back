<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Requests\core\UpdatePasswordRequest;
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

    public function logout(Request $request)
    {
        Auth::guard('web')->logout(); // O simplemente $this->guard()->logout()

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
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
                , users.apellido_parteno AS apellidoPaterno
                , users.apellido_materno AS apellidoMaterno
                , users.name + ' ' + COALESCE(users.apellido_parteno, ' ') + ' ' + COALESCE(users.apellido_materno, ' ') AS nombreCompleto
                , SUBSTRING(ISNULL(users.name, ''),1,1) + SUBSTRING(ISNULL(users.apellido_parteno, ''), 1,1) AS iniciales
                , users.imagen AS imagen
                , users.email AS correo
                , users.id AS id
                , core_rol.nombre AS rol
            ")->first();

        return response()->json($user);
    }

    protected function resetPassword(UpdatePasswordRequest $request)
    {
        try {

            $user = $request->user();

            $user->password = bcrypt($request->password);
            $user->save();

            return response()->json([
                'code' => 200,
                'message' => 'Contraseña actualizada correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al actualizar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
