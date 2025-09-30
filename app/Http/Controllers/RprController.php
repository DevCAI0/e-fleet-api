<?php

namespace App\Http\Controllers;

use App\Models\Rpr;
use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
        $query = Rpr::with(['unidade:id_unidade,unidade_nome,placa', 'usuario:id_user,nome']);

        if ($request->filled('id_unidade')) {
            $query->where('id_unidade', $request->id_unidade);
        }

        $perPage = $request->get('per_page', 15);
        $rprs = $query->orderBy('data_cadastro', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rprs->map(function ($rpr) {
                return [
                    'id' => $rpr->id,
                    'id_unidade' => $rpr->id_unidade,
                    'unidade' => [
                        'id' => $rpr->unidade->id_unidade,
                        'nome' => $rpr->unidade->unidade_nome,
                        'placa' => $rpr->unidade->placa,
                    ],
                    'problemas' => $rpr->getProblemasAtivos(),
                    'esta_ok' => $rpr->estaOk(),
                    'observacao' => $rpr->observacao,
                    'data_cadastro' => $rpr->data_cadastro->format('Y-m-d H:i:s'),
                    'usuario' => $rpr->usuario ? $rpr->usuario->nome : null,
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
}
