<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'logo',
        'cor_primaria',
        'cor_secundaria',
        'gerenciavel',
        'classificacao',
        'id_cadastro',
        'data_cadastro',
        'id_alteracao',
        'data_alteracao',
        'status',
        'sigla',
        'telefone',
        'cnae',
        'id_crt',
        'inscricao_estadual_substituto_tributario',
        'email',
        'tar',
        'contribuinte_simples_nacional',
        'liberacao_periodo_vendas'
    ];

    protected $casts = [
        'gerenciavel' => 'boolean',
        'classificacao' => 'integer',
        'id_cadastro' => 'integer',
        'data_cadastro' => 'datetime',
        'id_alteracao' => 'integer',
        'data_alteracao' => 'datetime',
        'status' => 'boolean',
        'id_crt' => 'integer',
        'tar' => 'integer',
        'contribuinte_simples_nacional' => 'boolean',
        'liberacao_periodo_vendas' => 'date'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function unidades()
    {
        return $this->hasMany(Unidade::class, 'id_empresa', 'id');
    }

    public function usuarioCadastro()
    {
        return $this->belongsTo(User::class, 'id_cadastro', 'id');
    }

    public function usuarioAlteracao()
    {
        return $this->belongsTo(User::class, 'id_alteracao', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivas($query)
    {
        return $query->where('status', 1);
    }

    public function scopeGerenciaveis($query)
    {
        return $query->where('gerenciavel', 1);
    }

    // ========================================
    // MÉTODOS DE VERIFICAÇÃO
    // ========================================

    public function isAtiva(): bool
    {
        return $this->status === true || $this->status === 1;
    }

    public function isGerenciavel(): bool
    {
        return $this->gerenciavel === true || $this->gerenciavel === 1;
    }

    // ========================================
    // ATTRIBUTES
    // ========================================

    public function getNomeCompletoAttribute(): string
    {
        return $this->razao_social;
    }

    public function getNomeExibicaoAttribute(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function getSiglaFormatadaAttribute(): string
    {
        return strtoupper($this->sigla ?? '');
    }
}
