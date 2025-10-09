<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RprTipo extends Model
{
    use HasFactory;

    protected $table = 'rpr_tipos';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'numero',
        'titulo',
        'descricao',
        'status',
        'requer_correcao',
        'ordem',
        'cor_badge'
    ];

    protected $casts = [
        'numero' => 'integer',
        'status' => 'boolean',
        'requer_correcao' => 'boolean',
        'ordem' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    /**
     * Correções disponíveis para este tipo (usando Model Pivot)
     */
    public function correcoes(): BelongsToMany
    {
        return $this->belongsToMany(
            RprCorrecao::class,
            'rpr_tipo_correcoes',
            'id_rpr_tipo',
            'id_rpr_correcao'
        )
        ->using(RprTipoCorrecao::class)
        ->withPivot('id')
        ->orderBy('rpr_correcoes.ordem', 'asc');
    }

    /**
     * Correções ativas disponíveis para este tipo
     */
    public function correcoesAtivas(): BelongsToMany
    {
        return $this->belongsToMany(
            RprCorrecao::class,
            'rpr_tipo_correcoes',
            'id_rpr_tipo',
            'id_rpr_correcao'
        )
        ->using(RprTipoCorrecao::class)
        ->where('rpr_correcoes.status', 1)
        ->orderBy('rpr_correcoes.ordem', 'asc');
    }

    /**
     * Relacionamento direto com a tabela pivot
     */
    public function tipoCorrecoes(): HasMany
    {
        return $this->hasMany(RprTipoCorrecao::class, 'id_rpr_tipo', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para buscar apenas tipos ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para buscar apenas tipos inativos
     */
    public function scopeInativos($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para ordenar por ordem de exibição
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem', 'asc');
    }

    /**
     * Scope para tipos que requerem correção
     */
    public function scopeRequerCorrecao($query)
    {
        return $query->where('requer_correcao', 1);
    }

    /**
     * Scope para tipos que NÃO requerem correção
     */
    public function scopeNaoRequerCorrecao($query)
    {
        return $query->where('requer_correcao', 0);
    }

    /**
     * Scope para buscar por número do tipo
     */
    public function scopePorNumero($query, int $numero)
    {
        return $query->where('numero', $numero);
    }

    /**
     * Scope com correções carregadas
     */
    public function scopeComCorrecoes($query)
    {
        return $query->with('correcoes');
    }

    /**
     * Scope com correções ativas carregadas
     */
    public function scopeComCorrecoesAtivas($query)
    {
        return $query->with('correcoesAtivas');
    }

    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================

    /**
     * Verifica se o tipo está ativo
     */
    public function estaAtivo(): bool
    {
        return $this->status === 1 || $this->status === true;
    }

    /**
     * Verifica se o tipo está inativo
     */
    public function estaInativo(): bool
    {
        return !$this->estaAtivo();
    }

    /**
     * Verifica se o tipo requer seleção de correção
     */
    public function precisaCorrecao(): bool
    {
        return $this->requer_correcao === 1 || $this->requer_correcao === true;
    }

    /**
     * Ativar o tipo
     */
    public function ativar(): bool
    {
        $this->status = 1;
        return $this->save();
    }

    /**
     * Desativar o tipo
     */
    public function desativar(): bool
    {
        $this->status = 0;
        return $this->save();
    }

    /**
     * Alterna o status (ativo/inativo)
     */
    public function toggleStatus(): bool
    {
        $this->status = !$this->status;
        return $this->save();
    }

    /**
     * Retorna array de IDs das correções deste tipo
     */
    public function getIdsCorrecoes(): array
    {
        return $this->correcoes()->pluck('id')->toArray();
    }

    /**
     * Retorna array de IDs das correções ativas deste tipo
     */
    public function getIdsCorrecoesAtivas(): array
    {
        return $this->correcoesAtivas()->pluck('id')->toArray();
    }

    /**
     * Adiciona uma correção ao tipo
     */
    public function adicionarCorrecao(int $idCorrecao): bool
    {
        if (!$this->correcoes()->where('id_rpr_correcao', $idCorrecao)->exists()) {
            $this->correcoes()->attach($idCorrecao);
            return true;
        }
        return false;
    }

    /**
     * Remove uma correção do tipo
     */
    public function removerCorrecao(int $idCorrecao): bool
    {
        if ($this->correcoes()->where('id_rpr_correcao', $idCorrecao)->exists()) {
            $this->correcoes()->detach($idCorrecao);
            return true;
        }
        return false;
    }

    /**
     * Sincroniza as correções do tipo
     */
    public function sincronizarCorrecoes(array $idsCorrecoes): void
    {
        $this->correcoes()->sync($idsCorrecoes);
    }

    /**
     * Retorna dados formatados para API/Frontend
     */
    public function toArrayApi(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'status' => $this->status,
            'ativo' => $this->estaAtivo(),
            'requer_correcao' => $this->requer_correcao,
            'ordem' => $this->ordem,
            'cor_badge' => $this->cor_badge,
            'correcoes' => $this->correcoesAtivas->map(function($correcao) {
                return [
                    'id' => $correcao->id,
                    'nome' => $correcao->nome,
                    'ordem' => $correcao->ordem
                ];
            })
        ];
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    /**
     * Retorna todos os tipos ativos ordenados
     */
    public static function getTiposAtivosOrdenados()
    {
        return self::ativos()
            ->ordenados()
            ->comCorrecoesAtivas()
            ->get();
    }

    /**
     * Busca tipo por número
     */
    public static function buscarPorNumero(int $numero): ?self
    {
        return self::where('numero', $numero)->first();
    }

    /**
     * Retorna tipos para select/dropdown
     */
    public static function paraSelect(): array
    {
        return self::ativos()
            ->ordenados()
            ->get()
            ->pluck('titulo', 'id')
            ->toArray();
    }
}
