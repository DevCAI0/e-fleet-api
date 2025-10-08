<?php

namespace App\Http\Controllers;

use App\Models\UsuarioLocalizacao;
use App\Models\ChecklistVeicular;
use App\Models\Unidade;
use App\Models\User;
use App\Helpers\GeoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class UsuarioLocalizacaoController extends Controller
{
    const RAIO_PROXIMIDADE = 500; // 500 metros

    /**
     * Buscar técnicos no mapa
     */
    public function tecnicos(): JsonResponse
    {
        try {
            $tecnicos = UsuarioLocalizacao::with([
                'usuario:id,nome,celular',
                'unidadeAtual:id,numero_ordem,placa,lat,lon,id_empresa',
                'unidadeAtual.empresa:id,sigla'
            ])
            ->ativos()
            ->online(10)
            ->get();

            $tecnicosMapeados = $tecnicos->map(function($localizacao) {
                $usuario = $localizacao->usuario;
                $unidadeAtual = $localizacao->unidadeAtual;

                // Verificar se tem checklist ativo
                $checklistAtivo = null;
                if ($unidadeAtual) {
                    $checklistAtivo = ChecklistVeicular::where('id_unidade', $unidadeAtual->id)
                        ->where('id_user_analise', $localizacao->id_usuario)
                        ->where('finalizado', false)
                        ->with(['rpr'])
                        ->latest('data_analise')
                        ->first();
                }

                // Validar proximidade se tem checklist
                $estaProximoVeiculo = false;
                $distanciaCalculada = null;

                if ($checklistAtivo && $unidadeAtual && $unidadeAtual->lat && $unidadeAtual->lon) {
                    $distanciaCalculada = GeoHelper::calcularDistancia(
                        $localizacao->latitude,
                        $localizacao->longitude,
                        $unidadeAtual->lat,
                        $unidadeAtual->lon
                    );

                    $estaProximoVeiculo = $distanciaCalculada <= self::RAIO_PROXIMIDADE;

                    Log::debug('Verificando proximidade técnico-veículo', [
                        'tecnico' => $usuario->nome,
                        'veiculo' => $unidadeAtual->numero_ordem_formatado,
                        'distancia_metros' => $distanciaCalculada,
                        'esta_proximo' => $estaProximoVeiculo,
                        'raio_maximo' => self::RAIO_PROXIMIDADE
                    ]);

                    // Se não está próximo, limpar vínculo
                    if (!$estaProximoVeiculo) {
                        Log::warning('Técnico longe do veículo vinculado, limpando vínculo', [
                            'tecnico_id' => $localizacao->id_usuario,
                            'tecnico_nome' => $usuario->nome,
                            'unidade_id' => $unidadeAtual->id,
                            'distancia_metros' => $distanciaCalculada
                        ]);

                        $localizacao->update([
                            'id_unidade_atual' => null,
                            'tipo_atividade' => $localizacao->velocidade > 5 ? 'EM_DESLOCAMENTO' : 'PARADO'
                        ]);

                        $unidadeAtual = null;
                        $checklistAtivo = null;
                    }
                } elseif (!$checklistAtivo && $unidadeAtual) {
                    // Tem vínculo mas não tem checklist ativo
                    Log::warning('Técnico com vínculo mas sem checklist ativo, limpando', [
                        'tecnico_id' => $localizacao->id_usuario,
                        'tecnico_nome' => $usuario->nome,
                        'unidade_id' => $unidadeAtual->id
                    ]);

                    $localizacao->update([
                        'id_unidade_atual' => null,
                        'tipo_atividade' => $localizacao->velocidade > 5 ? 'EM_DESLOCAMENTO' : 'PARADO'
                    ]);

                    $unidadeAtual = null;
                }

                // Determinar tipo de atividade
                $tipoAtividadeReal = $this->determinarTipoAtividadeReal(
                    $localizacao,
                    $estaProximoVeiculo,
                    $checklistAtivo
                );

                $dados = [
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
                    'tipo_atividade' => $tipoAtividadeReal,
                    'data_atualizacao' => $localizacao->data_atualizacao->toIso8601String(),
                    'tempo_ultima_atualizacao' => $localizacao->getTempoUltimaAtualizacao(),
                    'online' => $localizacao->isOnline(),
                    'cor_marker' => $this->getCorMarker($tipoAtividadeReal),
                    'icone_marker' => $this->getIconeMarker($tipoAtividadeReal)
                ];

                // Adicionar unidade APENAS se tem checklist ativo E está próximo
                if ($checklistAtivo && $unidadeAtual && $estaProximoVeiculo) {
                    $problemas = [];
                    if ($checklistAtivo->rpr) {
                        $problemas = $checklistAtivo->rpr->getProblemasAtivos();
                    }

                    $dados['unidade_atual'] = [
                        'id' => $unidadeAtual->id,
                        'numero_ordem' => $unidadeAtual->numero_ordem_formatado,
                        'placa' => $unidadeAtual->placa,
                        'distancia_metros' => round($distanciaCalculada, 2),
                        'checklist' => [
                            'id' => $checklistAtivo->id,
                            'id_rpr' => $checklistAtivo->id_rpr,
                            'status' => $checklistAtivo->status_geral,
                            'data_inicio' => $checklistAtivo->data_analise->format('Y-m-d H:i:s'),
                            'problemas' => $problemas,
                            'total_problemas' => count($problemas),
                        ]
                    ];
                } else {
                    $dados['unidade_atual'] = null;
                }

                return $dados;
            });

            return response()->json([
                'status' => 'success',
                'data' => $tecnicosMapeados,
                'total' => $tecnicosMapeados->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar técnicos no mapa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar técnicos'
            ], 500);
        }
    }

    /**
     * Obter status atual do técnico logado
     */
    public function meuStatus(): JsonResponse
    {
        try {
            $localizacao = UsuarioLocalizacao::where('id_usuario', Auth::id())
                ->with([
                    'unidadeAtual:id,numero_ordem,placa,lat,lon',
                ])
                ->first();

            if (!$localizacao) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhuma localização registrada'
                ]);
            }

            // Verificar se existe checklist ativo
            $checklistAtivo = null;
            if ($localizacao->id_unidade_atual) {
                $checklistAtivo = ChecklistVeicular::where('id_unidade', $localizacao->id_unidade_atual)
                    ->where('id_user_analise', Auth::id())
                    ->where('finalizado', false)
                    ->latest('data_analise')
                    ->first();

                // Se não tem checklist ativo, limpar vínculo
                if (!$checklistAtivo) {
                    Log::info('Limpando vínculo - sem checklist ativo', [
                        'user_id' => Auth::id(),
                        'id_unidade_atual' => $localizacao->id_unidade_atual
                    ]);

                    $localizacao->update([
                        'id_unidade_atual' => null,
                        'tipo_atividade' => $localizacao->velocidade > 5 ? 'EM_DESLOCAMENTO' : 'PARADO'
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $localizacao->id,
                            'id_unidade_atual' => null,
                            'tipo_atividade' => $localizacao->tipo_atividade,
                            'ativo' => $localizacao->ativo,
                            'online' => $localizacao->isOnline(),
                            'esta_proximo_veiculo' => false,
                            'tem_checklist_ativo' => false,
                            'checklist_id' => null,
                            'unidade_atual' => null,
                        ]
                    ]);
                }
            }

            // Verificar proximidade se houver checklist
            $estaProximo = false;
            $distancia = null;

            if ($checklistAtivo && $localizacao->unidadeAtual) {
                $unidade = $localizacao->unidadeAtual;
                if ($unidade->lat && $unidade->lon) {
                    $distancia = GeoHelper::calcularDistancia(
                        $localizacao->latitude,
                        $localizacao->longitude,
                        $unidade->lat,
                        $unidade->lon
                    );

                    $estaProximo = $distancia <= self::RAIO_PROXIMIDADE;

                    Log::info('Verificando proximidade do técnico logado', [
                        'user_id' => Auth::id(),
                        'distancia_metros' => $distancia,
                        'esta_proximo' => $estaProximo,
                        'raio_maximo' => self::RAIO_PROXIMIDADE
                    ]);

                    // Se não está próximo, limpar vínculo
                    if (!$estaProximo) {
                        Log::warning('Técnico logado longe do veículo, limpando vínculo', [
                            'user_id' => Auth::id(),
                            'distancia_metros' => $distancia
                        ]);

                        $localizacao->update([
                            'id_unidade_atual' => null,
                            'tipo_atividade' => $localizacao->velocidade > 5 ? 'EM_DESLOCAMENTO' : 'PARADO'
                        ]);

                        return response()->json([
                            'success' => true,
                            'data' => [
                                'id' => $localizacao->id,
                                'id_unidade_atual' => null,
                                'tipo_atividade' => $localizacao->tipo_atividade,
                                'ativo' => $localizacao->ativo,
                                'online' => $localizacao->isOnline(),
                                'esta_proximo_veiculo' => false,
                                'tem_checklist_ativo' => true,
                                'checklist_id' => $checklistAtivo->id,
                                'unidade_atual' => null,
                                'distancia_metros' => round($distancia, 2),
                                'motivo' => 'Técnico muito longe do veículo (máx: 500m)'
                            ]
                        ]);
                    }
                }
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $localizacao->id,
                    'id_unidade_atual' => ($checklistAtivo && $estaProximo) ? $localizacao->id_unidade_atual : null,
                    'tipo_atividade' => $this->determinarTipoAtividadeReal($localizacao, $estaProximo, $checklistAtivo),
                    'ativo' => $localizacao->ativo,
                    'online' => $localizacao->isOnline(),
                    'esta_proximo_veiculo' => $estaProximo,
                    'tem_checklist_ativo' => $checklistAtivo ? true : false,
                    'checklist_id' => $checklistAtivo?->id,
                    'distancia_metros' => $distancia ? round($distancia, 2) : null,
                    'unidade_atual' => ($checklistAtivo && $estaProximo && $localizacao->unidadeAtual) ? [
                        'id' => $localizacao->unidadeAtual->id,
                        'numero_ordem' => $localizacao->unidadeAtual->numero_ordem_formatado,
                        'placa' => $localizacao->unidadeAtual->placa,
                    ] : null,
                ]
            ];

            Log::info('Status do técnico consultado', [
                'user_id' => Auth::id(),
                'tem_checklist' => $checklistAtivo ? true : false,
                'esta_proximo' => $estaProximo,
                'distancia_metros' => $distancia,
                'id_unidade_vinculada' => $localizacao->id_unidade_atual
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Erro ao obter status do técnico', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter status'
            ], 500);
        }
    }

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

            // Verificar se tem checklist ativo antes de aceitar vínculo
            if (isset($dados['id_unidade_atual']) && $dados['id_unidade_atual']) {
                $checklistAtivo = ChecklistVeicular::where('id_unidade', $dados['id_unidade_atual'])
                    ->where('id_user_analise', $idUsuario)
                    ->where('finalizado', false)
                    ->first();

                if (!$checklistAtivo) {
                    Log::warning('Tentativa de vincular sem checklist ativo', [
                        'user_id' => $idUsuario,
                        'id_unidade' => $dados['id_unidade_atual']
                    ]);
                    $dados['id_unidade_atual'] = null;
                } else {
                    // Verificar proximidade
                    $unidade = Unidade::find($dados['id_unidade_atual']);
                    if ($unidade && $unidade->lat && $unidade->lon) {
                        $distancia = GeoHelper::calcularDistancia(
                            $dados['latitude'],
                            $dados['longitude'],
                            $unidade->lat,
                            $unidade->lon
                        );

                        if ($distancia > self::RAIO_PROXIMIDADE) {
                            Log::warning('Tentativa de vincular estando longe do veículo', [
                                'user_id' => $idUsuario,
                                'id_unidade' => $dados['id_unidade_atual'],
                                'distancia_metros' => $distancia,
                                'raio_maximo' => self::RAIO_PROXIMIDADE
                            ]);
                            $dados['id_unidade_atual'] = null;
                        }
                    }
                }
            }

            // Validar vínculo existente
            if ($localizacao && $localizacao->id_unidade_atual && !isset($dados['id_unidade_atual'])) {
                $checklistAtivo = ChecklistVeicular::where('id_unidade', $localizacao->id_unidade_atual)
                    ->where('id_user_analise', $idUsuario)
                    ->where('finalizado', false)
                    ->first();

                if ($checklistAtivo) {
                    $unidade = Unidade::find($localizacao->id_unidade_atual);
                    if ($unidade && $unidade->lat && $unidade->lon) {
                        $distancia = GeoHelper::calcularDistancia(
                            $dados['latitude'],
                            $dados['longitude'],
                            $unidade->lat,
                            $unidade->lon
                        );

                        if ($distancia > self::RAIO_PROXIMIDADE) {
                            Log::info('Técnico se afastou do veículo, removendo vínculo', [
                                'user_id' => $idUsuario,
                                'distancia_metros' => $distancia
                            ]);
                            $dados['id_unidade_atual'] = null;
                        } else {
                            $dados['id_unidade_atual'] = $localizacao->id_unidade_atual;
                        }
                    }
                } else {
                    $dados['id_unidade_atual'] = null;
                }
            }

            $tipoAtividade = $this->determinarTipoAtividade($dados, $localizacao);

            if ($localizacao) {
                $localizacao->update([
                    'latitude' => $dados['latitude'],
                    'longitude' => $dados['longitude'],
                    'velocidade' => $dados['velocidade'] ?? 0,
                    'endereco' => $dados['endereco'] ?? $localizacao->endereco,
                    'precisao' => $dados['precisao'] ?? $localizacao->precisao,
                    'tipo_atividade' => $tipoAtividade,
                    'id_unidade_atual' => $dados['id_unidade_atual'] ?? null,
                    'data_atualizacao' => now(),
                    'ativo' => true
                ]);
            } else {
                $localizacao = UsuarioLocalizacao::create([
                    'id_usuario' => $idUsuario,
                    'latitude' => $dados['latitude'],
                    'longitude' => $dados['longitude'],
                    'velocidade' => $dados['velocidade'] ?? 0,
                    'endereco' => $dados['endereco'] ?? null,
                    'precisao' => $dados['precisao'] ?? null,
                    'tipo_atividade' => $tipoAtividade,
                    'id_unidade_atual' => $dados['id_unidade_atual'] ?? null,
                    'data_atualizacao' => now(),
                    'ativo' => true
                ]);
            }

            Log::info('Localização do técnico atualizada', [
                'user_id' => $idUsuario,
                'tipo_atividade' => $tipoAtividade,
                'id_unidade_atual' => $localizacao->id_unidade_atual,
                'velocidade' => $dados['velocidade'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Localização atualizada com sucesso',
                'data' => [
                    'id' => $localizacao->id,
                    'tipo_atividade' => $localizacao->tipo_atividade,
                    'id_unidade_atual' => $localizacao->id_unidade_atual,
                    'online' => $localizacao->isOnline()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar localização do usuário', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar localização'
            ], 500);
        }
    }

    // ... (demais métodos permanecem iguais)

    /**
     * Determinar tipo de atividade REAL baseado em checklist e proximidade
     */
    private function determinarTipoAtividadeReal($localizacao, bool $estaProximoVeiculo, $checklistAtivo): string
    {
        // Tem checklist ativo E está próximo = Trabalhando
        if ($checklistAtivo && $estaProximoVeiculo) {
            return 'NO_LOCAL';
        }

        // Está se movendo
        if ($localizacao->velocidade > 5) {
            return 'EM_DESLOCAMENTO';
        }

        // Está parado
        return 'PARADO';
    }

    private function determinarTipoAtividade(array $dados, $localizacaoAtual = null): string
    {
        $velocidade = $dados['velocidade'] ?? 0;
        $idUnidadeAtual = $dados['id_unidade_atual'] ?? null;

        if ($idUnidadeAtual) {
            return 'NO_LOCAL';
        }

        if ($localizacaoAtual && $localizacaoAtual->id_unidade_atual && !isset($dados['id_unidade_atual'])) {
            return 'NO_LOCAL';
        }

        if ($velocidade > 5) {
            return 'EM_DESLOCAMENTO';
        }

        return 'PARADO';
    }

    private function getCorMarker(string $tipoAtividade): string
    {
        switch ($tipoAtividade) {
            case 'EM_DESLOCAMENTO':
                return '#3B82F6';
            case 'NO_LOCAL':
                return '#F59E0B';
            case 'PARADO':
                return '#6B7280';
            default:
                return '#EF4444';
        }
    }

    private function getIconeMarker(string $tipoAtividade): string
    {
        switch ($tipoAtividade) {
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
