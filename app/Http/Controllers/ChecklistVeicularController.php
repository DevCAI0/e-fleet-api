<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChecklistVeicular;
use App\Models\Rpr;
use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChecklistVeicularController extends Controller
{
    /**
     * Listar veículos em manutenção
     */
    public function veiculosManutencao(Request $request)
  {
    $query = Rpr::with([
        'unidade:id_unidade,unidade_nome,placa,serial',
        'usuario:id_user,nome'  // CORRIGIDO: id_user e nome
    ])
        ->whereHas('unidade', function($q) {
            $q->where('status', 'S');
        })
        ->where(function($q) {
            $q->where('status_t1', 'S')
              ->orWhere('status_t2', 'S')
              ->orWhere('status_t3', 'S')
              ->orWhere('status_t4', 'S')
              ->orWhere('status_t5', 'S')
              ->orWhere('status_t6', 'S')
              ->orWhere('status_t9', 'S')
              ->orWhere('status_t10', 'S');
        });

        // Filtros
        if ($request->filled('unidade_nome')) {
            $query->whereHas('unidade', function($q) use ($request) {
                $q->where('unidade_nome', 'LIKE', '%' . $request->unidade_nome . '%');
            });
        }

        if ($request->filled('sem_checklist')) {
            $query->whereDoesntHave('checklists', function($q) {
                $q->where('finalizado', false);
            });
        }

        $perPage = $request->get('per_page', 15);
        $rprs = $query->latest('data_cadastro')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rprs->map(function($rpr) {
                return [
                    'id' => $rpr->id,
                    'id_unidade' => $rpr->id_unidade,
                    'unidade' => [
                        'id' => $rpr->unidade->id_unidade,
                        'nome' => $rpr->unidade->unidade_nome,
                        'placa' => $rpr->unidade->placa,
                        'serial' => $rpr->unidade->serial,
                    ],
                    'problemas' => $rpr->getProblemasAtivos(),
                    'tem_checklist_ativo' => $rpr->checklistAtivo()->exists(),
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'total' => $rprs->total(),
                'per_page' => $rprs->perPage(),
                'current_page' => $rprs->currentPage(),
                'last_page' => $rprs->lastPage(),
            ]
        ]);
    }

    /**
     * Status de um veículo específico
     */
    public function statusVeiculo($id_unidade)
    {
        $unidade = Unidade::find($id_unidade);

        if (!$unidade) {
            return response()->json([
                'success' => false,
                'message' => 'Veículo não encontrado'
            ], 404);
        }

        $rpr = Rpr::where('id_unidade', $id_unidade)
            ->latest('data_cadastro')
            ->first();

        $checklistAtivo = ChecklistVeicular::where('id_unidade', $id_unidade)
            ->where('finalizado', false)
            ->latest('data_analise')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'unidade' => [
                    'id' => $unidade->id_unidade,
                    'nome' => $unidade->unidade_nome,
                    'placa' => $unidade->placa,
                    'serial' => $unidade->serial,
                    'status' => $unidade->status,
                ],
                'rpr' => $rpr ? [
                    'id' => $rpr->id,
                    'tem_problemas' => $rpr->temProblema(),
                    'esta_ok' => $rpr->estaOk(),
                    'problemas' => $rpr->getProblemasAtivos(),
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                ] : null,
                'checklist_ativo' => $checklistAtivo ? [
                    'id' => $checklistAtivo->id,
                    'status' => $checklistAtivo->status_geral,
                    'data_analise' => $checklistAtivo->data_analise->format('Y-m-d H:i:s'),
                    'data_prevista' => $checklistAtivo->data_prevista_conclusao?->format('Y-m-d'),
                    'percentual_ok' => round($checklistAtivo->getPercentualOk(), 2),
                    'itens_com_problema' => $checklistAtivo->getItensComProblema(),
                ] : null,
            ]
        ]);
    }

    /**
     * Listar checklists
     */
    public function index(Request $request)
    {
        $query = ChecklistVeicular::with(['unidade:id_unidade,unidade_nome,placa', 'usuarioAnalise:id,name']);

        // Filtros
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
                        'id' => $checklist->unidade->id_unidade,
                        'nome' => $checklist->unidade->unidade_nome,
                        'placa' => $checklist->unidade->placa,
                    ],
                    'status_geral' => $checklist->status_geral,
                    'finalizado' => $checklist->finalizado,
                    'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
                    'data_prevista_conclusao' => $checklist->data_prevista_conclusao?->format('Y-m-d'),
                    'percentual_ok' => round($checklist->getPercentualOk(), 2),
                    'usuario_analise' => $checklist->usuarioAnalise->name ?? null,
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

    /**
     * Obter checklist específico
     */
    public function show($id)
    {
        $checklist = ChecklistVeicular::with([
            'unidade',
            'rpr',
            'usuarioAnalise:id,name',
            'usuarioFinalizacao:id,name'
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
                    'id' => $checklist->unidade->id_unidade,
                    'nome' => $checklist->unidade->unidade_nome,
                    'placa' => $checklist->unidade->placa,
                    'serial' => $checklist->unidade->serial,
                ],
                'status_geral' => $checklist->status_geral,
                'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
                'data_prevista_conclusao' => $checklist->data_prevista_conclusao?->format('Y-m-d'),
                'finalizado' => $checklist->finalizado,
                'data_finalizacao' => $checklist->data_finalizacao?->format('Y-m-d H:i:s'),

                // Itens do checklist
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

                'usuario_analise' => $checklist->usuarioAnalise->name ?? null,
                'usuario_finalizacao' => $checklist->usuarioFinalizacao->name ?? null,
            ]
        ]);
    }

    /**
     * Criar novo checklist
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_rpr' => 'required|exists:rpr,id',
            'id_unidade' => 'required|exists:unidades,id_unidade',
            'status_geral' => 'required|in:PENDENTE,EM_ANALISE,AGUARDANDO_PECAS,EM_MANUTENCAO',

            'modulo_rastreador' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'modulo_rastreador_obs' => 'nullable|string|max:1000',
            'sirene' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sirene_obs' => 'nullable|string|max:1000',
            'leitor_ibutton' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'leitor_ibutton_obs' => 'nullable|string|max:1000',
            'camera' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'camera_obs' => 'nullable|string|max:1000',
            'tomada_usb' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'tomada_usb_obs' => 'nullable|string|max:1000',
            'wifi' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'wifi_obs' => 'nullable|string|max:1000',
            'sensor_velocidade' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sensor_velocidade_obs' => 'nullable|string|max:1000',
            'sensor_rpm' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sensor_rpm_obs' => 'nullable|string|max:1000',
            'antena_gps' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'antena_gps_obs' => 'nullable|string|max:1000',
            'antena_gprs' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'antena_gprs_obs' => 'nullable|string|max:1000',
            'instalacao_eletrica' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'instalacao_eletrica_obs' => 'nullable|string|max:1000',
            'fixacao_equipamento' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'fixacao_equipamento_obs' => 'nullable|string|max:1000',

            'observacoes_gerais' => 'nullable|string|max:2000',
            'data_prevista_conclusao' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar se já existe checklist pendente
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

    /**
     * Atualizar checklist
     */
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

            'modulo_rastreador' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'modulo_rastreador_obs' => 'nullable|string|max:1000',
            'sirene' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sirene_obs' => 'nullable|string|max:1000',
            'leitor_ibutton' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'leitor_ibutton_obs' => 'nullable|string|max:1000',
            'camera' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'camera_obs' => 'nullable|string|max:1000',
            'tomada_usb' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'tomada_usb_obs' => 'nullable|string|max:1000',
            'wifi' => 'nullable|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'wifi_obs' => 'nullable|string|max:1000',
            'sensor_velocidade' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sensor_velocidade_obs' => 'nullable|string|max:1000',
            'sensor_rpm' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'sensor_rpm_obs' => 'nullable|string|max:1000',
            'antena_gps' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'antena_gps_obs' => 'nullable|string|max:1000',
            'antena_gprs' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'antena_gprs_obs' => 'nullable|string|max:1000',
            'instalacao_eletrica' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'instalacao_eletrica_obs' => 'nullable|string|max:1000',
            'fixacao_equipamento' => 'nullable|in:OK,PROBLEMA,NAO_VERIFICADO',
            'fixacao_equipamento_obs' => 'nullable|string|max:1000',

            'observacoes_gerais' => 'nullable|string|max:2000',
            'data_prevista_conclusao' => 'nullable|date',
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

    /**
     * Atualizar item específico do checklist
     */
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

    /**
     * Finalizar checklist
     */
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

            // Se aprovado, atualizar RPR
            if ($request->status_final === 'APROVADO' && $checklist->rpr) {
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

    /**
     * Reabrir checklist finalizado (apenas admin)
     */
    public function reabrir($id)
    {
        $checklist = ChecklistVeicular::find($id);

        if (!$checklist) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não encontrado'
            ], 404);
        }

        if (!$checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist não está finalizado'
            ], 400);
        }

        $checklist->update([
            'finalizado' => false,
            'status_geral' => 'EM_ANALISE',
            'data_finalizacao' => null,
            'id_user_finalizacao' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checklist reaberto com sucesso',
            'data' => [
                'id' => $checklist->id,
                'status_geral' => $checklist->status_geral,
            ]
        ]);
    }

    /**
     * Dashboard com estatísticas
     */
    public function dashboard()
    {
        $dados = [
            'aguardando_analise' => Rpr::whereDoesntHave('checklists', function($q) {
                $q->where('finalizado', false);
            })
            ->where(function($q) {
                $q->where('status_t1', 'S')
                  ->orWhere('status_t2', 'S')
                  ->orWhere('status_t3', 'S')
                  ->orWhere('status_t4', 'S')
                  ->orWhere('status_t5', 'S')
                  ->orWhere('status_t6', 'S')
                  ->orWhere('status_t9', 'S')
                  ->orWhere('status_t10', 'S');
            })
            ->count(),

            'checklists_ativos' => ChecklistVeicular::where('finalizado', false)->count(),
            'checklists_finalizados_hoje' => ChecklistVeicular::where('finalizado', true)
                ->whereDate('data_finalizacao', today())
                ->count(),

            'por_status' => ChecklistVeicular::select('status_geral', DB::raw('count(*) as total'))
                ->where('finalizado', false)
                ->groupBy('status_geral')
                ->pluck('total', 'status_geral')
                ->toArray(),

            'atrasados' => ChecklistVeicular::where('finalizado', false)
                ->whereNotNull('data_prevista_conclusao')
                ->whereDate('data_prevista_conclusao', '<', today())
                ->count(),

            'tempo_medio_conclusao' => ChecklistVeicular::where('finalizado', true)
                ->whereNotNull('data_finalizacao')
                ->selectRaw('AVG(DATEDIFF(data_finalizacao, data_analise)) as media')
                ->value('media'),

            'aprovados_mes' => ChecklistVeicular::where('status_geral', 'APROVADO')
                ->whereMonth('data_finalizacao', now()->month)
                ->whereYear('data_finalizacao', now()->year)
                ->count(),

            'reprovados_mes' => ChecklistVeicular::where('status_geral', 'REPROVADO')
                ->whereMonth('data_finalizacao', now()->month)
                ->whereYear('data_finalizacao', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }

    /**
     * Problemas mais comuns
     */
    public function problemasComuns()
    {
        $campos = [
            'modulo_rastreador' => 'Módulo Rastreador',
            'sirene' => 'Sirene',
            'leitor_ibutton' => 'Leitor iButton',
            'camera' => 'Câmera',
            'tomada_usb' => 'Tomada USB',
            'wifi' => 'WiFi',
            'sensor_velocidade' => 'Sensor Velocidade',
            'sensor_rpm' => 'Sensor RPM',
            'antena_gps' => 'Antena GPS',
            'antena_gprs' => 'Antena GPRS',
            'instalacao_eletrica' => 'Instalação Elétrica',
            'fixacao_equipamento' => 'Fixação Equipamento'
        ];

        $problemas = [];

        foreach ($campos as $campo => $nome) {
            $total = ChecklistVeicular::where($campo, 'PROBLEMA')->count();
            if ($total > 0) {
                $problemas[] = [
                    'item' => $nome,
                    'campo' => $campo,
                    'total' => $total
                ];
            }
        }

        usort($problemas, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        return response()->json([
            'success' => true,
            'data' => $problemas
        ]);
    }

    /**
     * Tempo médio por status
     */
    public function tempoMedio()
    {
        $dados = ChecklistVeicular::where('finalizado', true)
            ->whereNotNull('data_finalizacao')
            ->select(
                'status_geral',
                DB::raw('AVG(DATEDIFF(data_finalizacao, data_analise)) as tempo_medio'),
                DB::raw('MIN(DATEDIFF(data_finalizacao, data_analise)) as tempo_minimo'),
                DB::raw('MAX(DATEDIFF(data_finalizacao, data_analise)) as tempo_maximo'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('status_geral')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }

    /**
     * Relatório por período
     */
    public function relatorioPorPeriodo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $dados = [
            'periodo' => [
                'inicio' => $request->data_inicio,
                'fim' => $request->data_fim,
            ],
            'total_checklists' => ChecklistVeicular::whereBetween('data_analise', [$request->data_inicio, $request->data_fim])
                ->count(),
            'finalizados' => ChecklistVeicular::whereBetween('data_analise', [$request->data_inicio, $request->data_fim])
                ->where('finalizado', true)
                ->count(),
            'pendentes' => ChecklistVeicular::whereBetween('data_analise', [$request->data_inicio, $request->data_fim])
                ->where('finalizado', false)
                ->count(),
            'por_status' => ChecklistVeicular::whereBetween('data_analise', [$request->data_inicio, $request->data_fim])
                ->select('status_geral', DB::raw('count(*) as total'))
                ->groupBy('status_geral')
                ->pluck('total', 'status_geral')
                ->toArray(),
            'problemas_mais_comuns' => $this->getProblemasPorPeriodo($request->data_inicio, $request->data_fim),
            'veiculos_atendidos' => ChecklistVeicular::whereBetween('data_analise', [$request->data_inicio, $request->data_fim])
                ->distinct('id_unidade')
                ->count('id_unidade'),
        ];

        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }

    /**
     * Histórico de um veículo
     */
    public function historicoVeiculo($id_unidade)
    {
        $unidade = Unidade::find($id_unidade);

        if (!$unidade) {
            return response()->json([
                'success' => false,
                'message' => 'Veículo não encontrado'
            ], 404);
        }

        $checklists = ChecklistVeicular::with(['usuarioAnalise:id,name', 'usuarioFinalizacao:id,name'])
            ->where('id_unidade', $id_unidade)
            ->orderBy('data_analise', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'unidade' => [
                    'id' => $unidade->id_unidade,
                    'nome' => $unidade->unidade_nome,
                    'placa' => $unidade->placa,
                ],
                'total_checklists' => $checklists->count(),
                'checklists' => $checklists->map(function($checklist) {
                    return [
                        'id' => $checklist->id,
                        'status_geral' => $checklist->status_geral,
                        'finalizado' => $checklist->finalizado,
                        'data_analise' => $checklist->data_analise->format('Y-m-d H:i:s'),
                        'data_finalizacao' => $checklist->data_finalizacao?->format('Y-m-d H:i:s'),
                        'percentual_ok' => round($checklist->getPercentualOk(), 2),
                        'usuario_analise' => $checklist->usuarioAnalise->name ?? null,
                        'usuario_finalizacao' => $checklist->usuarioFinalizacao->name ?? null,
                    ];
                })
            ]
        ]);
    }

    /**
     * Opções de correção
     */
    public function opcoesCorrecao()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'cor_t1' => [
                    'descricao' => 'Velocidade irregular',
                    'opcoes' => ['Calibragem', 'Módulo', 'Instalação', 'Sensor']
                ],
                'cor_t2' => [
                    'descricao' => 'RPM irregular',
                    'opcoes' => ['Calibragem', 'Módulo', 'Instalação', 'Alternador']
                ],
                'cor_t3' => [
                    'descricao' => 'Sinal GPS/GPRS irregular',
                    'opcoes' => ['Módulo', 'Instalação', 'Satélite', 'Operadora']
                ],
                'cor_t4' => [
                    'descricao' => 'ID Motorista irregular',
                    'opcoes' => ['Módulo', 'Instalação', 'Leitor', 'Ibutton']
                ],
                'cor_t5' => [
                    'descricao' => 'Revisão estrutura rastreador',
                    'opcoes' => ['Instalação']
                ],
                'cor_t6' => [
                    'descricao' => 'Manutenção veículo +5 dias',
                    'opcoes' => ['Oficina']
                ],
                'cor_t7' => [
                    'descricao' => 'Veículo OK',
                    'opcoes' => ['Veículo OK (100%)', 'Calibragem', 'Módulo', 'Instalação', 'Sensor', 'Alternador', 'Leitor', 'Ibutton', 'Satélite', 'Operadora', 'Oficina']
                ],
                'cor_t9' => [
                    'descricao' => 'Sem rastreador',
                    'opcoes' => ['Instalação']
                ],
                'cor_t10' => [
                    'descricao' => 'Agendado para correção',
                    'opcoes' => ['Calibragem', 'Módulo', 'Instalação', 'Sensor', 'Alternador', 'Leitor', 'Ibutton', 'Satélite', 'Operadora', 'Oficina']
                ]
            ]
        ]);
    }

    /**
     * Tipos de status disponíveis
     */
    public function tiposStatus()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status_checklist' => [
                    'PENDENTE' => 'Pendente de análise',
                    'EM_ANALISE' => 'Em análise',
                    'AGUARDANDO_PECAS' => 'Aguardando peças',
                    'EM_MANUTENCAO' => 'Em manutenção',
                    'APROVADO' => 'Aprovado',
                    'REPROVADO' => 'Reprovado'
                ],
                'status_item' => [
                    'OK' => 'Funcionando corretamente',
                    'PROBLEMA' => 'Com problema',
                    'NAO_INSTALADO' => 'Não instalado',
                    'NAO_VERIFICADO' => 'Não verificado'
                ]
            ]
        ]);
    }

    /**
     * Helper: Obter problemas por período
     */
    private function getProblemasPorPeriodo($dataInicio, $dataFim)
    {
        $campos = [
            'modulo_rastreador' => 'Módulo Rastreador',
            'sirene' => 'Sirene',
            'leitor_ibutton' => 'Leitor iButton',
            'camera' => 'Câmera',
            'tomada_usb' => 'Tomada USB',
            'wifi' => 'WiFi',
            'sensor_velocidade' => 'Sensor Velocidade',
            'sensor_rpm' => 'Sensor RPM',
            'antena_gps' => 'Antena GPS',
            'antena_gprs' => 'Antena GPRS',
            'instalacao_eletrica' => 'Instalação Elétrica',
            'fixacao_equipamento' => 'Fixação Equipamento'
        ];

        $problemas = [];

        foreach ($campos as $campo => $nome) {
            $total = ChecklistVeicular::whereBetween('data_analise', [$dataInicio, $dataFim])
                ->where($campo, 'PROBLEMA')
                ->count();

            if ($total > 0) {
                $problemas[] = [
                    'item' => $nome,
                    'total' => $total
                ];
            }
        }

        usort($problemas, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        return array_slice($problemas, 0, 5);
    }
}
