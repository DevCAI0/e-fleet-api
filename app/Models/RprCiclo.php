<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RprCiclo extends Model
{
    use HasFactory;

    protected $table = 'rpr_ciclos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'data_inicio',
        'data_fim',
        'status',
        'total_veiculos',
        'inspecionados',
        'aprovados',
        'com_problema',
        'id_user_criacao',
        'data_criacao'
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_criacao' => 'datetime',
        'total_veiculos' => 'integer',
        'inspecionados' => 'integer',
        'aprovados' => 'integer',
        'com_problema' => 'integer',
        'id_user_criacao' => 'integer'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function veiculos()
    {
        return $this->hasMany(RprCicloVeiculo::class, 'id_ciclo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_user_criacao', 'id');
    }

    // ========================================
    // MÉTODOS
    // ========================================

    public function estaAtivo(): bool
    {
        return $this->status === 'ATIVO' &&
               Carbon::now()->between($this->data_inicio, $this->data_fim->endOfDay());
    }

    public function expirou(): bool
    {
        return Carbon::now()->greaterThan($this->data_fim->endOfDay());
    }

    public function getPercentualConclusao(): float
    {
        if ($this->total_veiculos === 0) return 0;
        return round(($this->inspecionados / $this->total_veiculos) * 100, 2);
    }

    public function getPercentualAprovacao(): float
    {
        if ($this->inspecionados === 0) return 0;
        return round(($this->aprovados / $this->inspecionados) * 100, 2);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivo($query)
    {
        return $query->where('status', 'ATIVO');
    }

    public function scopeExpirado($query)
    {
        return $query->where('status', 'EXPIRADO');
    }

    public function scopeFinalizado($query)
    {
        return $query->where('status', 'FINALIZADO');
    }
}
