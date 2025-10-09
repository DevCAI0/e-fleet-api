<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RprCorrecao extends Model
{
    use HasFactory;

    protected $table = 'rpr_correcoes';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nome',
        'descricao',
        'status',
        'icone',
        'ordem'
    ];

    protected $casts = [
        'status' => 'boolean',
        'ordem' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    /**
     * Tipos que utilizam esta correção (usando Model Pivot)
     */
    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(
            RprTipo::class,
            'rpr_tipo_correcoes',
            'id_rpr_correcao',
            'id_rpr_tipo'
        )
        ->using(RprTipoCorrecao::class)
        ->withPivot('id')
        ->orderBy('rpr_tipos.ordem', 'asc');
    }

    /**
     * Tipos ativos que utilizam esta correção
     */
    public function tiposAtivos(): BelongsToMany
    {
        return $this->belongsToMany(
            RprTipo::class,
            'rpr_tipo_correcoes',
            'id_rpr_correcao',
            'id_rpr_tipo'
        )
        ->using(RprTipoCorrecao::class)
        ->where('rpr_tipos.status', 1)
        ->orderBy('rpr_tipos.ordem', 'asc');
    }

    /**
     * Relacionamento direto com a tabela pivot
     */
    public function tipoCorrecoes(): HasMany
    {
        return $this->hasMany(RprTipoCorrecao::class, 'id_rpr_correcao', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope para buscar apenas correções ativas
     */
    public function scopeAtivas($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para buscar apenas correções inativas
     */
    public function scopeInativas($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para ordenar por ordem de exibição
     */
    public function scopeOrdenadas($query)
    {
        return $query->orderBy('ordem', 'asc');
    }

    /**
     * Scope para buscar por nome (parcial)
     */
    public function scopeBuscarPorNome($query, string $nome)
    {
        return $query->where('nome', 'LIKE', "%{$nome}%");
    }

    /**
     * Scope com tipos carregados
     */
    public function scopeComTipos($query)
    {
        return $query->with('tipos');
    }

    /**
     * Scope com tipos ativos carregados
     */
    public function scopeComTiposAtivos($query)
    {
        return $query->with('tiposAtivos');
    }

    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================

    /**
     * Verifica se a correção está ativa
     */
    public function estaAtiva(): bool
    {
        return $this->status === 1 || $this->status === true;
    }

    /**
     * Verifica se a correção está inativa
     */
    public function estaInativa(): bool
    {
        return !$this->estaAtiva();
    }

    /**
     * Ativar a correção
     */
    public function ativar(): bool
    {
        $this->status = 1;
        return $this->save();
    }

    /**
     * Desativar a correção
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
     * Retorna array de IDs dos tipos que usam esta correção
     */
    public function getIdsTipos(): array
    {
        return $this->tipos()->pluck('id')->toArray();
    }

    /**
     * Retorna array de IDs dos tipos ativos que usam esta correção
     */
    public function getIdsTiposAtivos(): array
    {
        return $this->tiposAtivos()->pluck('id')->toArray();
    }

    /**
     * Adiciona esta correção a um tipo
     */
    public function adicionarAoTipo(int $idTipo): bool
    {
        if (!$this->tipos()->where('id_rpr_tipo', $idTipo)->exists()) {
            $this->tipos()->attach($idTipo);
            return true;
        }
        return false;
    }

    /**
     * Remove esta correção de um tipo
     */
    public function removerDoTipo(int $idTipo): bool
    {
        if ($this->tipos()->where('id_rpr_tipo', $idTipo)->exists()) {
            $this->tipos()->detach($idTipo);
            return true;
        }
        return false;
    }

    /**
     * Sincroniza os tipos desta correção
     */
    public function sincronizarTipos(array $idsTipos): void
    {
        $this->tipos()->sync($idsTipos);
    }

    /**
     * Verifica se está sendo usada em algum tipo ativo
     */
    public function estaEmUso(): bool
    {
        return $this->tiposAtivos()->exists();
    }

    /**
     * Retorna quantidade de tipos que usam esta correção
     */
    public function quantidadeTipos(): int
    {
        return $this->tipos()->count();
    }

    /**
     * Retorna dados formatados para API/Frontend
     */
    public function toArrayApi(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'status' => $this->status,
            'ativa' => $this->estaAtiva(),
            'icone' => $this->icone,
            'ordem' => $this->ordem,
            'em_uso' => $this->estaEmUso(),
            'quantidade_tipos' => $this->quantidadeTipos()
        ];
    }

    // ========================================
    // MÉTODOS ESTÁTICOS
    // ========================================

    /**
     * Retorna todas as correções ativas ordenadas
     */
    public static function getCorrecoesAtivasOrdenadas()
    {
        return self::ativas()
            ->ordenadas()
            ->get();
    }

    /**
     * Busca correção por nome exato
     */
    public static function buscarPorNome(string $nome): ?self
    {
        return self::where('nome', $nome)->first();
    }

    /**
     * Retorna correções para select/dropdown
     */
    public static function paraSelect(): array
    {
        return self::ativas()
            ->ordenadas()
            ->get()
            ->pluck('nome', 'id')
            ->toArray();
    }

    /**
     * Retorna correções de um tipo específico
     */
    public static function getCorrecoesPorTipo(int $idTipo)
    {
        return self::whereHas('tipos', function($query) use ($idTipo) {
            $query->where('rpr_tipos.id', $idTipo);
        })
        ->ativas()
        ->ordenadas()
        ->get();
    }
}
