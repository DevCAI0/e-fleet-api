<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UsuarioLocalizacao extends Model
{
    use HasFactory;

    protected $table = 'usuarios_localizacao';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'latitude',
        'longitude',
        'velocidade',
        'endereco',
        'precisao',
        'tipo_atividade',
        'id_unidade_atual',
        'data_atualizacao',
        'ativo'
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'velocidade' => 'float',
        'precisao' => 'float',
        'id_unidade_atual' => 'integer',
        'data_atualizacao' => 'datetime',
        'ativo' => 'boolean'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }

    public function unidadeAtual()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade_atual', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeOnline($query, $minutosMaximo = 5)
    {
        return $query->where('data_atualizacao', '>=', now()->subMinutes($minutosMaximo));
    }

    public function scopeEmDeslocamento($query)
    {
        return $query->where('tipo_atividade', 'EM_DESLOCAMENTO');
    }

    public function scopeNoLocal($query)
    {
        return $query->where('tipo_atividade', 'NO_LOCAL');
    }

    // ========================================
    // MÉTODOS
    // ========================================

    public function isOnline($minutosMaximo = 5): bool
    {
        if (!$this->data_atualizacao) {
            return false;
        }

        return Carbon::parse($this->data_atualizacao)
            ->diffInMinutes(Carbon::now()) <= $minutosMaximo;
    }

    public function getTempoUltimaAtualizacao(): string
    {
        if (!$this->data_atualizacao) {
            return 'Nunca';
        }

        $minutos = now()->diffInMinutes($this->data_atualizacao);

        if ($minutos < 1) {
            return 'Agora';
        }

        if ($minutos < 60) {
            return "{$minutos} min atrás";
        }

        if ($minutos < 1440) {
            $horas = floor($minutos / 60);
            return "{$horas}h atrás";
        }

        $dias = floor($minutos / 1440);
        return "{$dias} dia" . ($dias > 1 ? 's' : '') . ' atrás';
    }

    public function atualizarLocalizacao(array $dados): void
    {
        $this->update([
            'latitude' => $dados['latitude'],
            'longitude' => $dados['longitude'],
            'velocidade' => $dados['velocidade'] ?? 0,
            'endereco' => $dados['endereco'] ?? null,
            'precisao' => $dados['precisao'] ?? null,
            'tipo_atividade' => $this->determinarTipoAtividade($dados),
            'id_unidade_atual' => $dados['id_unidade_atual'] ?? $this->id_unidade_atual,
            'data_atualizacao' => now(),
            'ativo' => true
        ]);
    }

    private function determinarTipoAtividade(array $dados): string
    {
        $velocidade = $dados['velocidade'] ?? 0;

        if ($velocidade > 5) {
            return 'EM_DESLOCAMENTO';
        }

        if (isset($dados['id_unidade_atual']) && $dados['id_unidade_atual']) {
            return 'NO_LOCAL';
        }

        return 'PARADO';
    }
}
