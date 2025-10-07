<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServicoVeiculo extends Model
{
    protected $table = 'ordem_servico_veiculos';
    public $timestamps = false;

    protected $fillable = [
        'id_ordem_servico',
        'id_unidade',      // ← ADICIONE ESTE CAMPO
        'id_rpr',
        'status_veiculo',
        'problemas_identificados',
        'servicos_realizados',
        'observacoes_tecnico',
        'data_inicio_manutencao',
        'data_conclusao_manutencao'
    ];

    protected $casts = [
        'data_inicio_manutencao' => 'datetime',
        'data_conclusao_manutencao' => 'datetime'
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'id_ordem_servico');
    }

    public function unidade()
    {
        // CORRIGIDO: 'id_unidade' é a foreign key em ordem_servico_veiculos
        // 'id' é a primary key em unidades
        return $this->belongsTo(Unidade::class, 'id_unidade', 'id');
    }

    public function rpr()
    {
        return $this->belongsTo(Rpr::class, 'id_rpr');
    }

    public function getProblemasArray(): array
    {
        return json_decode($this->problemas_identificados, true) ?? [];
    }

    public function setProblemasArray(array $problemas): void
    {
        $this->problemas_identificados = json_encode($problemas);
    }
}
