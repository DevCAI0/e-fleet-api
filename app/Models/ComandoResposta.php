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
        'id',
        'string',
        'data_recebimento'
    ];

    protected $casts = [
        'id' => 'string',
        'data_recebimento' => 'datetime'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id', 'serial');
    }

    public function unidade()
    {
        return $this->hasOneThrough(
            Unidade::class,
            Modulo::class,
            'serial',
            'id_modulo',
            'id',
            'id'
        );
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopePeriodo($query, $dataInicio, $dataFim = null)
    {
        $query->where('data_recebimento', '>=', $dataInicio);

        if ($dataFim) {
            $query->where('data_recebimento', '<=', $dataFim);
        }

        return $query;
    }

    public function scopeSerial($query, $serial)
    {
        return $query->where('id', $serial);
    }

    public function scopeRecentes($query)
    {
        return $query->orderBy('data_recebimento', 'desc');
    }

    public function scopeConteudo($query, $conteudo)
    {
        return $query->where('string', 'like', '%' . $conteudo . '%');
    }

    public function scopeHoje($query)
    {
        return $query->whereDate('data_recebimento', now()->toDateString());
    }

    public function scopeUltimos($query, $quantidade = 10)
    {
        return $query->orderBy('data_recebimento', 'desc')
                    ->limit($quantidade);
    }

    // ========================================
    // MÉTODOS DE VERIFICAÇÃO
    // ========================================

    public function isRespostaComando($tipoComando): bool
    {
        return str_contains(strtoupper($this->string), strtoupper($tipoComando));
    }

    public function isSucesso(): bool
    {
        $string = strtoupper($this->string);
        return str_contains($string, 'ACK') || str_contains($string, 'OK');
    }

    public function isErro(): bool
    {
        $string = strtoupper($this->string);
        return str_contains($string, 'ERROR') ||
               str_contains($string, 'FAIL') ||
               str_contains($string, 'NACK');
    }

    // ========================================
    // ATTRIBUTES
    // ========================================

    public function getTipoRespostaAttribute(): string
    {
        $string = strtoupper($this->string);

        if (str_contains($string, 'ACK')) {
            return 'confirmacao';
        }

        if (str_contains($string, 'REBOOT')) {
            return 'reboot';
        }

        if (str_contains($string, 'SETODOMETER') || str_contains($string, 'ODOMETER')) {
            return 'hodometro';
        }

        if (str_contains($string, 'NETWORK') || str_contains($string, 'NTW')) {
            return 'rede';
        }

        if (str_contains($string, 'SPEED') || str_contains($string, 'SVC')) {
            return 'velocidade';
        }

        return 'generico';
    }

    public function getStringFormatadaAttribute(): string
    {
        return trim(preg_replace('/[^\x20-\x7E]/', '', $this->string));
    }

    public function getIdadeMinutosAttribute(): int
    {
        return now()->diffInMinutes($this->data_recebimento);
    }

    public function getIdadeFormatadaAttribute(): string
    {
        $minutos = $this->idade_minutos;

        if ($minutos < 1) {
            return 'Agora mesmo';
        }

        if ($minutos < 60) {
            return "Há {$minutos} min";
        }

        if ($minutos < 1440) { // menos de 24h
            $horas = floor($minutos / 60);
            return "Há {$horas}h";
        }

        $dias = floor($minutos / 1440);
        return "Há {$dias} dia" . ($dias > 1 ? 's' : '');
    }

    public function getStatusCorAttribute(): string
    {
        if ($this->isSucesso()) {
            return 'success';
        }

        if ($this->isErro()) {
            return 'danger';
        }

        return 'info';
    }

    public function getIconeAttribute(): string
    {
        return match($this->tipo_resposta) {
            'confirmacao' => 'check-circle',
            'reboot' => 'refresh-cw',
            'hodometro' => 'gauge',
            'rede' => 'wifi',
            'velocidade' => 'activity',
            default => 'message-square'
        };
    }
}
