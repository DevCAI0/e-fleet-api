<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RprCicloVeiculo extends Model
{
    use HasFactory;

    protected $table = 'rpr_ciclo_veiculos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_ciclo',
        'id_unidade', // CORRIGIDO: de 'id' para 'id_unidade'
        'status_inspecao',
        'id_rpr',
        'data_inspecao',
        'id_user_inspecao',
        'observacao'
    ];

    protected $casts = [
        'data_inspecao' => 'datetime',
        'id_ciclo' => 'integer',
        'id_unidade' => 'integer', // CORRIGIDO
        'id_rpr' => 'integer',
        'id_user_inspecao' => 'integer'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function ciclo()
    {
        return $this->belongsTo(RprCiclo::class, 'id_ciclo');
    }

    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade', 'id');
    }

    public function rpr()
    {
        return $this->belongsTo(Rpr::class, 'id_rpr');
    }

    public function usuarioInspecao()
    {
        return $this->belongsTo(User::class, 'id_user_inspecao', 'id');
    }

    // ========================================
    // MÉTODOS
    // ========================================

    public function foiInspecionado(): bool
    {
        return !in_array($this->status_inspecao, ['PENDENTE', 'NAO_INSPECIONADO']);
    }

    public function temProblema(): bool
    {
        return $this->status_inspecao === 'COM_PROBLEMA';
    }

    public function estaAprovado(): bool
    {
        return $this->status_inspecao === 'APROVADO';
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopePendentes($query)
    {
        return $query->where('status_inspecao', 'PENDENTE');
    }

    public function scopeInspecionados($query)
    {
        return $query->whereNotIn('status_inspecao', ['PENDENTE', 'NAO_INSPECIONADO']);
    }

    public function scopeComProblema($query)
    {
        return $query->where('status_inspecao', 'COM_PROBLEMA');
    }

    public function scopeAprovados($query)
    {
        return $query->where('status_inspecao', 'APROVADO');
    }
}
