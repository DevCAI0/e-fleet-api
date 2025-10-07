<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login do usuário
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Buscar usuário por login
        $user = User::where('login', strtolower($request->login))->first();

        // Verificar se usuário existe e senha está correta
        if (!$user || !Hash::check($request->password, $user->senha)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciais inválidas'
            ], 401);
        }

        // Verificar se usuário está ativo
        if (!$user->isAtivo()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário inativo ou bloqueado'
            ], 401);
        }

        // Gerar token
        $deviceName = $request->device_name ?? 'e-fleet-app';
        $token = $user->createToken($deviceName)->plainTextToken;

        // Atualizar último login
        $user->update([
            'ultimo_login' => now(),
            'ip_acesso' => $request->ip()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login realizado com sucesso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'login' => $user->login,
                    'email' => $user->email,
                    'tipo_usuario' => $user->tipo_usuario,
                    'id_empresa' => $user->id_empresa,
                    'acessa_control' => $user->acessa_control,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Logout do usuário
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout realizado com sucesso'
        ]);
    }

    /**
     * Informações do usuário autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'login' => $user->login,
                    'email' => $user->email,
                    'tipo_usuario' => $user->tipo_usuario,
                    'id_empresa' => $user->id_empresa,
                    'status' => $user->status,
                ]
            ]
        ]);
    }

    /**
     * Verificar se usuário tem permissão de app
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'app' => 'required|string|in:conferencia,rastreamento,passagem,vigilante',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $hasPermission = $user->hasPermissaoApp($request->app);

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_permission' => $hasPermission,
                'app' => $request->app,
            ]
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido'
            ], 401);
        }

        // Revogar token atual
        $request->user()->currentAccessToken()->delete();

        // Criar novo token
        $deviceName = $request->device_name ?? 'e-fleet-app';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Token renovado com sucesso',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }
}
