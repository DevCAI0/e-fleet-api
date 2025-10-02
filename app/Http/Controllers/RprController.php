<?php

namespace App\Http\Controllers;

use App\Models\Rpr;
use App\Models\Unidade;
use App\Models\ChecklistVeicular;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RprController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_unidade' => 'required|exists:unidades,id_unidade',
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
    }

    public function buscarPorUnidade($id_unidade)
    {
        $unidade = Unidade::find($id_unidade);

        if (!$unidade) {
            return response()->json([
                'success' => false,
                'message' => 'Unidade não encontrada'
            ], 404);
        }

        $rprs = Rpr::where('id_unidade', $id_unidade)
            ->with(['usuario:id_user,nome'])
            ->orderBy('data_cadastro', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rprs->map(function ($rpr) {
                return [
                    'id' => $rpr->id,
                    'id_unidade' => $rpr->id_unidade,
                    'problemas' => $rpr->getProblemasAtivos(),
                    'esta_ok' => $rpr->estaOk(),
                    'observacao' => $rpr->observacao,
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                    'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
                ];
            })
        ]);
    }

    public function listar(Request $request)
    {
        // Converter para boolean corretamente
        $mostrarTodos = filter_var($request->get('mostrar_todos', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $request->get('per_page', 15);

        Log::info('===== INÍCIO LISTAGEM RPR =====');
        Log::info('Parâmetro mostrar_todos:', ['valor' => $mostrarTodos, 'tipo' => gettype($mostrarTodos)]);

        // Buscar todos os RPRs com suas relações
        $query = Rpr::with(['unidade:id_unidade,unidade_nome,placa', 'usuario:id_user,nome']);

        if ($request->filled('id_unidade')) {
            $query->where('id_unidade', $request->id_unidade);
        }

        // Buscar todos sem paginação primeiro
        $todosRprs = $query->orderBy('data_cadastro', 'desc')->get();

        Log::info('Total RPRs encontrados no banco:', ['count' => $todosRprs->count()]);

        // Processar e filtrar
        $data = $todosRprs->map(function ($rpr) {
            // Verificar se existe checklist aprovado para este RPR
            $checklistAprovado = ChecklistVeicular::where('id_rpr', $rpr->id)
                ->where('status_geral', 'APROVADO')
                ->where('finalizado', true)
                ->exists();

            $problemas = $rpr->getProblemasAtivos();
            $estaOk = $rpr->estaOk();
            $temProblemas = !$estaOk && count($problemas) > 0;

            Log::info('RPR processado:', [
                'id' => $rpr->id,
                'id_unidade' => $rpr->id_unidade,
                'status_t7' => $rpr->status_t7,
                'esta_ok' => $estaOk,
                'qtd_problemas' => count($problemas),
                'tem_problemas' => $temProblemas,
                'checklist_resolvido' => $checklistAprovado,
                'problemas' => $problemas
            ]);

            return [
                'id' => $rpr->id,
                'id_unidade' => $rpr->id_unidade,
                'unidade' => [
                    'id' => $rpr->unidade->id_unidade,
                    'nome' => $rpr->unidade->unidade_nome,
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

        Log::info('Antes do filtro:', ['total_processados' => $data->count()]);

        // Filtrar baseado no parâmetro
        if ($mostrarTodos) {
            // Quando mostrar_todos = true, mostrar APENAS resolvidos
            $data = $data->filter(function ($rpr) {
                $resultado = $rpr['checklist_resolvido'];
                Log::info('Filtro RESOLVIDOS:', ['id' => $rpr['id'], 'passou' => $resultado]);
                return $resultado;
            })->values();
        } else {
            // Quando mostrar_todos = false, mostrar APENAS pendentes (não resolvidos)
            $data = $data->filter(function ($rpr) {
                $resultado = $rpr['tem_problemas'] && !$rpr['checklist_resolvido'];
                Log::info('Filtro PENDENTES:', [
                    'id' => $rpr['id'],
                    'tem_problemas' => $rpr['tem_problemas'],
                    'checklist_resolvido' => $rpr['checklist_resolvido'],
                    'passou' => $resultado
                ]);
                return $resultado;
            })->values();
        }

        Log::info('Depois do filtro:', ['total_filtrados' => $data->count()]);
        Log::info('===== FIM LISTAGEM RPR =====');

        $total = $data->count();

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => 1,
                'last_page' => 1,
            ]
        ]);
    }
}
