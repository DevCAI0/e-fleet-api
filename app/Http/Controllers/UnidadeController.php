<?php

namespace App\Http\Controllers;

use App\Models\Unidade;
use App\Models\Rpr;
use App\Models\ChecklistVeicular;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UnidadeController extends Controller
{
    public function veiculosComRpr(Request $request): JsonResponse
    {
        try {
            $rprsAtivos = Rpr::with(['unidade' => function($query) {
                $query->whereNotNull('lat')
                      ->whereNotNull('lon')
                      ->where('lat', '!=', 0)
                      ->where('lon', '!=', 0);
            }])
            ->whereHas('unidade', function($query) {
                $query->whereNotNull('lat')
                      ->whereNotNull('lon')
                      ->where('lat', '!=', 0)
                      ->where('lon', '!=', 0);
            })
            ->select('id', 'id_unidade', 'status_t1', 'status_t2', 'status_t3', 'status_t4',
                    'status_t5', 'status_t6', 'status_t7', 'status_t8', 'status_t9',
                    'status_t10', 'status_t11', 'data_cadastro')
            ->orderBy('data_cadastro', 'desc')
            ->get()
            ->groupBy('id_unidade')
            ->map(function($rprs) {
                return $rprs->first();
            });

            // Buscar checklists aprovados
            $checklistsAprovados = ChecklistVeicular::where('finalizado', true)
                ->where('status_geral', 'APROVADO')
                ->pluck('id_rpr')
                ->toArray();

            $unidadesMapeadas = $rprsAtivos->map(function ($rpr) use ($checklistsAprovados) {
                $unidade = $rpr->unidade;

                if (!$unidade) {
                    return null;
                }

                // Se este RPR já tem checklist aprovado, não mostrar
                if (in_array($rpr->id, $checklistsAprovados)) {
                    return null;
                }

                $isOnline = $this->isOnline($unidade);
                $statusMovimento = $this->determinarStatusMovimento($unidade);

                $temProblema = $rpr->status_t1 === 'S' ||
                               $rpr->status_t2 === 'S' ||
                               $rpr->status_t3 === 'S' ||
                               $rpr->status_t4 === 'S' ||
                               $rpr->status_t5 === 'S' ||
                               $rpr->status_t6 === 'S' ||
                               $rpr->status_t9 === 'S' ||
                               $rpr->status_t10 === 'S';

                $estaOk = $rpr->status_t7 === 'S';

                return [
                    'id' => $unidade->id_unidade,
                    'id_rpr' => $rpr->id,
                    'numero' => $this->getNumeroUnidade($unidade),
                    'nome' => $unidade->unidade_nome ?? 'Sem nome',
                    'placa' => $unidade->placa ?? 'Sem placa',
                    'posicao' => [
                        'latitude' => (float) $unidade->lat,
                        'longitude' => (float) $unidade->lon,
                    ],
                    'endereco' => $this->getEnderecoResumido($unidade->endereco_completo),
                    'velocidade' => (int) ($unidade->velocidade ?? 0),
                    'ignicao' => (bool) ($unidade->ignicao ?? false),
                    'voltagem' => (float) ($unidade->voltagem ?? 0),
                    'quilometragem' => (int) ($unidade->quilometragem ?? 0),
                    'data_evento' => $unidade->data_evento ?
                        \Carbon\Carbon::parse($unidade->data_evento)->toIso8601String() : null,
                    'data_server' => $unidade->data_server ?
                        \Carbon\Carbon::parse($unidade->data_server)->toIso8601String() : null,
                    'status_movimento' => $statusMovimento,
                    'cor_marker' => $this->determinarCorMarker($unidade, $isOnline, $temProblema, $estaOk),
                    'icone_marker' => $temProblema ? 'build' : 'directions-bus',
                    'online' => $isOnline,
                    'tem_rpr_pendente' => true,
                    'tem_problema' => $temProblema,
                    'rpr_ok' => $estaOk,
                ];
            })->filter()->values();

            return response()->json([
                'status' => 'success',
                'data' => $unidadesMapeadas,
                'total' => $unidadesMapeadas->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Erro em veiculosComRpr: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar veículos com RPR: ' . $e->getMessage()
            ], 500);
        }
    }

    public function mapa(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(1000, max(10, (int) $request->get('per_page', 100)));

            $query = Unidade::query()
                ->whereNotNull('lat')
                ->whereNotNull('lon')
                ->where('lat', '!=', 0)
                ->where('lon', '!=', 0);

            $total = $query->count();

            $unidades = $query
                ->select([
                    'id_unidade',
                    'unidade_nome',
                    'n_ordem',
                    'placa',
                    'lat',
                    'lon',
                    'velocidade',
                    'ignicao',
                    'data_evento',
                    'endereco_completo',
                    'quilometragem',
                    'voltagem',
                ])
                ->orderByRaw('COALESCE(data_evento, "1970-01-01") DESC')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $idsUnidades = $unidades->pluck('id_unidade')->toArray();

            $rprsAtivos = Rpr::whereIn('id_unidade', $idsUnidades)
                ->select('id', 'id_unidade', 'status_t1', 'status_t2', 'status_t3', 'status_t4',
                        'status_t5', 'status_t6', 'status_t7', 'status_t8', 'status_t9',
                        'status_t10', 'status_t11', 'data_cadastro')
                ->orderBy('data_cadastro', 'desc')
                ->get()
                ->groupBy('id_unidade')
                ->map(function($rprs) {
                    return $rprs->first();
                });

            // Buscar checklists aprovados
            $checklistsAprovados = ChecklistVeicular::where('finalizado', true)
                ->where('status_geral', 'APROVADO')
                ->pluck('id_rpr')
                ->toArray();

            $unidadesMapeadas = $unidades->map(function ($unidade) use ($rprsAtivos, $checklistsAprovados) {
                $isOnline = $this->isOnline($unidade);
                $statusMovimento = $this->determinarStatusMovimento($unidade);
                $rprAtivo = $rprsAtivos->get($unidade->id_unidade);

                $temRprPendente = false;
                $temProblema = false;
                $estaOk = false;

                if ($rprAtivo) {
                    // Se este RPR já tem checklist aprovado, não considerar como pendente
                    if (in_array($rprAtivo->id, $checklistsAprovados)) {
                        $rprAtivo = null;
                    } else {
                        $temRprPendente = true;
                        $temProblema = $rprAtivo->status_t1 === 'S' ||
                                       $rprAtivo->status_t2 === 'S' ||
                                       $rprAtivo->status_t3 === 'S' ||
                                       $rprAtivo->status_t4 === 'S' ||
                                       $rprAtivo->status_t5 === 'S' ||
                                       $rprAtivo->status_t6 === 'S' ||
                                       $rprAtivo->status_t9 === 'S' ||
                                       $rprAtivo->status_t10 === 'S';

                        $estaOk = $rprAtivo->status_t7 === 'S';
                    }
                }

                return [
                    'id' => $unidade->id_unidade,
                    'numero' => $this->getNumeroUnidade($unidade),
                    'nome' => $unidade->unidade_nome ?? 'Sem nome',
                    'placa' => $unidade->placa ?? 'Sem placa',
                    'posicao' => [
                        'latitude' => (float) $unidade->lat,
                        'longitude' => (float) $unidade->lon,
                    ],
                    'endereco' => $this->getEnderecoResumido($unidade->endereco_completo),
                    'velocidade' => (int) ($unidade->velocidade ?? 0),
                    'ignicao' => (bool) ($unidade->ignicao ?? false),
                    'voltagem' => (float) ($unidade->voltagem ?? 0),
                    'quilometragem' => (int) ($unidade->quilometragem ?? 0),
                    'data_evento' => $unidade->data_evento ?
                        Carbon::parse($unidade->data_evento)->format('H:i') : null,
                    'status_movimento' => $statusMovimento,
                    'cor_marker' => $this->determinarCorMarker($unidade, $isOnline, $temProblema, $estaOk),
                    'icone_marker' => $temProblema ? 'build' : 'directions-bus',
                    'online' => $isOnline,
                    'tem_rpr_pendente' => $temRprPendente,
                    'tem_problema' => $temProblema,
                    'rpr_ok' => $estaOk,
                ];
            })->values();

            $lastPage = ceil($total / $perPage);

            return response()->json([
                'status' => 'success',
                'data' => $unidadesMapeadas,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => $lastPage,
                    'has_more' => $page < $lastPage
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar unidades'
            ], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $unidade = Unidade::where('id_unidade', $id)->firstOrFail();
            $isOnline = $this->isOnline($unidade);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $unidade->id_unidade,
                    'numero' => $this->getNumeroUnidade($unidade),
                    'nome' => $unidade->unidade_nome,
                    'placa' => $unidade->placa,
                    'posicao' => [
                        'latitude' => (float) $unidade->lat,
                        'longitude' => (float) $unidade->lon,
                    ],
                    'endereco' => $unidade->endereco_completo,
                    'velocidade' => (int) $unidade->velocidade,
                    'ignicao' => (bool) $unidade->ignicao,
                    'voltagem' => (float) $unidade->voltagem,
                    'quilometragem' => (int) $unidade->quilometragem,
                    'data_evento' => $unidade->data_evento,
                    'online' => $isOnline,
                    'status_movimento' => $this->determinarStatusMovimento($unidade),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unidade não encontrada'
            ], 404);
        }
    }

    private function getNumeroUnidade($unidade): string
    {
        return $unidade->n_ordem ?:
               ($unidade->unidade_nome ? substr($unidade->unidade_nome, 0, 15) :
               'Unidade ' . $unidade->id_unidade);
    }

    private function getEnderecoResumido($endereco): string
    {
        if (!$endereco) return 'Endereço não disponível';
        $parts = explode(',', $endereco);
        return count($parts) > 1 ? trim($parts[0]) : substr($endereco, 0, 50);
    }

    private function isOnline($unidade): bool
    {
        if (!$unidade->data_evento) return false;
        try {
            return Carbon::parse($unidade->data_evento)->diffInMinutes(Carbon::now()) <= 30;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function determinarStatusMovimento($unidade): string
    {
        $velocidade = (int) ($unidade->velocidade ?? 0);
        $ignicao = (bool) ($unidade->ignicao ?? false);

        if ($velocidade > 5) return 'movimento';
        if ($ignicao) return 'parado_ligado';
        return 'parado_desligado';
    }

    private function determinarCorMarker($unidade, $isOnline, $temProblema = false, $estaOk = false): string
    {
        if ($temProblema) return '#FF6B6B';
        if ($estaOk) return '#10B981';
        if (!$isOnline) return '#EF4444';

        $velocidade = (int) ($unidade->velocidade ?? 0);
        $ignicao = (bool) ($unidade->ignicao ?? false);

        if ($velocidade > 5) return '#7BC142';
        if ($ignicao) return '#F59E0B';
        return '#6B7280';
    }
}
