<?php

namespace App\Http\Controllers;

use App\Models\Unidade;
use App\Models\ComandoPendente;
use App\Models\ComandoHistorico;
use App\Models\ComandoResposta;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComandoController extends Controller
{
    /**
     * Listar unidades disponíveis para comandos
     */
    public function unidades(): JsonResponse
    {
        try {
            $unidades = Unidade::ativas()
                ->comModuloSerial()
                ->select('id_unidade', 'unidade_nome', 'placa', 'modulo', 'serial')
                ->orderBy('unidade_nome')
                ->get()
                ->map(function ($unidade) {
                    return [
                        'id' => $unidade->id_unidade,
                        'nome' => $unidade->unidade_nome,
                        'placa' => $unidade->placa,
                        'modulo' => $unidade->modulo,
                        'serial' => $unidade->serial,
                        'key' => $unidade->modulo . '|' . $unidade->serial,
                        'display' => $unidade->unidade_nome . ' - ' . ($unidade->placa ?? 'S/Placa') . ' (Módulo: ' . $unidade->modulo . ')'
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $unidades
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar unidades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar comando de hodômetro
     */
    public function hodometro(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'unidade_key' => 'required|string',
                'valor' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extrair módulo e serial
            $unidadeKey = explode('|', $request->unidade_key);
            if (count($unidadeKey) !== 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Formato de unidade inválido'
                ], 400);
            }

            [$modulo, $serial] = $unidadeKey;
            $valor = $request->valor;

            // Validar se módulo é suportado
            if (!in_array($modulo, ['ST215', 'ST300'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Módulo não suportado. Use ST215 ou ST300'
                ], 400);
            }

            // Gerar comando
            $comandoString = "SA200CMD;{$serial};02;SetOdometer={$valor}";

            $comando = ComandoPendente::create([
                'ID_Disp' => $serial,
                'comando_nome' => 'Hodômetro',
                'comando_string' => $comandoString,
                'usuario' => Auth::id(),
                'data_solicitacao' => now(),
                'observacao' => "Valor: {$valor}"
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comando de hodômetro criado com sucesso',
                'data' => [
                    'nsu_comando' => $comando->nsu_comando,
                    'modulo' => $modulo,
                    'serial' => $serial,
                    'valor' => $valor,
                    'comando_string' => $comandoString
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao criar comando: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar comando de reboot
     */
    public function reboot(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'unidade_key' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extrair módulo e serial
            $unidadeKey = explode('|', $request->unidade_key);
            if (count($unidadeKey) !== 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Formato de unidade inválido'
                ], 400);
            }

            [$modulo, $serial] = $unidadeKey;

            // Gerar comando
            $comandoString = "SA200CMD;{$serial};02;Reboot";

            $comando = ComandoPendente::create([
                'ID_Disp' => $serial,
                'comando_nome' => 'Reboot',
                'comando_string' => $comandoString,
                'usuario' => Auth::id(),
                'data_solicitacao' => now(),
                'observacao' => 'Reinicialização do módulo'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comando de reboot criado com sucesso',
                'data' => [
                    'nsu_comando' => $comando->nsu_comando,
                    'modulo' => $modulo,
                    'serial' => $serial,
                    'comando_string' => $comandoString
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao criar comando: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configurar velocidade máxima
     */
    public function velocidadeMaxima(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'unidade_key' => 'required|string',
                'velocidade' => 'required|numeric|min:1|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extrair módulo e serial
            $unidadeKey = explode('|', $request->unidade_key);
            if (count($unidadeKey) !== 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Formato de unidade inválido'
                ], 400);
            }

            [$modulo, $serial] = $unidadeKey;
            $velocidade = $request->velocidade;

            // Gerar comando
            $comandoString = "SA200SVC;{$serial};02;1;{$velocidade};0;0;0;0;1;1;1;0;0;0;0";

            $comando = ComandoPendente::create([
                'ID_Disp' => $serial,
                'comando_nome' => 'Velocidade',
                'comando_string' => $comandoString,
                'usuario' => Auth::id(),
                'data_solicitacao' => now(),
                'observacao' => "Velocidade: {$velocidade} km/h"
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comando de velocidade máxima criado com sucesso',
                'data' => [
                    'nsu_comando' => $comando->nsu_comando,
                    'modulo' => $modulo,
                    'serial' => $serial,
                    'velocidade' => $velocidade,
                    'comando_string' => $comandoString
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao criar comando: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configurar rede
     */
    public function configurarRede(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'unidade_key' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extrair módulo e serial
            $unidadeKey = explode('|', $request->unidade_key);
            if (count($unidadeKey) !== 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Formato de unidade inválido'
                ], 400);
            }

            [$modulo, $serial] = $unidadeKey;

            // Gerar comando de configuração de rede
            $comandoString = "SA200NTW;{$serial};02;0;smart.m2m.vivo.com.br;vivo;vivo;177.87.8.43;7210;200.254.242.19;7210;0;8486";

            $comando = ComandoPendente::create([
                'ID_Disp' => $serial,
                'comando_nome' => 'Rede',
                'comando_string' => $comandoString,
                'usuario' => Auth::id(),
                'data_solicitacao' => now(),
                'observacao' => 'Configuração de rede'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comando de configuração de rede criado com sucesso',
                'data' => [
                    'nsu_comando' => $comando->nsu_comando,
                    'modulo' => $modulo,
                    'serial' => $serial,
                    'comando_string' => $comandoString
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao criar comando: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar comandos pendentes
     */
    public function comandosPendentes(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $serial = $request->get('serial');

            $query = ComandoPendente::with(['user:id_user,nome'])
                ->pendentes()
                ->orderBy('data_solicitacao', 'desc');

            if ($serial) {
                $query->where('ID_Disp', $serial);
            }

            $comandos = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $comandos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar comandos pendentes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar comandos enviados
     */
    public function comandosEnviados(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $serial = $request->get('serial');

            $query = ComandoPendente::with(['user:id_user,nome'])
                ->enviados()
                ->orderBy('data_envio', 'desc');

            if ($serial) {
                $query->where('ID_Disp', $serial);
            }

            $comandos = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $comandos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar comandos enviados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar histórico de comandos
     */
    public function historico(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $serial = $request->get('serial');
            $dataInicio = $request->get('data_inicio');
            $dataFim = $request->get('data_fim');

            $query = ComandoHistorico::with(['user:id_user,nome'])
                ->orderBy('data_solicitacao', 'desc');

            if ($serial) {
                $query->where('ID_Disp', $serial);
            }

            if ($dataInicio) {
                $query->where('data_solicitacao', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->where('data_solicitacao', '<=', $dataFim . ' 23:59:59');
            }

            $comandos = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $comandos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar logs de resposta
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $serial = $request->get('serial');
            $dataInicio = $request->get('data_inicio');
            $dataFim = $request->get('data_fim');

            $query = ComandoResposta::orderBy('data_recebimento', 'desc');

            if ($serial) {
                $query->where('id_unidade', $serial);
            }

            if ($dataInicio) {
                $query->where('data_recebimento', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->where('data_recebimento', '<=', $dataFim . ' 23:59:59');
            }

            $logs = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar logs: ' . $e->getMessage()
            ], 500);
        }
    }

}
