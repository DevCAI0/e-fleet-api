<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComandoPendente extends Model
{
    use HasFactory;

    protected $table = 'comando_pendente';
    protected $primaryKey = 'nsu_comando';
    public $timestamps = false;

    protected $fillable = [
        'ID_Disp',
        'comando_nome',
        'comando_string',
        'data_solicitacao',
        'data_envio',
        'data_confirmacao',
        'usuario',
        'observacao'
    ];

    protected $casts = [
        'data_solicitacao' => 'datetime',
        'data_envio' => 'datetime',
        'data_confirmacao' => 'datetime',
        'usuario' => 'integer'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function user()
    {
        return $this->belongsTo(User::class, 'usuario', 'id');
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'ID_Disp', 'serial');
    }

    public function unidade()
    {
        return $this->hasOneThrough(
            Unidade::class,
            Modulo::class,
            'serial',
            'id_modulo',
            'ID_Disp',
            'id'
        );
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopePendentes($query)
    {
        return $query->whereNull('data_envio');
    }

    public function scopeEnviados($query)
    {
        return $query->whereNotNull('data_envio');
    }

    public function scopeConfirmados($query)
    {
        return $query->whereNotNull('data_confirmacao');
    }

    public function scopeNaoConfirmados($query)
    {
        return $query->whereNotNull('data_envio')
                    ->whereNull('data_confirmacao');
    }

    public function scopeSerial($query, $serial)
    {
        return $query->where('ID_Disp', $serial);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('comando_nome', $tipo);
    }

    public function scopeUsuario($query, $userId)
    {
        return $query->where('usuario', $userId);
    }

    // ========================================
    // MÉTODOS DE STATUS
    // ========================================

    public function isPendente(): bool
    {
        return is_null($this->data_envio);
    }

    public function isEnviado(): bool
    {
        return !is_null($this->data_envio);
    }

    public function isConfirmado(): bool
    {
        return !is_null($this->data_confirmacao);
    }

    public function marcarComoEnviado(): bool
    {
        $this->data_envio = now();
        return $this->save();
    }

    public function marcarComoConfirmado(): bool
    {
        $this->data_confirmacao = now();
        return $this->save();
    }

    // ========================================
    // ATTRIBUTES
    // ========================================

    public function getStatusAttribute(): string
    {
        if ($this->data_confirmacao) {
            return 'confirmado';
        } elseif ($this->data_envio) {
            return 'enviado';
        }

        return 'pendente';
    }

    public function getStatusCorAttribute(): string
    {
        return match($this->status) {
            'confirmado' => 'success',
            'enviado' => 'warning',
            'pendente' => 'info',
            default => 'secondary'
        };
    }

    public function getTempoEsperaAttribute(): ?int
    {
        if (!$this->data_solicitacao) {
            return null;
        }

        return now()->diffInMinutes($this->data_solicitacao);
    }

    public function getTempoEsperaFormatadoAttribute(): ?string
    {
        $minutos = $this->tempo_espera;

        if ($minutos === null) {
            return null;
        }

        if ($minutos < 60) {
            return "{$minutos}min";
        }

        $horas = floor($minutos / 60);
        $mins = $minutos % 60;

        return "{$horas}h {$mins}min";
    }
}
