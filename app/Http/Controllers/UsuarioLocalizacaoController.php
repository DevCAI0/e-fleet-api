<?php

namespace App\Http\Controllers;

use App\Models\UsuarioLocalizacao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class UsuarioLocalizacaoController extends Controller
{
    /**
     * Atualizar localização do usuário
     */
    public function atualizarLocalizacao(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'velocidade' => 'nullable|numeric|min:0',
            'endereco' => 'nullable|string|max:500',
            'precisao' => 'nullable|numeric|min:0',
            'id_unidade_atual' => 'nullable|exists:unidades,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $idUsuario = Auth::id();
            $dados = $validator->validated();

            $localizacao = UsuarioLocalizacao::where('id_usuario', $idUsuario)->first();

            if ($localizacao) {
                $localizacao->atualizarLocalizacao($dados);
            } else {
                $localizacao = UsuarioLocalizacao::create([
                    'id_usuario' => $idUsuario,
                    'latitude' => $dados['latitude'],
                    'longitude' => $dados['longitude'],
                    'velocidade' => $dados['velocidade'] ?? 0,
                    'endereco' => $dados['endereco'] ?? null,
                    'precisao' => $dados['precisao'] ?? null,
                    'tipo_atividade' => $this->determinarTipoAtividade($dados),
                    'id_unidade_atual' => $dados['id_unidade_atual'] ?? null,
                    'data_atualizacao' => now(),
                    'ativo' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Localização atualizada com sucesso',
                'data' => [
                    'id' => $localizacao->id,
                    'tipo_atividade' => $localizacao->tipo_atividade,
                    'online' => $localizacao->isOnline()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar localização do usuário', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar localização'
            ], 500);
        }
    }

    /**
     * Buscar técnicos no mapa
     */
    public function tecnicos(): JsonResponse
    {
        try {
            $tecnicos = UsuarioLocalizacao::with([
                'usuario:id,nome,celular',
                'unidadeAtual:id,numero_ordem,placa,id_empresa',
                'unidadeAtual.empresa:id,sigla'
            ])
            ->ativos()
            ->online(10) // Online nos últimos 10 minutos
            ->get();

            $tecnicosMapeados = $tecnicos->map(function($localizacao) {
                $usuario = $localizacao->usuario;
                $unidadeAtual = $localizacao->unidadeAtual;

                return [
                    'id' => $localizacao->id,
                    'id_usuario' => $usuario->id,
                    'nome' => $usuario->nome,
                    'celular' => $usuario->celular,
                    'posicao' => [
                        'latitude' => (float) $localizacao->latitude,
                        'longitude' => (float) $localizacao->longitude,
                    ],
                    'velocidade' => (float) $localizacao->velocidade,
                    'endereco' => $localizacao->endereco,
                    'precisao' => (float) $localizacao->precisao,
                    'tipo_atividade' => $localizacao->tipo_atividade,
                    'unidade_atual' => $unidadeAtual ? [
                        'id' => $unidadeAtual->id,
                        'numero_ordem' => $unidadeAtual->numero_ordem_formatado,
                        'placa' => $unidadeAtual->placa
                    ] : null,
                    'data_atualizacao' => $localizacao->data_atualizacao->toIso8601String(),
                    'tempo_ultima_atualizacao' => $localizacao->getTempoUltimaAtualizacao(),
                    'online' => $localizacao->isOnline(),
                    'cor_marker' => $this->getCorMarker($localizacao),
                    'icone_marker' => $this->getIconeMarker($localizacao)
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $tecnicosMapeados,
                'total' => $tecnicosMapeados->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar técnicos no mapa', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar técnicos'
            ], 500);
        }
    }

    /**
     * Desativar localização (usuário saiu do app)
     */
    public function desativar(): JsonResponse
    {
        try {
            $localizacao = UsuarioLocalizacao::where('id_usuario', Auth::id())->first();

            if ($localizacao) {
                $localizacao->update([
                    'ativo' => false,
                    'tipo_atividade' => 'OFFLINE'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Localização desativada'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao desativar localização', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao desativar localização'
            ], 500);
        }
    }

    /**
     * Vincular técnico a um veículo
     */
    public function vincularVeiculo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_unidade' => 'required|exists:unidades,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $localizacao = UsuarioLocalizacao::where('id_usuario', Auth::id())->first();

            if (!$localizacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Localização não encontrada. Ative o rastreamento primeiro.'
                ], 404);
            }

            $localizacao->update([
                'id_unidade_atual' => $request->id_unidade,
                'tipo_atividade' => 'NO_LOCAL'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Técnico vinculado ao veículo'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao vincular técnico ao veículo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao vincular técnico'
            ], 500);
        }
    }

    /**
     * Desvincular técnico do veículo
     */
    public function desvincularVeiculo(): JsonResponse
    {
        try {
            $localizacao = UsuarioLocalizacao::where('id_usuario', Auth::id())->first();

            if ($localizacao) {
                $localizacao->update([
                    'id_unidade_atual' => null,
                    'tipo_atividade' => $localizacao->velocidade > 5 ? 'EM_DESLOCAMENTO' : 'PARADO'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Técnico desvinculado do veículo'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao desvincular técnico', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao desvincular técnico'
            ], 500);
        }
    }

    // ========================================
    // MÉTODOS PRIVADOS
    // ========================================

    private function determinarTipoAtividade(array $dados): string
    {
        $velocidade = $dados['velocidade'] ?? 0;

        if ($velocidade > 5) {
            return 'EM_DESLOCAMENTO';
        }

        if (isset($dados['id_unidade_atual']) && $dados['id_unidade_atual']) {
            return 'NO_LOCAL';
        }

        return 'PARADO';
    }

    private function getCorMarker(UsuarioLocalizacao $localizacao): string
    {
        switch ($localizacao->tipo_atividade) {
            case 'EM_DESLOCAMENTO':
                return '#3B82F6'; // Azul - em movimento
            case 'NO_LOCAL':
                return '#F59E0B'; // Laranja - atendendo veículo
            case 'PARADO':
                return '#6B7280'; // Cinza - parado
            default:
                return '#EF4444'; // Vermelho - offline
        }
    }

    private function getIconeMarker(UsuarioLocalizacao $localizacao): string
    {
        switch ($localizacao->tipo_atividade) {
            case 'EM_DESLOCAMENTO':
                return 'directions-walk';
            case 'NO_LOCAL':
                return 'build';
            case 'PARADO':
                return 'person-pin';
            default:
                return 'person-off';
        }
    }
}
