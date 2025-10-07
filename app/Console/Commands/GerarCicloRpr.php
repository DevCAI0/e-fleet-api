<?php

namespace App\Console\Commands;

use App\Models\RprCiclo;
use App\Models\RprCicloVeiculo;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GerarCicloRpr extends Command
{
    protected $signature = 'rpr:gerar-ciclo';
    protected $description = 'Gera um novo ciclo de RPR com todos os veículos ativos (executa aos domingos)';

    public function handle()
    {
        try {
            // Verifica se hoje é domingo
            // if (!Carbon::now()->isSunday()) {
            //     $this->warn('Este comando deve ser executado apenas aos domingos');
            //     return 1;
            // }

            DB::beginTransaction();

            // Expira ciclos antigos ainda ativos
            RprCiclo::where('status', 'ATIVO')
                ->where('data_fim', '<', Carbon::now())
                ->update(['status' => 'EXPIRADO']);

            // Define as datas do ciclo
            $dataInicio = Carbon::now()->startOfDay(); // Domingo
            $dataFim = Carbon::now()->next(Carbon::TUESDAY)->endOfDay(); // Terça

            // Verifica se já existe ciclo para este período
            $cicloExistente = RprCiclo::where('data_inicio', $dataInicio->format('Y-m-d'))->first();

            if ($cicloExistente) {
                $this->warn('Já existe um ciclo para este período');
                DB::rollBack();
                return 1;
            }

            // Busca todos os veículos ativos
            // CORRIGIDO: status é boolean (1 ou true), não 'S'
            // CORRIGIDO: verificar se tem módulo com serial
            $veiculos = Unidade::where('status', 1)
                ->where('ativo', 1) // Verifica se está ativo também
                ->whereHas('modulo', function($query) {
                    $query->whereNotNull('serial')
                          ->where('serial', '!=', '')
                          ->where('status', 1);
                })
                ->with('modulo') // Carrega o relacionamento
                ->get();

            if ($veiculos->isEmpty()) {
                $this->error('Nenhum veículo ativo encontrado');
                DB::rollBack();
                return 1;
            }

            // Cria o novo ciclo
            $ciclo = RprCiclo::create([
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'status' => 'ATIVO',
                'total_veiculos' => $veiculos->count(),
                'inspecionados' => 0,
                'aprovados' => 0,
                'com_problema' => 0,
                'data_criacao' => Carbon::now()
            ]);

            // Adiciona todos os veículos ao ciclo
            foreach ($veiculos as $veiculo) {
                RprCicloVeiculo::create([
                    'id_ciclo' => $ciclo->id,
                    'id_unidade' => $veiculo->id, // CORRIGIDO: usar 'id' da tabela unidades
                    'status_inspecao' => 'PENDENTE'
                ]);
            }

            DB::commit();

            $this->info("Ciclo RPR #{$ciclo->id} criado com sucesso!");
            $this->info("Período: {$dataInicio->format('d/m/Y')} a {$dataFim->format('d/m/Y')}");
            $this->info("Total de veículos: {$veiculos->count()}");

            Log::info('Ciclo RPR gerado', [
                'id_ciclo' => $ciclo->id,
                'total_veiculos' => $veiculos->count(),
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Erro ao gerar ciclo: ' . $e->getMessage());
            Log::error('Erro ao gerar ciclo RPR', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
