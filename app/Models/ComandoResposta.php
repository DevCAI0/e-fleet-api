<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComandoResposta extends Model
{
    use HasFactory;

    protected $table = 'comando_resposta';
    protected $primaryKey = 'id_comando_resposta';
    public $timestamps = false;

    protected $fillable = [
        'id_unidade',
        'string',
        'data_recebimento'
    ];

    protected $casts = [
        'id_unidade' => 'integer',
        'data_recebimento' => 'datetime'
    ];

    /**
     * Relacionamento com unidade (por serial)
     */
    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade', 'serial');
    }

    /**
     * Scope para filtrar por período
     */
    public function scopePeriodo($query, $dataInicio, $dataFim = null)
    {
        $query->where('data_recebimento', '>=', $dataInicio);

        if ($dataFim) {
            $query->where('data_recebimento', '<=', $dataFim);
        }

        return $query;
    }

    /**
     * Scope para filtrar por unidade
     */
    public function scopeUnidade($query, $idUnidade)
    {
        return $query->where('id_unidade', $idUnidade);
    }

    /**
     * Scope para ordenar por mais recente
     */
    public function scopeRecentes($query)
    {
        return $query->orderBy('data_recebimento', 'desc');
    }

    /**
     * Scope para buscar por conteúdo da string
     */
    public function scopeConteudo($query, $conteudo)
    {
        return $query->where('string', 'like', '%' . $conteudo . '%');
    }

    /**
     * Verificar se é resposta de comando específico
     */
    public function isRespostaComando($tipoComando): bool
    {
        return str_contains($this->string, $tipoComando);
    }

    /**
     * Obter tipo de resposta baseado no conteúdo
     */
    public function getTipoRespostaAttribute(): string
    {
        $string = strtoupper($this->string);

        if (str_contains($string, 'ACK')) {
            return 'confirmacao';
        }

        if (str_contains($string, 'REBOOT')) {
            return 'reboot';
        }

        if (str_contains($string, 'SETODOMETER')) {
            return 'hodometro';
        }

        if (str_contains($string, 'NETWORK')) {
            return 'rede';
        }

        if (str_contains($string, 'SPEED')) {
            return 'velocidade';
        }

        return 'generico';
    }

    /**
     * Formatar string para exibição
     */
    public function getStringFormatadaAttribute(): string
    {
        // Remove caracteres especiais e formata para melhor leitura
        return trim(preg_replace('/[^\x20-\x7E]/', '', $this->string));
    }

    /**
     * Verificar se é resposta de sucesso
     */
    public function isSucesso(): bool
    {
        $string = strtoupper($this->string);
        return str_contains($string, 'ACK') || str_contains($string, 'OK');
    }

    /**
     * Verificar se é resposta de erro
     */
    public function isErro(): bool
    {
        $string = strtoupper($this->string);
        return str_contains($string, 'ERROR') || str_contains($string, 'FAIL');
    }

    /**
     * Obter idade da resposta em minutos
     */
    public function getIdadeMinutosAttribute(): int
    {
        return now()->diffInMinutes($this->data_recebimento);
    }
}
