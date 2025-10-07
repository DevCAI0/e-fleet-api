<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoVeiculo;
use App\Models\Rpr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrdemServicoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = OrdemServico::with([
                'veiculos.unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'veiculos.unidade.modulo:id,serial',
                'veiculos.unidade.empresa:id,sigla',
                'veiculos.rpr',
                'tecnicoResponsavel:id,nome'
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('prioridade')) {
                $query->where('prioridade', $request->prioridade);
            }

            $perPage = $request->get('per_page', 100);
            $ordens = $query->orderBy('data_abertura', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $ordens->map(function($os) {
                    $veiculosInfo = $os->veiculos->map(function($v) {
                        $problemas = [];
                        if ($v->rpr) {
                            $problemas = $v->rpr->getProblemasAtivos();
                        }

                        return [
                            'id' => $v->id,
                            'id_unidade' => $v->id_unidade,
                            'numero_ordem' => $v->unidade->numero_ordem_formatado ?? 'S/N',
                            'placa' => $v->unidade->placa ?? 'S/Placa',
                            'total_problemas' => count($problemas),
                            'status' => $v->status_veiculo,
                        ];
                    });

                    $totalProblemas = $veiculosInfo->sum('total_problemas');

                    return [
                        'id' => $os->id,
                        'numero_os' => $os->numero_os,
                        'status' => $os->status,
                        'prioridade' => $os->prioridade,
                        'total_veiculos' => $os->veiculos->count(),
                        'veiculos_concluidos' => $os->veiculos->where('status_veiculo', 'CONCLUIDO')->count(),
                        'percentual_conclusao' => round($os->getPercentualConclusao(), 2),
                        'total_problemas' => $totalProblemas,
                        'veiculos' => $veiculosInfo,
                        'tecnico' => $os->tecnicoResponsavel?->nome,
                        'data_abertura' => $os->data_abertura->format('Y-m-d H:i:s'),
                        'data_prevista' => $os->data_prevista_conclusao?->format('Y-m-d'),
                    ];
                }),
                'pagination' => [
                    'total' => $ordens->total(),
                    'per_page' => $ordens->perPage(),
                    'current_page' => $ordens->currentPage(),
                    'last_page' => $ordens->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar ordens de serviço', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'veiculos' => 'required|array|min:1',
            'veiculos.*' => 'required|integer|exists:unidades,id',
            'prioridade' => 'required|in:BAIXA,MEDIA,ALTA,URGENTE',
            'descricao' => 'nullable|string',
            'data_prevista_conclusao' => 'nullable|date_format:Y-m-d',
            'id_tecnico_responsavel' => 'nullable|exists:usuarios,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $numeroOS = OrdemServico::gerarNumeroOS();

            $os = OrdemServico::create([
                'numero_os' => $numeroOS,
                'status' => 'ABERTA',
                'prioridade' => $request->prioridade,
                'descricao' => $request->descricao,
                'data_prevista_conclusao' => $request->data_prevista_conclusao,
                'id_tecnico_responsavel' => $request->id_tecnico_responsavel,
                'id_user_abertura' => Auth::id(),
            ]);

            $veiculos = array_map('intval', $request->veiculos);

            foreach ($veiculos as $idUnidade) {
                $rpr = Rpr::where('id_unidade', $idUnidade)
                    ->where(function($q) {
                        $q->where('status_t1', 'S')
                          ->orWhere('status_t2', 'S')
                          ->orWhere('status_t3', 'S')
                          ->orWhere('status_t4', 'S')
                          ->orWhere('status_t5', 'S')
                          ->orWhere('status_t9', 'S')
                          ->orWhere('status_t10', 'S');
                    })
                    ->latest('data_cadastro')
                    ->first();

                $problemas = $rpr ? $rpr->getProblemasAtivos() : [];

                OrdemServicoVeiculo::create([
                    'id_ordem_servico' => $os->id,
                    'id_unidade' => $idUnidade,
                    'id_rpr' => $rpr?->id,
                    'problemas_identificados' => json_encode($problemas),
                    'status_veiculo' => 'PENDENTE'
                ]);
            }

            $totalVeiculos = count($veiculos);
            $os->registrarHistorico(
                Auth::id(),
                'OS Criada',
                "OS {$numeroOS} criada com {$totalVeiculos} veículo(s)"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ordem de Serviço criada com sucesso',
                'data' => [
                    'id' => $os->id,
                    'numero_os' => $os->numero_os
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar OS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao criar ordem de serviço'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $os = OrdemServico::with([
                'veiculos.unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'veiculos.unidade.modulo:id,serial',
                'veiculos.unidade.empresa:id,sigla',
                'veiculos.rpr',
                'tecnicoResponsavel:id,nome',
                'usuarioAbertura:id,nome',
                'historico.usuario:id,nome'
            ])->find($id);

            if (!$os) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ordem de serviço não encontrada'
                ], 404);
            }

            $veiculosDetalhes = $os->veiculos->map(function($v) {
                $problemas = [];
                $idRpr = null;
                $temChecklist = false;

                if ($v->rpr) {
                    $problemas = $v->rpr->getProblemasAtivos();
                    $idRpr = $v->rpr->id;

                    $temChecklist = \App\Models\ChecklistVeicular::where('id_rpr', $idRpr)
                        ->where('finalizado', false)
                        ->exists();
                }

                return [
                    'id' => $v->id,
                    'id_unidade' => $v->id_unidade,
                    'id_rpr' => $idRpr,
                    'tem_checklist' => $temChecklist,
                    'unidade' => [
                        'id' => $v->unidade->id,
                        'numero_ordem' => $v->unidade->numero_ordem_formatado,
                        'placa' => $v->unidade->placa ?? '',
                        'serial' => $v->unidade->modulo?->serial,
                    ],
                    'status' => $v->status_veiculo,
                    'problemas' => $problemas,
                    'total_problemas' => count($problemas),
                    'servicos_realizados' => $v->servicos_realizados,
                    'observacoes_tecnico' => $v->observacoes_tecnico,
                    'data_inicio' => $v->data_inicio_manutencao ? $v->data_inicio_manutencao->format('Y-m-d H:i:s') : null,
                    'data_conclusao' => $v->data_conclusao_manutencao ? $v->data_conclusao_manutencao->format('Y-m-d H:i:s') : null,
                ];
            });

            $historicoArray = [];
            if ($os->historico) {
                $historicoArray = $os->historico->map(function($h) {
                    return [
                        'usuario' => $h->usuario?->nome ?? 'Sistema',
                        'acao' => $h->acao,
                        'detalhes' => $h->detalhes,
                        'data' => $h->created_at ? $h->created_at->format('Y-m-d H:i:s') : null,
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $os->id,
                    'numero_os' => $os->numero_os,
                    'status' => $os->status,
                    'prioridade' => $os->prioridade,
                    'descricao' => $os->descricao,
                    'observacoes' => $os->observacoes,
                    'data_abertura' => $os->data_abertura->format('Y-m-d H:i:s'),
                    'data_prevista' => $os->data_prevista_conclusao ? $os->data_prevista_conclusao->format('Y-m-d H:i:s') : null,
                    'data_conclusao' => $os->data_conclusao ? $os->data_conclusao->format('Y-m-d H:i:s') : null,
                    'tecnico' => $os->tecnicoResponsavel?->nome,
                    'usuario_abertura' => $os->usuarioAbertura?->nome,
                    'percentual_conclusao' => round($os->getPercentualConclusao(), 2),
                    'veiculos' => $veiculosDetalhes->toArray(),
                    'total_veiculos' => $veiculosDetalhes->count(),
                    'veiculos_concluidos' => $veiculosDetalhes->where('status', 'CONCLUIDO')->count(),
                    'historico' => $historicoArray,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar OS', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function atualizarStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:ABERTA,EM_ANDAMENTO,CONCLUIDA,CANCELADA'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $os = OrdemServico::find($id);

            if (!$os) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ordem de serviço não encontrada'
                ], 404);
            }

            $statusAnterior = $os->status;

            $os->update(['status' => $request->status]);

            if ($request->status === 'CONCLUIDA') {
                $os->update([
                    'data_conclusao' => now(),
                    'id_user_conclusao' => Auth::id()
                ]);
            }

            $os->registrarHistorico(
                Auth::id(),
                'Status Alterado',
                "De {$statusAnterior} para {$request->status}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar status da OS', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function veiculosComProblemas(Request $request)
    {
        try {
            $rprs = Rpr::with([
                'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'unidade.modulo:id,serial',
                'unidade.empresa:id,sigla'
            ])
                ->where(function ($q) {
                    $q->where('status_t1', 'S')
                        ->orWhere('status_t2', 'S')
                        ->orWhere('status_t3', 'S')
                        ->orWhere('status_t4', 'S')
                        ->orWhere('status_t5', 'S')
                        ->orWhere('status_t9', 'S')
                        ->orWhere('status_t10', 'S');
                })
                ->whereDoesntHave('veiculosOS', function ($q) {
                    $q->whereHas('ordemServico', function ($q2) {
                        $q2->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']);
                    });
                })
                ->latest('data_cadastro')
                ->get()
                ->groupBy('id_unidade')
                ->map(fn($group) => $group->first());

            return response()->json([
                'success' => true,
                'data' => $rprs->map(function ($rpr) {
                    return [
                        'id' => $rpr->id,
                        'id_unidade' => $rpr->id_unidade,
                        'id_rpr' => $rpr->id,
                        'unidade' => [
                            'id' => $rpr->unidade->id,
                            'numero_ordem' => $rpr->unidade->numero_ordem_formatado,
                            'placa' => $rpr->unidade->placa,
                            'serial' => $rpr->unidade->modulo?->serial
                        ],
                        'problemas' => $rpr->getProblemasAtivos(),
                        'total_problemas' => count($rpr->getProblemasAtivos()),
                        'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s')
                    ];
                })->values()
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar veículos com problemas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function adicionarVeiculo(Request $request, $id)
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
            $os = OrdemServico::find($id);

            if (!$os) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ordem de serviço não encontrada'
                ], 404);
            }

            if ($os->status === 'CONCLUIDA' || $os->status === 'CANCELADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível adicionar veículos a uma OS concluída ou cancelada'
                ], 400);
            }

            $existe = OrdemServicoVeiculo::where('id_ordem_servico', $id)
                ->where('id_unidade', $request->id_unidade)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este veículo já está nesta OS'
                ], 409);
            }

            DB::beginTransaction();

            $rpr = Rpr::where('id_unidade', $request->id_unidade)
                ->where(function ($q) {
                    $q->where('status_t1', 'S')
                        ->orWhere('status_t2', 'S')
                        ->orWhere('status_t3', 'S')
                        ->orWhere('status_t4', 'S')
                        ->orWhere('status_t5', 'S')
                        ->orWhere('status_t9', 'S')
                        ->orWhere('status_t10', 'S');
                })
                ->latest('data_cadastro')
                ->first();

            $problemas = $rpr ? $rpr->getProblemasAtivos() : [];

           OrdemServicoVeiculo::create([
                'id_ordem_servico' => $id,
                'id_unidade' => $request->id_unidade,
                'id_rpr' => $rpr?->id,
                'problemas_identificados' => json_encode($problemas),
                'status_veiculo' => 'PENDENTE'
            ]);

            $os->registrarHistorico(Auth::id(), 'Veículo Adicionado', "Veículo ID {$request->id_unidade} adicionado à OS");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Veículo adicionado com sucesso'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao adicionar veículo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function removerVeiculo($id, $id_veiculo)
    {
        try {
            $os = OrdemServico::find($id);

            if (!$os) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ordem de serviço não encontrada'
                ], 404);
            }

            if ($os->status === 'CONCLUIDA' || $os->status === 'CANCELADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível remover veículos de uma OS concluída ou cancelada'
                ], 400);
            }

            $osVeiculo = OrdemServicoVeiculo::where('id_ordem_servico', $id)
                ->where('id', $id_veiculo)
                ->first();

            if (!$osVeiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado nesta OS'
                ], 404);
            }

            if ($osVeiculo->status_veiculo !== 'PENDENTE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível remover um veículo que já está em manutenção ou concluído'
                ], 400);
            }

            $osVeiculo->delete();

            $os->registrarHistorico(Auth::id(), 'Veículo Removido', "Veículo ID {$osVeiculo->id_unidade} removido da OS");

            return response()->json([
                'success' => true,
                'message' => 'Veículo removido com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao remover veículo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }

    public function atualizarStatusVeiculo(Request $request, $id, $id_veiculo)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:PENDENTE,EM_MANUTENCAO,CONCLUIDO,PROBLEMA_PERSISTENTE',
            'servicos_realizados' => 'nullable|string',
            'observacoes_tecnico' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $osVeiculo = OrdemServicoVeiculo::where('id_ordem_servico', $id)
                ->where('id', $id_veiculo)
                ->first();

            if (!$osVeiculo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veículo não encontrado nesta OS'
                ], 404);
            }

            $statusAnterior = $osVeiculo->status_veiculo;

            $dados = ['status_veiculo' => $request->status];

            if ($request->status === 'EM_MANUTENCAO' && !$osVeiculo->data_inicio_manutencao) {
                $dados['data_inicio_manutencao'] = now();
            }

            if ($request->status === 'CONCLUIDO') {
                $dados['data_conclusao_manutencao'] = now();
            }

            if ($request->filled('servicos_realizados')) {
                $dados['servicos_realizados'] = $request->servicos_realizados;
            }

            if ($request->filled('observacoes_tecnico')) {
                $dados['observacoes_tecnico'] = $request->observacoes_tecnico;
            }

            $osVeiculo->update($dados);

            $os = $osVeiculo->ordemServico;
            $os->registrarHistorico(
                Auth::id(),
                'Status Veículo Alterado',
                "Veículo ID {$osVeiculo->id_unidade}: {$statusAnterior} -> {$request->status}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Status do veículo atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar status do veículo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar a solicitação'
            ], 500);
        }
    }
}
