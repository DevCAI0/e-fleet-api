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
                'message' => 'Usuário inativo'
            ], 401);
        }

        // Gerar token
        $deviceName = $request->device_name ?? 'e-fleet-app';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login realizado com sucesso',
            'data' => [
                'user' => [
                    'id' => $user->id_user,
                    'nome' => $user->nome,
                    'login' => $user->login,
                    'setor_user' => $user->setor_user,
                    'ativo' => $user->ativo,
                    'permissoes' => $user->permissoes,
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
                    'id' => $user->id_user,
                    'nome' => $user->nome,
                    'login' => $user->login,
                    'setor_user' => $user->setor_user,
                    'ativo' => $user->ativo,
                    'permissoes' => $user->permissoes,
                ]
            ]
        ]);
    }

    /**
     * Verificar se usuário tem uma permissão específica
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'permission' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $hasPermission = $user->hasPermissao($request->permission);

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_permission' => $hasPermission,
                'permission' => $request->permission,
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
