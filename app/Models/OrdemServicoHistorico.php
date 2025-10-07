<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServicoHistorico extends Model
{
    protected $table = 'ordem_servico_historico';
    public $timestamps = false;

    protected $fillable = [
        'id_ordem_servico',
        'id_user',
        'acao',
        'detalhes',
        'data_acao'
    ];

    protected $casts = [
        'data_acao' => 'datetime'
    ];

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class, 'id_ordem_servico');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
