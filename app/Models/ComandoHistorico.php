<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComandoHistorico extends Model
{
    use HasFactory;

    protected $table = 'comando_historico';
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

    public function scopeConfirmados($query)
    {
        return $query->whereNotNull('data_confirmacao');
    }

    public function scopeNaoConfirmados($query)
    {
        return $query->whereNotNull('data_envio')
                    ->whereNull('data_confirmacao');
    }

    public function scopePeriodo($query, $dataInicio, $dataFim = null)
    {
        $query->where('data_solicitacao', '>=', $dataInicio);

        if ($dataFim) {
            $query->where('data_solicitacao', '<=', $dataFim);
        }

        return $query;
    }

    public function scopeUsuario($query, $userId)
    {
        return $query->where('usuario', $userId);
    }

    public function scopeSerial($query, $serial)
    {
        return $query->where('ID_Disp', $serial);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('comando_nome', $tipo);
    }

    public function scopeRecentes($query, $dias = 30)
    {
        return $query->whereDate('data_solicitacao', '>=', now()->subDays($dias));
    }

    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================

    public function isSucesso(): bool
    {
        return !is_null($this->data_confirmacao);
    }

    public function isFalha(): bool
    {
        return !is_null($this->data_envio) && is_null($this->data_confirmacao);
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

    public function getTempoRespostaAttribute(): ?int
    {
        if (!$this->data_confirmacao || !$this->data_solicitacao) {
            return null;
        }

        return $this->data_confirmacao->diffInMinutes($this->data_solicitacao);
    }

    public function getTempoEnvioAttribute(): ?int
    {
        if (!$this->data_envio || !$this->data_solicitacao) {
            return null;
        }

        return $this->data_envio->diffInMinutes($this->data_solicitacao);
    }

    public function getTempoRespostaFormatadoAttribute(): ?string
    {
        $minutos = $this->tempo_resposta;

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

    public function getTempoEnvioFormatadoAttribute(): ?string
    {
        $minutos = $this->tempo_envio;

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
