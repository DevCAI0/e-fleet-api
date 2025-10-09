<?php

namespace App\Http\Controllers;

use App\Models\Rpr;
use App\Models\RprTipo;
use App\Models\RprCiclo;
use App\Models\RprCicloVeiculo;
use App\Models\Unidade;
use App\Models\ChecklistVeicular;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RprController extends Controller
{
    // ========================================
    // TIPOS E CORREÇÕES
    // ========================================

    /**
     * Retorna tipos e correções ativos para o formulário
     */
    public function tiposCorrecoes()
    {
        try {
            $tipos = RprTipo::ativos()
                ->ordenados()
                ->with('correcoesAtivas')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'tipos' => $tipos->map(function ($tipo) {
                        return $tipo->toArrayApi();
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar tipos e correções', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar tipos e correções'
            ], 500);
        }
    }

    // ========================================
    // SALVAR RPR (FORMULÁRIO MANUAL)
    // ========================================

    /**
     * Salva um RPR (usado no formulário manual)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_unidade' => 'required|exists:unidades,id',
            'status_t1' => 'required|in:S,N',
            'status_t2' => 'required|in:S,N',
            'status_t3' => 'required|in:S,N',
            'status_t4' => 'required|in:S,N',
            'status_t5' => 'required|in:S,N',
            'status_t6' => 'required|in:S,N',
            'status_t7' => 'required|in:S,N',
            'status_t8' => 'required|in:S,N',
            'status_t9' => 'required|in:S,N',
            'status_t10' => 'required|in:S,N',
            'status_t11' => 'required|in:S,N',
            'cor_t1' => 'nullable|string|max:255',
            'cor_t2' => 'nullable|string|max:255',
            'cor_t3' => 'nullable|string|max:255',
            'cor_t4' => 'nullable|string|max:255',
            'cor_t5' => 'nullable|string|max:255',
            'cor_t6' => 'nullable|string|max:255',
            'cor_t7' => 'nullable|string|max:255',
            'cor_t9' => 'nullable|string|max:255',
            'cor_t10' => 'nullable|string|max:255',
            'observacao' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['id_user'] = Auth::id();
            $data['data_cadastro'] = now();

            $rpr = Rpr::create($data);

            return response()->json([
                'success' => true,
                'message' => 'RPR salvo com sucesso',
                'data' => [
                    'id' => $rpr->id,
                    'id_unidade' => $rpr->id_unidade,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar RPR', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar RPR'
            ], 500);
        }
    }

    // ========================================
    // CICLO ATIVO
    // ========================================

    /**
     * Retorna o ciclo ativo atual
     */
    public function cicloAtivo()
    {
        try {
            $ciclo = RprCiclo::with([
                'veiculos.unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'veiculos.unidade.modulo:id,serial',
                'veiculos.unidade.empresa:id,sigla'
            ])
                ->where('status', 'ATIVO')
                ->latest('data_inicio')
                ->first();

            if (!$ciclo) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhum ciclo ativo no momento'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ciclo->id,
                    'data_inicio' => $ciclo->data_inicio->format('Y-m-d'),
                    'data_fim' => $ciclo->data_fim->format('Y-m-d'),
                    'status' => $ciclo->status,
                    'estatisticas' => [
                        'total_veiculos' => $ciclo->total_veiculos,
                        'inspecionados' => $ciclo->inspecionados,
                        'aprovados' => $ciclo->aprovados,
                        'com_problema' => $ciclo->com_problema,
                        'pendentes' => $ciclo->total_veiculos - $ciclo->inspecionados,
                        'percentual_conclusao' => round($ciclo->getPercentualConclusao(), 2),
                        'percentual_aprovacao' => round($ciclo->getPercentualAprovacao(), 2),
                    ],
                    'esta_ativo' => $ciclo->estaAtivo(),
                    'expirou' => $ciclo->expirou()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar ciclo ativo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar ciclo ativo'
            ], 500);
        }
    }

    /**
     * Retorna veículos do ciclo ativo
     */
    public function veiculosCicloAtivo(Request $request)
    {
        try {
            $ciclo = RprCiclo::where('status', 'ATIVO')
                ->latest('data_inicio')
                ->first();

            if (!$ciclo) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Nenhum ciclo ativo encontrado',
                    'pagination' => [
                        'total' => 0,
                        'per_page' => 20,
                        'current_page' => 1,
                        'last_page' => 1,
                    ]
                ]);
            }

            $query = RprCicloVeiculo::with([
                'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'unidade.modulo:id,serial',
                'unidade.empresa:id,sigla',
                'usuarioInspecao:id,nome'
            ])->where('id_ciclo', $ciclo->id);

            if ($request->filled('status')) {
                $query->where('status_inspecao', $request->status);
            }

            if ($request->filled('numero_ordem')) {
                $query->whereHas('unidade', function ($q) use ($request) {
                    $q->where('numero_ordem', 'LIKE', '%' . $request->numero_ordem . '%');
                });
            }

            $perPage = $request->get('per_page', 20);
            $veiculos = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'ciclo' => [
                    'id' => $ciclo->id,
                    'data_inicio' => $ciclo->data_inicio->format('d/m/Y'),
                    'data_fim' => $ciclo->data_fim->format('d/m/Y'),
                ],
                'data' => $veiculos->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'unidade' => [
                            'id' => $item->unidade->id,
                            'numero_ordem' => $item->unidade->numero_ordem_formatado,
                            'placa' => $item->unidade->placa,
                            'serial' => $item->unidade->modulo?->serial,
                        ],
                        'status_inspecao' => $item->status_inspecao,
                        'data_inspecao' => $item->data_inspecao?->format('d/m/Y H:i'),
                        'usuario_inspecao' => $item->usuarioInspecao?->nome,
                        'tem_rpr' => (bool) $item->id_rpr,
                        'observacao' => $item->observacao,
                    ];
                }),
                'pagination' => [
                    'total' => $veiculos->total(),
                    'per_page' => $veiculos->perPage(),
                    'current_page' => $veiculos->currentPage(),
                    'last_page' => $veiculos->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar veículos do ciclo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar veículos do ciclo'
            ], 500);
        }
    }

    /**
     * Realiza inspeção de um veículo no ciclo
     */
    public function realizarInspecao(Request $request, $id_veiculo_ciclo)
    {
        $validator = Validator::make($request->all(), [
            'status_t1' => 'required|in:S,N',
            'status_t2' => 'required|in:S,N',
            'status_t3' => 'required|in:S,N',
            'status_t4' => 'required|in:S,N',
            'status_t5' => 'required|in:S,N',
            'status_t9' => 'required|in:S,N',
            'status_t10' => 'required|in:S,N',
            'cor_t1' => 'nullable|string|max:255',
            'cor_t2' => 'nullable|string|max:255',
            'cor_t3' => 'nullable|string|max:255',
            'cor_t4' => 'nullable|string|max:255',
            'cor_t5' => 'nullable|string|max:255',
            'cor_t9' => 'nullable|string|max:255',
            'cor_t10' => 'nullable|string|max:255',
            'observacao' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $veiculoCiclo = RprCicloVeiculo::with('ciclo')->find($id_veiculo_ciclo);

            if (!$veiculoCiclo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo do ciclo não encontrado'
                ], 404);
            }

            if ($veiculoCiclo->foiInspecionado()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este veículo já foi inspecionado neste ciclo'
                ], 409);
            }

            DB::beginTransaction();

            $data = $validator->validated();

            $temProblema = $data['status_t1'] === 'S' ||
                $data['status_t2'] === 'S' ||
                $data['status_t3'] === 'S' ||
                $data['status_t4'] === 'S' ||
                $data['status_t5'] === 'S' ||
                $data['status_t9'] === 'S' ||
                $data['status_t10'] === 'S';

            $rpr = Rpr::create([
                'id_unidade' => $veiculoCiclo->id_unidade,
                'id_user' => Auth::id(),
                'status_t1' => $data['status_t1'],
                'status_t2' => $data['status_t2'],
                'status_t3' => $data['status_t3'],
                'status_t4' => $data['status_t4'],
                'status_t5' => $data['status_t5'],
                'status_t6' => 'N',
                'status_t7' => $temProblema ? 'N' : 'S',
                'status_t8' => 'N',
                'status_t9' => $data['status_t9'],
                'status_t10' => $data['status_t10'],
                'status_t11' => 'N',
                'cor_t1' => $data['cor_t1'] ?? null,
                'cor_t2' => $data['cor_t2'] ?? null,
                'cor_t3' => $data['cor_t3'] ?? null,
                'cor_t4' => $data['cor_t4'] ?? null,
                'cor_t5' => $data['cor_t5'] ?? null,
                'cor_t7' => $temProblema ? null : 'Veículo aprovado no RPR',
                'cor_t9' => $data['cor_t9'] ?? null,
                'cor_t10' => $data['cor_t10'] ?? null,
                'observacao' => $data['observacao'] ?? null,
                'data_cadastro' => now()
            ]);

            $veiculoCiclo->update([
                'status_inspecao' => $temProblema ? 'COM_PROBLEMA' : 'OK',
                'id_rpr' => $rpr->id,
                'data_inspecao' => now(),
                'id_user_inspecao' => Auth::id(),
                'observacao' => $data['observacao'] ?? null
            ]);

            $ciclo = $veiculoCiclo->ciclo;
            $ciclo->increment('inspecionados');

            if ($temProblema) {
                $ciclo->increment('com_problema');
            } else {
                $ciclo->increment('aprovados');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $temProblema
                    ? 'Veículo inspecionado com problemas detectados'
                    : 'Veículo aprovado no RPR',
                'data' => [
                    'id_rpr' => $rpr->id,
                    'status' => $temProblema ? 'COM_PROBLEMA' : 'OK',
                    'aprovado' => !$temProblema
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao realizar inspeção', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar inspeção'
            ], 500);
        }
    }

    // ========================================
    // LISTAGEM E CONSULTAS
    // ========================================

    /**
     * Lista RPRs com filtros
     */
    public function listar(Request $request)
    {
        try {
            $mostrarTodos = filter_var(
                $request->get('mostrar_todos', false),
                FILTER_VALIDATE_BOOLEAN
            );

            $query = Rpr::with([
                'unidade:id,numero_ordem,placa,id_empresa',
                'unidade.empresa:id,sigla',
                'usuario:id,nome'
            ]);

            if ($request->filled('id_unidade')) {
                $query->where('id_unidade', $request->id_unidade);
            }

            $todosRprs = $query->orderBy('data_cadastro', 'desc')->get();

            $data = $todosRprs->map(function ($rpr) {
                $checklistAprovado = ChecklistVeicular::where('id_rpr', $rpr->id)
                    ->where('status_geral', 'APROVADO')
                    ->where('finalizado', true)
                    ->exists();

                $problemas = $rpr->getProblemasAtivosComDadosBanco();
                $estaOk = $rpr->estaOk();
                $temProblemas = !$estaOk && count($problemas) > 0;

                return [
                    'id' => $rpr->id,
                    'id_unidade' => $rpr->id_unidade,
                    'unidade' => [
                        'id' => $rpr->unidade->id,
                        'numero_ordem' => $rpr->unidade->numero_ordem_formatado,
                        'placa' => $rpr->unidade->placa,
                    ],
                    'problemas' => $problemas,
                    'esta_ok' => $estaOk,
                    'observacao' => $rpr->observacao,
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                    'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
                    'checklist_resolvido' => $checklistAprovado,
                    'tem_problemas' => $temProblemas,
                ];
            });

            if ($mostrarTodos) {
                $data = $data->filter(fn($rpr) => $rpr['checklist_resolvido'])->values();
            } else {
                $data = $data->filter(
                    fn($rpr) =>
                    $rpr['tem_problemas'] && !$rpr['checklist_resolvido']
                )->values();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $data->count(),
                    'per_page' => $request->get('per_page', 15),
                    'current_page' => 1,
                    'last_page' => 1,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar RPRs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar RPRs'
            ], 500);
        }
    }

    /**
     * Busca RPRs de uma unidade específica
     */
    public function buscarPorUnidade($id_unidade)
    {
        try {
            $unidade = Unidade::with(['empresa:id,sigla'])->find($id_unidade);

            if (!$unidade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade não encontrada'
                ], 404);
            }

            $rprs = Rpr::where('id_unidade', $id_unidade)
                ->with(['usuario:id,nome'])
                ->orderBy('data_cadastro', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $rprs->map(function ($rpr) {
                    return [
                        'id' => $rpr->id,
                        'id_unidade' => $rpr->id_unidade,
                        'problemas' => $rpr->getProblemasAtivosComDadosBanco(),
                        'esta_ok' => $rpr->estaOk(),
                        'observacao' => $rpr->observacao,
                        'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                        'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar RPRs por unidade', [
                'error' => $e->getMessage(),
                'id_unidade' => $id_unidade
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar RPRs'
            ], 500);
        }
    }

    /**
     * Histórico de ciclos
     */
    public function historicoCiclos(Request $request)
    {
        try {
            $query = RprCiclo::query();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $ciclos = $query->orderBy('data_inicio', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $ciclos->map(function ($ciclo) {
                    return [
                        'id' => $ciclo->id,
                        'periodo' => $ciclo->data_inicio->format('d/m/Y') .
                            ' a ' .
                            $ciclo->data_fim->format('d/m/Y'),
                        'status' => $ciclo->status,
                        'total_veiculos' => $ciclo->total_veiculos,
                        'inspecionados' => $ciclo->inspecionados,
                        'aprovados' => $ciclo->aprovados,
                        'com_problema' => $ciclo->com_problema,
                        'percentual_conclusao' => round($ciclo->getPercentualConclusao(), 2),
                    ];
                }),
                'pagination' => [
                    'total' => $ciclos->total(),
                    'current_page' => $ciclos->currentPage(),
                    'last_page' => $ciclos->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar histórico de ciclos', [
                'error' => $e->getMessage()
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Erro ao buscar histórico'
                ],
                500
            );
        }
    }
}
