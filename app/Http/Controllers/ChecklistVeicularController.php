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
    public function veiculosManutencao(Request $request)
    {
        $query = Rpr::with([
            'unidade:id_unidade,unidade_nome,placa,serial',
            'usuario:id_user,nome'
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
                    'problemas' => $this->getItensChecklist($rpr),
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

    private function getItensChecklist($rpr): array
    {
        $itens = [];

        if ($rpr->status_t1 === 'S') {
            $itens[] = ['tipo' => 1, 'descricao' => 'Velocidade irregular', 'correcao' => $rpr->cor_t1 ?? 'Calibragem', 'obrigatorio' => true];
        }

        if ($rpr->status_t2 === 'S') {
            $itens[] = ['tipo' => 2, 'descricao' => 'RPM irregular', 'correcao' => $rpr->cor_t2 ?? 'Calibragem', 'obrigatorio' => true];
        }

        if ($rpr->status_t3 === 'S') {
            $itens[] = ['tipo' => 3, 'descricao' => 'Sinal GPS/GPRS irregular', 'correcao' => $rpr->cor_t3 ?? 'Verificar antenas', 'obrigatorio' => true];
        }

        if ($rpr->status_t4 === 'S') {
            $itens[] = ['tipo' => 4, 'descricao' => 'ID Motorista irregular', 'correcao' => $rpr->cor_t4 ?? 'Verificar leitor', 'obrigatorio' => true];
        }

        if ($rpr->status_t5 === 'S') {
            $itens[] = ['tipo' => 5, 'descricao' => 'Revisão estrutura rastreador', 'correcao' => $rpr->cor_t5 ?? 'Revisar instalação', 'obrigatorio' => true];
        }

        if ($rpr->status_t9 === 'S') {
            $itens[] = ['tipo' => 9, 'descricao' => 'Sem rastreador', 'correcao' => $rpr->cor_t9 ?? 'Instalar rastreador', 'obrigatorio' => true];
        }

        if ($rpr->status_t10 === 'S') {
            $itens[] = ['tipo' => 10, 'descricao' => 'Agendado para correção', 'correcao' => $rpr->cor_t10 ?? 'Executar correção', 'obrigatorio' => true];
        }

        $itens[] = ['tipo' => 6, 'descricao' => 'Câmera', 'correcao' => 'Verificar captura de imagem', 'obrigatorio' => false];
        $itens[] = ['tipo' => 7, 'descricao' => 'Tomada USB', 'correcao' => 'Testar alimentação', 'obrigatorio' => false];
        $itens[] = ['tipo' => 8, 'descricao' => 'WiFi', 'correcao' => 'Verificar conectividade', 'obrigatorio' => false];

        return $itens;
    }

      public function statusVeiculo($id_unidade)
    {
        $unidade = Unidade::find($id_unidade);

        if (!$unidade) {
            return response()->json([
                'success' => false,
                'message' => 'Veículo não encontrado'
            ], 404);
        }

        // Buscar o RPR mais recente desta unidade
        $rpr = Rpr::where('id_unidade', $id_unidade)
            ->with(['usuario:id_user,nome'])
            ->latest('data_cadastro')
            ->first();

        // Buscar checklist mais recente (finalizado ou não)
        $checklistAtivo = null;
        if ($rpr) {
            $checklistAtivo = ChecklistVeicular::where('id_rpr', $rpr->id)
                ->latest('data_analise')
                ->first();
        }

        $response = [
            'unidade' => [
                'id' => $unidade->id_unidade,
                'nome' => $unidade->unidade_nome,
                'placa' => $unidade->placa,
                'serial' => $unidade->serial,
                'status' => $unidade->status,
            ],
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
            $response['checklist_ativo'] = [
                'id' => $checklistAtivo->id,
                'id_rpr' => $checklistAtivo->id_rpr,
                'status' => $checklistAtivo->status_geral,
                'data_analise' => $checklistAtivo->data_analise
                    ? $checklistAtivo->data_analise->format('Y-m-d H:i:s')
                    : null,
                'data_prevista' => $checklistAtivo->data_prevista_conclusao
                    ? $checklistAtivo->data_prevista_conclusao->format('Y-m-d')
                    : null,
                'finalizado' => (bool) $checklistAtivo->finalizado,
                'data_finalizacao' => $checklistAtivo->data_finalizacao
                    ? $checklistAtivo->data_finalizacao->format('Y-m-d H:i:s')
                    : null,
                'itens_com_problema' => $checklistAtivo->getItensComProblema(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }

    public function index(Request $request)
    {
        $query = ChecklistVeicular::with([
            'unidade:id_unidade,unidade_nome,placa',
            'usuarioAnalise'
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
                        'id' => $checklist->unidade->id_unidade,
                        'nome' => $checklist->unidade->unidade_nome,
                        'placa' => $checklist->unidade->placa,
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
            'unidade',
            'rpr',
            'usuarioAnalise',
            'usuarioFinalizacao'
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
            'id_unidade' => 'required|exists:unidades,id_unidade',
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
        $data['id_user_analise'] = Auth::user()->id_user;
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
            'id_user_finalizacao' => Auth::user()->id_user,
            'observacoes_gerais' => $request->observacoes_finalizacao ?? $checklist->observacoes_gerais,
        ]);

        if ($checklist->rpr) {
            if ($request->status_final === 'APROVADO') {
                // Se aprovado, zera todos os problemas
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
            } else {
                // Se reprovado, mantém os problemas mas marca como analisado
                $checklist->rpr->update([
                    'status_t8' => 'S', // Marca como "em análise" ou "reprovado"
                    'cor_t8' => 'Checklist reprovado',
                    'data_cadastro' => now(),
                ]);
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
