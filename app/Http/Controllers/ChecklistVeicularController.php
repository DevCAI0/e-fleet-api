<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChecklistVeicular;
use App\Models\Rpr;
use App\Models\Unidade;
use App\Models\OrdemServicoVeiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChecklistVeicularController extends Controller
{
    public function veiculosManutencao(Request $request)
    {
        $resolvidos = $request->boolean('resolvidos', false);

        if ($resolvidos) {
            $query = OrdemServicoVeiculo::with([
                'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'unidade.modulo:id,serial,modelo',
                'unidade.empresa:id,sigla',
                'rpr',
                'ordemServico:id,numero_os,status,prioridade'
            ])
            ->whereHas('ordemServico', function($q) {
                $q->where('status', 'CONCLUIDA');
            })
            ->where('status_veiculo', 'CONCLUIDO');
        } else {
            $query = OrdemServicoVeiculo::with([
                'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'unidade.modulo:id,serial,modelo',
                'unidade.empresa:id,sigla',
                'rpr',
                'ordemServico:id,numero_os,status,prioridade'
            ])
            ->whereHas('ordemServico', function($q) {
                $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']);
            })
            ->whereIn('status_veiculo', ['PENDENTE', 'EM_MANUTENCAO']);
        }

        if ($request->filled('numero_ordem')) {
            $query->whereHas('unidade', function($q) use ($request) {
                $q->where('numero_ordem', 'LIKE', '%' . $request->numero_ordem . '%');
            });
        }

        $perPage = $request->get('per_page', 100);
        $osVeiculos = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $osVeiculos->map(function($osVeiculo) {
                $problemas = [];
                if ($osVeiculo->rpr) {
                    $problemas = $osVeiculo->rpr->getProblemasAtivos();
                }

                $temChecklist = false;
                if ($osVeiculo->rpr) {
                    $temChecklist = ChecklistVeicular::where('id_rpr', $osVeiculo->rpr->id)
                        ->where('finalizado', false)
                        ->exists();
                }

                return [
                    'id' => $osVeiculo->id,
                    'id_unidade' => $osVeiculo->id_unidade,
                    'id_rpr' => $osVeiculo->id_rpr,
                    'unidade' => [
                        'id' => $osVeiculo->unidade->id,
                        'numero_ordem' => $osVeiculo->unidade->numero_ordem_formatado,
                        'placa' => $osVeiculo->unidade->placa,
                        'serial' => $osVeiculo->unidade->modulo?->serial,
                        'modelo' => $osVeiculo->unidade->modulo?->modelo,
                    ],
                    'problemas' => $problemas,
                    'tem_checklist_ativo' => $temChecklist,
                    'ordem_servico' => [
                        'numero_os' => $osVeiculo->ordemServico->numero_os,
                        'status' => $osVeiculo->ordemServico->status,
                        'prioridade' => $osVeiculo->ordemServico->prioridade,
                    ],
                    'status_veiculo' => $osVeiculo->status_veiculo,
                    'data_cadastro' => $osVeiculo->rpr?->data_cadastro?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'total' => $osVeiculos->total(),
                'per_page' => $osVeiculos->perPage(),
                'current_page' => $osVeiculos->currentPage(),
                'last_page' => $osVeiculos->lastPage(),
            ]
        ]);
    }

    public function statusVeiculo($id_unidade)
    {
        try {
            $unidade = Unidade::with([
                'modulo:id,serial,modelo',
                'empresa:id,sigla'
            ])->findOrFail($id_unidade);

            $rpr = Rpr::where('id_unidade', $id_unidade)
                ->where(function($q) {
                    $q->where('status_t1', 'S')
                      ->orWhere('status_t2', 'S')
                      ->orWhere('status_t3', 'S')
                      ->orWhere('status_t4', 'S')
                      ->orWhere('status_t5', 'S')
                      ->orWhere('status_t9', 'S')
                      ->orWhere('status_t10', 'S');
                })
                ->with(['usuario:id,nome'])
                ->latest('data_cadastro')
                ->first();

            $checklistAtivo = null;
            if ($rpr) {
                $checklistAtivo = ChecklistVeicular::where('id_rpr', $rpr->id)
                    ->latest('data_analise')
                    ->first();
            }

            $osVeiculo = OrdemServicoVeiculo::with(['ordemServico' => function($q) {
                $q->select('id', 'numero_os', 'status', 'prioridade', 'data_abertura', 'data_prevista_conclusao');
            }])
            ->where('id_unidade', $id_unidade)
            ->whereHas('ordemServico', function($q) {
                $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']);
            })
            ->latest('id')
            ->first();

            $response = [
                'success' => true,
                'unidade' => [
                    'id' => $unidade->id,
                    'numero_ordem' => $unidade->numero_ordem_formatado,
                    'placa' => $unidade->placa,
                    'serial' => $unidade->modulo?->serial,
                    'modelo' => $unidade->modulo?->modelo,
                    'status' => $unidade->status,
                ],
                'rpr' => null,
                'checklist_ativo' => null,
                'ordem_servico' => null,
                'tem_checklist_ativo' => false,
            ];

            if ($rpr) {
                $response['rpr'] = [
                    'id' => $rpr->id,
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                    'problemas' => $rpr->getProblemasAtivos(),
                    'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
                ];
            }

            if ($checklistAtivo) {
                $itensResolvidos = 0;
                $totalItens = 0;

                $campos = [
                    'modulo_rastreador', 'sirene', 'leitor_ibutton', 'camera',
                    'tomada_usb', 'wifi', 'sensor_velocidade', 'sensor_rpm',
                    'antena_gps', 'antena_gprs', 'instalacao_eletrica', 'fixacao_equipamento'
                ];

                $itensComProblema = [];

                foreach ($campos as $campo) {
                    if ($checklistAtivo->$campo && $checklistAtivo->$campo !== 'NAO_VERIFICADO') {
                        $totalItens++;

                        if (in_array($checklistAtivo->$campo, ['OK', 'PROBLEMA', 'NAO_INSTALADO'])) {
                            $itensResolvidos++;

                            if ($checklistAtivo->$campo === 'PROBLEMA') {
                                $itensComProblema[] = [
                                    'item' => ucwords(str_replace('_', ' ', $campo)),
                                    'status' => $checklistAtivo->$campo,
                                    'observacao' => $checklistAtivo->{$campo . '_obs'},
                                ];
                            }
                        }
                    }
                }

                $progresso = $totalItens > 0 ? ($itensResolvidos / $totalItens) * 100 : 0;

                $response['checklist_ativo'] = [
                    'id' => $checklistAtivo->id,
                    'id_rpr' => $checklistAtivo->id_rpr,
                    'status' => $checklistAtivo->status_geral,
                    'data_analise' => $checklistAtivo->data_analise ? $checklistAtivo->data_analise->format('Y-m-d H:i:s') : null,
                    'data_prevista' => $checklistAtivo->data_prevista_conclusao ? $checklistAtivo->data_prevista_conclusao->format('Y-m-d H:i:s') : null,
                    'finalizado' => (bool) $checklistAtivo->finalizado,
                    'data_finalizacao' => $checklistAtivo->data_finalizacao ? $checklistAtivo->data_finalizacao->format('Y-m-d H:i:s') : null,
                    'itens_com_problema' => $itensComProblema,
                    'total_itens' => $totalItens,
                    'itens_resolvidos' => $itensResolvidos,
                    'progresso' => round($progresso, 2),
                ];

                $response['tem_checklist_ativo'] = true;
            }

            if ($osVeiculo) {
                $response['ordem_servico'] = [
                    'id' => $osVeiculo->ordemServico->id,
                    'numero_os' => $osVeiculo->ordemServico->numero_os,
                    'status' => $osVeiculo->ordemServico->status,
                    'prioridade' => $osVeiculo->ordemServico->prioridade,
                    'data_abertura' => $osVeiculo->ordemServico->data_abertura->format('Y-m-d H:i:s'),
                    'data_prevista' => $osVeiculo->ordemServico->data_prevista_conclusao?->format('Y-m-d'),
                    'status_veiculo' => $osVeiculo->status_veiculo,
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Erro ao obter status do veículo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = ChecklistVeicular::with([
            'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
            'unidade.modulo:id,serial',
            'unidade.empresa:id,sigla',
            'usuarioAnalise:id,nome'
        ]);

        if ($request->filled('status')) {
            $query->where('status_geral', $request->status);
        }

        if ($request->filled('finalizado')) {
            $query->where('finalizado', $request->boolean('finalizado'));
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data_analise', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_analise', '<=', $request->data_fim);
        }

        if ($request->filled('id_unidade')) {
            $query->where('id_unidade', $request->id_unidade);
        }

        $perPage = $request->get('per_page', 15);
        $checklists = $query->latest('data_analise')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $checklists->map(function($checklist) {
                return [
                    'id' => $checklist->id,
                    'unidade' => [
                        'id' => $checklist->unidade->id,
                        'numero_ordem' => $checklist->unidade->numero_ordem_formatado,
                        'placa' => $checklist->unidade->placa,
                        'serial' => $checklist->unidade->modulo?->serial,
                    ],
                    'status_geral' => $checklist->status_geral,
                    'finalizado' => $checklist->finalizado,
                    'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
                    'data_prevista_conclusao' => $checklist->data_prevista_conclusao?->format('Y-m-d'),
                    'percentual_ok' => round($checklist->getPercentualOk(), 2),
                    'usuario_analise' => $checklist->usuarioAnalise?->nome ?? null,
                ];
            }),
            'pagination' => [
                'total' => $checklists->total(),
                'per_page' => $checklists->perPage(),
                'current_page' => $checklists->currentPage(),
                'last_page' => $checklists->lastPage(),
            ]
        ]);
    }

    public function show($id)
    {
        $checklist = ChecklistVeicular::with([
            'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
            'unidade.modulo:id,serial',
            'unidade.empresa:id,sigla',
            'rpr',
            'usuarioAnalise:id,nome',
            'usuarioFinalizacao:id,nome'
        ])->find($id);

        if (!$checklist) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $checklist->id,
                'id_unidade' => $checklist->id_unidade,
                'id_rpr' => $checklist->id_rpr,
                'unidade' => [
                    'id' => $checklist->unidade->id,
                    'numero_ordem' => $checklist->unidade->numero_ordem_formatado,
                    'placa' => $checklist->unidade->placa,
                    'serial' => $checklist->unidade->modulo?->serial,
                ],
                'status_geral' => $checklist->status_geral,
                'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
                'data_prevista_conclusao' => $checklist->data_prevista_conclusao?->format('Y-m-d'),
                'finalizado' => $checklist->finalizado,
                'data_finalizacao' => $checklist->data_finalizacao?->format('Y-m-d H:i:s'),
                'itens' => [
                    'modulo_rastreador' => [
                        'status' => $checklist->modulo_rastreador,
                        'observacao' => $checklist->modulo_rastreador_obs,
                    ],
                    'sirene' => [
                        'status' => $checklist->sirene,
                        'observacao' => $checklist->sirene_obs,
                    ],
                    'leitor_ibutton' => [
                        'status' => $checklist->leitor_ibutton,
                        'observacao' => $checklist->leitor_ibutton_obs,
                    ],
                    'camera' => [
                        'status' => $checklist->camera,
                        'observacao' => $checklist->camera_obs,
                    ],
                    'tomada_usb' => [
                        'status' => $checklist->tomada_usb,
                        'observacao' => $checklist->tomada_usb_obs,
                    ],
                    'wifi' => [
                        'status' => $checklist->wifi,
                        'observacao' => $checklist->wifi_obs,
                    ],
                    'sensor_velocidade' => [
                        'status' => $checklist->sensor_velocidade,
                        'observacao' => $checklist->sensor_velocidade_obs,
                    ],
                   'sensor_rpm' => [
                        'status' => $checklist->sensor_rpm,
                        'observacao' => $checklist->sensor_rpm_obs,
                    ],
                    'antena_gps' => [
                        'status' => $checklist->antena_gps,
                        'observacao' => $checklist->antena_gps_obs,
                    ],
                    'antena_gprs' => [
                        'status' => $checklist->antena_gprs,
                        'observacao' => $checklist->antena_gprs_obs,
                    ],
                    'instalacao_eletrica' => [
                        'status' => $checklist->instalacao_eletrica,
                        'observacao' => $checklist->instalacao_eletrica_obs,
                    ],
                    'fixacao_equipamento' => [
                        'status' => $checklist->fixacao_equipamento,
                        'observacao' => $checklist->fixacao_equipamento_obs,
                    ],
                ],
                'observacoes_gerais' => $checklist->observacoes_gerais,
                'percentual_ok' => round($checklist->getPercentualOk(), 2),
                'itens_com_problema' => $checklist->getItensComProblema(),
                'usuario_analise' => $checklist->usuarioAnalise?->nome ?? null,
                'usuario_finalizacao' => $checklist->usuarioFinalizacao?->nome ?? null,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_rpr' => 'required|exists:rpr,id',
            'id_unidade' => 'required|exists:unidades,id',
            'status_geral' => 'required|in:PENDENTE,EM_ANALISE,AGUARDANDO_PECAS,EM_MANUTENCAO',
            'observacoes_gerais' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $checklistExistente = ChecklistVeicular::where('id_rpr', $request->id_rpr)
            ->where('finalizado', false)
            ->first();

        if ($checklistExistente) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um checklist pendente para este veículo',
                'checklist_id' => $checklistExistente->id
            ], 409);
        }

        $data = $validator->validated();
        $data['id_user_analise'] = Auth::id();
        $data['data_analise'] = now();

        $checklist = ChecklistVeicular::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Checklist criado com sucesso',
            'data' => [
                'id' => $checklist->id,
                'id_unidade' => $checklist->id_unidade,
                'status_geral' => $checklist->status_geral,
                'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $checklist = ChecklistVeicular::find($id);

        if (!$checklist) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não encontrado'
            ], 404);
        }

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já finalizado não pode ser editado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_geral' => 'sometimes|in:PENDENTE,EM_ANALISE,AGUARDANDO_PECAS,EM_MANUTENCAO,APROVADO',
            'observacoes_gerais' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $checklist->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Checklist atualizado com sucesso',
            'data' => [
                'id' => $checklist->id,
                'status_geral' => $checklist->status_geral,
                'percentual_ok' => round($checklist->getPercentualOk(), 2),
            ]
        ]);
    }

    public function updateItem(Request $request, $id)
    {
        $checklist = ChecklistVeicular::find($id);

        if (!$checklist) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não encontrado'
            ], 404);
        }

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já finalizado não pode ser editado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'item' => 'required|in:modulo_rastreador,sirene,leitor_ibutton,camera,tomada_usb,wifi,sensor_velocidade,sensor_rpm,antena_gps,antena_gprs,instalacao_eletrica,fixacao_equipamento',
            'status' => 'required|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'observacao' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $item = $request->item;
        $checklist->$item = $request->status;
        $checklist->{$item . '_obs'} = $request->observacao;
        $checklist->save();

        return response()->json([
            'success' => true,
            'message' => 'Item atualizado com sucesso',
            'data' => [
                'item' => $item,
                'status' => $checklist->$item,
                'observacao' => $checklist->{$item . '_obs'},
                'percentual_ok' => round($checklist->getPercentualOk(), 2),
            ]
        ]);
    }

    public function finalizar(Request $request, $id)
    {
        $checklist = ChecklistVeicular::find($id);

        if (!$checklist) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não encontrado'
            ], 404);
        }

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já foi finalizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_final' => 'required|in:APROVADO,REPROVADO',
            'observacoes_finalizacao' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($checklist, $request) {
            $checklist->update([
                'status_geral' => $request->status_final,
                'finalizado' => true,
                'data_finalizacao' => now(),
                'id_user_finalizacao' => Auth::id(),
                'observacoes_gerais' => $request->observacoes_finalizacao ?? $checklist->observacoes_gerais,
            ]);

            if ($checklist->rpr) {
                if ($request->status_final === 'APROVADO') {
                    $checklist->rpr->update([
                        'status_t1' => 'N',
                        'status_t2' => 'N',
                        'status_t3' => 'N',
                        'status_t4' => 'N',
                        'status_t5' => 'N',
                        'status_t6' => 'N',
                        'status_t7' => 'S',
                        'status_t8' => 'N',
                        'status_t9' => 'N',
                        'status_t10' => 'N',
                        'cor_t7' => 'Veículo OK (100%)',
                        'data_cadastro' => now(),
                    ]);

                    $osVeiculo = OrdemServicoVeiculo::where('id_rpr', $checklist->id_rpr)
                        ->whereHas('ordemServico', function($q) {
                            $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']);
                        })
                        ->first();

                    if ($osVeiculo) {
                        $osVeiculo->update([
                            'status_veiculo' => 'CONCLUIDO',
                            'data_conclusao_manutencao' => now(),
                            'servicos_realizados' => 'Checklist aprovado - Veículo OK',
                        ]);

                        $os = $osVeiculo->ordemServico;
                        $totalVeiculos = $os->veiculos->count();
                        $veiculosConcluidos = $os->veiculos->where('status_veiculo', 'CONCLUIDO')->count();

                        if ($totalVeiculos === $veiculosConcluidos) {
                            $os->update([
                                'status' => 'CONCLUIDA',
                                'data_conclusao' => now(),
                                'id_user_conclusao' => Auth::id(),
                            ]);
                        }
                    }
                } else {
                    $checklist->rpr->update([
                        'status_t8' => 'S',
                        'cor_t8' => 'Checklist reprovado',
                        'data_cadastro' => now(),
                    ]);

                    $osVeiculo = OrdemServicoVeiculo::where('id_rpr', $checklist->id_rpr)
                        ->whereHas('ordemServico', function($q) {
                            $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']);
                        })
                        ->first();

                    if ($osVeiculo) {
                        $osVeiculo->update([
                            'status_veiculo' => 'PROBLEMA_PERSISTENTE',
                            'observacoes_tecnico' => 'Checklist reprovado',
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Checklist finalizado com sucesso',
            'data' => [
                'id' => $checklist->id,
                'status_final' => $checklist->status_geral,
                'data_finalizacao' => $checklist->data_finalizacao->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}
