<?php

namespace App\Http\Controllers;

use App\Models\ChecklistVeicular;
use App\Models\Rpr;
use App\Models\Unidade;
use App\Models\OrdemServicoVeiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->whereHas('ordemServico', fn($q) => $q->where('status', 'CONCLUIDA'))
            ->where('status_veiculo', 'CONCLUIDO');
        } else {
            $query = OrdemServicoVeiculo::with([
                'unidade:id,numero_ordem,placa,id_modulo,id_empresa',
                'unidade.modulo:id,serial,modelo',
                'unidade.empresa:id,sigla',
                'rpr',
                'ordemServico:id,numero_os,status,prioridade'
            ])
            ->whereHas('ordemServico', fn($q) => $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']))
            ->whereIn('status_veiculo', ['PENDENTE', 'EM_MANUTENCAO']);
        }

        if ($request->filled('numero_ordem')) {
            $query->whereHas('unidade', fn($q) => $q->where('numero_ordem', 'LIKE', '%' . $request->numero_ordem . '%'));
        }

        $perPage = $request->get('per_page', 100);
        $osVeiculos = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $osVeiculos->map(function($osVeiculo) {
                $problemas = [];
                if ($osVeiculo->rpr) {
                    $problemas = $osVeiculo->rpr->getProblemasAtivosComDadosBanco();
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
                        'key' => $osVeiculo->unidade->id,
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
                ->where('finalizado', false)
                ->with('tecnicoResponsavel:id,nome')
                ->latest('data_analise')
                ->first();
        }

        $osVeiculo = OrdemServicoVeiculo::with(['ordemServico'])
            ->where('id_unidade', $id_unidade)
            ->whereHas('ordemServico', fn($q) => $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']))
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
                'key' => $unidade->id,
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
                'problemas' => $rpr->getProblemasAtivosComDadosBanco(),
                'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
            ];
        }

        if ($checklistAtivo) {
            $campos = [
                'modulo_rastreador',
                'sirene',
                'leitor_ibutton',
                'camera',
                'tomada_usb',
                'wifi'
            ];

            $totalItens = 0;
            $itensResolvidos = 0;

            foreach ($campos as $campo) {
                if ($checklistAtivo->$campo && $checklistAtivo->$campo !== 'NAO_VERIFICADO') {
                    $totalItens++;
                    if (in_array($checklistAtivo->$campo, ['OK', 'PROBLEMA', 'NAO_INSTALADO'])) {
                        $itensResolvidos++;
                    }
                }
            }

            $response['checklist_ativo'] = [
                'id' => $checklistAtivo->id,
                'id_rpr' => $checklistAtivo->id_rpr,
                'status' => $checklistAtivo->status_geral,
                'data_analise' => $checklistAtivo->data_analise->format('Y-m-d H:i:s'),
                'data_prevista' => $checklistAtivo->data_prevista_conclusao?->format('Y-m-d'),
                'finalizado' => $checklistAtivo->finalizado,
                'itens_com_problema' => $checklistAtivo->getItensComProblema(),
                'total_itens' => $totalItens,
                'itens_resolvidos' => $itensResolvidos,
                'progresso' => round($checklistAtivo->getPercentualConclusao(), 2),
                'tecnico_responsavel' => $checklistAtivo->tecnicoResponsavel?->nome,
                'pertence_ao_usuario' => $checklistAtivo->pertenceAoTecnico(Auth::id()),
            ];

            $response['tem_checklist_ativo'] = true;
        }

        if ($osVeiculo) {
            $response['ordem_servico'] = [
                'id' => $osVeiculo->ordemServico->id,
                'numero_os' => $osVeiculo->ordemServico->numero_os,
                'status' => $osVeiculo->ordemServico->status,
                'prioridade' => $osVeiculo->ordemServico->prioridade,
                'status_veiculo' => $osVeiculo->status_veiculo,
            ];
        }

        return response()->json($response);
    }

    public function show($id)
    {
        $checklist = ChecklistVeicular::with([
            'unidade:id,numero_ordem,placa,id_modulo',
            'unidade.modulo:id,serial',
            'rpr',
            'usuarioAnalise:id,nome',
            'tecnicoResponsavel:id,nome',
            'usuarioFinalizacao:id,nome'
        ])->findOrFail($id);

        if (!$checklist->pertenceAoTecnico(Auth::id()) && !$checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Este checklist pertence a outro técnico'
            ], 403);
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
                'finalizado' => $checklist->finalizado,
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
                ],
                'fotos' => $checklist->fotos ?? [],
                'observacoes_gerais' => $checklist->observacoes_gerais,
                'progresso' => round($checklist->getPercentualConclusao(), 2),
                'itens_com_problema' => $checklist->getItensComProblema(),
                'tecnico_responsavel' => $checklist->tecnicoResponsavel?->nome,
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
            if (!$checklistExistente->pertenceAoTecnico(Auth::id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Já existe um checklist ativo para este veículo com outro técnico'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Já existe um checklist ativo para este veículo',
                'checklist_id' => $checklistExistente->id
            ], 409);
        }

        $data = $validator->validated();
        $data['id_user_analise'] = Auth::id();
        $data['id_tecnico_responsavel'] = Auth::id();
        $data['data_analise'] = now();

        $checklist = ChecklistVeicular::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Checklist criado com sucesso',
            'data' => [
                'id' => $checklist->id,
                'id_unidade' => $checklist->id_unidade,
                'status_geral' => $checklist->status_geral,
            ]
        ], 201);
    }

    public function atualizarItem(Request $request, $id)
    {
        $checklist = ChecklistVeicular::findOrFail($id);

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já finalizado'
            ], 403);
        }

        if (!$checklist->pertenceAoTecnico(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Este checklist pertence a outro técnico'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'item' => 'required|in:modulo_rastreador,sirene,leitor_ibutton,camera,tomada_usb,wifi',
            'status' => 'required|in:OK,PROBLEMA,NAO_INSTALADO,NAO_VERIFICADO',
            'observacao' => 'nullable|string|max:2000',
            'fotos' => 'nullable|array',
            'fotos.*' => 'string',
            'itens_listagem' => 'nullable|array',
            'itens_listagem.*' => 'string',
            'itens_com_defeito' => 'nullable|integer|min:0',
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

        $observacaoCompleta = $request->observacao ?: '';

        if ($request->has('itens_listagem') && count($request->itens_listagem) > 0) {
            $observacaoCompleta .= "\n\nItens instalados:\n";
            foreach ($request->itens_listagem as $itemLista) {
                $observacaoCompleta .= "- {$itemLista}\n";
            }
        }

        if ($request->has('itens_com_defeito') && $request->itens_com_defeito > 0) {
            $observacaoCompleta .= "\nItens com defeito: {$request->itens_com_defeito}";
        }

        $checklist->{$item . '_obs'} = trim($observacaoCompleta);

        if ($request->has('fotos')) {
            $fotosExistentes = $checklist->fotos ?? [];
            $checklist->fotos = array_merge($fotosExistentes, $request->fotos);
        }

        $checklist->save();

        return response()->json([
            'success' => true,
            'message' => 'Item atualizado',
            'data' => [
                'item' => $item,
                'status' => $checklist->$item,
                'progresso' => round($checklist->getPercentualConclusao(), 2),
            ]
        ]);
    }

    public function uploadFoto(Request $request, $id)
    {
        $checklist = ChecklistVeicular::findOrFail($id);

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já finalizado'
            ], 403);
        }

        if (!$checklist->pertenceAoTecnico(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Este checklist pertence a outro técnico'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $path = $request->file('foto')->store('checklists/' . $id, 'public');

        $fotosExistentes = $checklist->fotos ?? [];
        $fotosExistentes[] = $path;
        $checklist->fotos = $fotosExistentes;
        $checklist->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto enviada',
            'data' => [
                'path' => $path,
                'url' => Storage::url($path)
            ]
        ]);
    }

    public function finalizar(Request $request, $id)
    {
        $checklist = ChecklistVeicular::findOrFail($id);

        if ($checklist->finalizado) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist já finalizado'
            ], 403);
        }

        if (!$checklist->pertenceAoTecnico(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Este checklist pertence a outro técnico'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_final' => 'required|in:APROVADO,REPROVADO',
            'observacoes_finalizacao' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
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
                        ->whereHas('ordemServico', fn($q) => $q->whereIn('status', ['ABERTA', 'EM_ANDAMENTO']))
                        ->first();

                    if ($osVeiculo) {
                        $osVeiculo->update([
                            'status_veiculo' => 'CONCLUIDO',
                            'data_conclusao_manutencao' => now(),
                            'servicos_realizados' => 'Checklist aprovado',
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
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Checklist finalizado',
            'data' => [
                'id' => $checklist->id,
                'status_final' => $checklist->status_geral,
            ]
        ]);
    }
}
