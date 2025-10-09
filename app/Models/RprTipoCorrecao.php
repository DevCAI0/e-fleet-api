<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RprTipoCorrecao extends Pivot
{
    use HasFactory;

    protected $table = 'rpr_tipo_correcoes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'id_rpr_tipo',
        'id_rpr_correcao'
    ];

    protected $casts = [
        'id_rpr_tipo' => 'integer',
        'id_rpr_correcao' => 'integer'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    /**
     * Tipo relacionado
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(RprTipo::class, 'id_rpr_tipo', 'id');
    }

    /**
     * Correção relacionada
     */
    public function correcao(): BelongsTo
    {
        return $this->belongsTo(RprCorrecao::class, 'id_rpr_correcao', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para buscar por tipo
     */
    public function scopePorTipo($query, int $idTipo)
    {
        return $query->where('id_rpr_tipo', $idTipo);
    }

    /**
     * Scope para buscar por correção
     */
    public function scopePorCorrecao($query, int $idCorrecao)
    {
        return $query->where('id_rpr_correcao', $idCorrecao);
    }

    /**
     * Scope para buscar apenas tipos ativos
     */
    public function scopeComTiposAtivos($query)
    {
        return $query->whereHas('tipo', function($q) {
            $q->where('status', 1);
        });
    }

    /**
     * Scope para buscar apenas correções ativas
     */
    public function scopeComCorrecoesAtivas($query)
    {
        return $query->whereHas('correcao', function($q) {
            $q->where('status', 1);
        });
    }

    /**
     * Scope com tipo e correção carregados
     */
    public function scopeComRelacionamentos($query)
    {
        return $query->with(['tipo', 'correcao']);
    }

    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================

    /**
     * Verifica se o tipo está ativo
     */
    public function tipoEstaAtivo(): bool
    {
        return $this->tipo && $this->tipo->estaAtivo();
    }

    /**
     * Verifica se a correção está ativa
     */
    public function correcaoEstaAtiva(): bool
    {
        return $this->correcao && $this->correcao->estaAtiva();
    }

    /**
     * Verifica se ambos estão ativos
     */
    public function ambosAtivos(): bool
    {
        return $this->tipoEstaAtivo() && $this->correcaoEstaAtiva();
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    /**
     * Retorna todas as correções de um tipo específico
     */
    public static function getCorrecoesPorTipo(int $idTipo)
    {
        return self::where('id_rpr_tipo', $idTipo)
            ->with('correcao')
            ->get()
            ->pluck('correcao');
    }

    /**
     * Retorna todos os tipos que usam uma correção específica
     */
    public static function getTiposPorCorrecao(int $idCorrecao)
    {
        return self::where('id_rpr_correcao', $idCorrecao)
            ->with('tipo')
            ->get()
            ->pluck('tipo');
    }

    /**
     * Verifica se existe relacionamento entre tipo e correção
     */
    public static function existeRelacionamento(int $idTipo, int $idCorrecao): bool
    {
        return self::where('id_rpr_tipo', $idTipo)
            ->where('id_rpr_correcao', $idCorrecao)
            ->exists();
    }

    /**
     * Cria relacionamento se não existir
     */
    public static function criarSeNaoExistir(int $idTipo, int $idCorrecao): bool
    {
        if (!self::existeRelacionamento($idTipo, $idCorrecao)) {
            self::create([
                'id_rpr_tipo' => $idTipo,
                'id_rpr_correcao' => $idCorrecao
            ]);
            return true;
        }
        return false;
    }

    /**
     * Remove relacionamento se existir
     */
    public static function removerSeExistir(int $idTipo, int $idCorrecao): bool
    {
        if (self::existeRelacionamento($idTipo, $idCorrecao)) {
            self::where('id_rpr_tipo', $idTipo)
                ->where('id_rpr_correcao', $idCorrecao)
                ->delete();
            return true;
        }
        return false;
    }

    /**
     * Retorna quantidade de correções de um tipo
     */
    public static function contarCorrecoesPorTipo(int $idTipo): int
    {
        return self::where('id_rpr_tipo', $idTipo)->count();
    }

    /**
     * Retorna quantidade de tipos que usam uma correção
     */
    public static function contarTiposPorCorrecao(int $idCorrecao): int
    {
        return self::where('id_rpr_correcao', $idCorrecao)->count();
    }

    /**
     * Retorna todos os relacionamentos ativos (tipo e correção ativos)
     */
    public static function getRelacionamentosAtivos()
    {
        return self::comTiposAtivos()
            ->comCorrecoesAtivas()
            ->comRelacionamentos()
            ->get();
    }
}
