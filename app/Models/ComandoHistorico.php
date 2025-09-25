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
        'ID_Disp' => 'integer',
        'data_solicitacao' => 'datetime',
        'data_envio' => 'datetime',
        'data_confirmacao' => 'datetime',
        'usuario' => 'integer'
    ];

    /**
     * Relacionamento com usuário
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'usuario', 'id_user');
    }

    /**
     * Relacionamento com unidade (por serial)
     */
    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'ID_Disp', 'serial');
    }

    /**
     * Scope para comandos confirmados
     */
    public function scopeConfirmados($query)
    {
        return $query->whereNotNull('data_confirmacao');
    }

    /**
     * Scope para comandos enviados mas não confirmados
     */
    public function scopeNaoConfirmados($query)
    {
        return $query->whereNotNull('data_envio')
                    ->whereNull('data_confirmacao');
    }

    /**
     * Scope para filtrar por período
     */
    public function scopePeriodo($query, $dataInicio, $dataFim = null)
    {
        $query->where('data_solicitacao', '>=', $dataInicio);

        if ($dataFim) {
            $query->where('data_solicitacao', '<=', $dataFim);
        }

        return $query;
    }

    /**
     * Scope para filtrar por usuário
     */
    public function scopeUsuario($query, $userId)
    {
        return $query->where('usuario', $userId);
    }

    /**
     * Obter status do comando
     */
    public function getStatusAttribute(): string
    {
        if ($this->data_confirmacao) {
            return 'confirmado';
        } elseif ($this->data_envio) {
            return 'enviado';
        }

        return 'pendente';
    }

    /**
     * Obter tempo de resposta em minutos
     */
    public function getTempoRespostaAttribute(): ?int
    {
        if (!$this->data_confirmacao || !$this->data_solicitacao) {
            return null;
        }

        return $this->data_confirmacao->diffInMinutes($this->data_solicitacao);
    }

    /**
     * Obter tempo para envio em minutos
     */
    public function getTempoEnvioAttribute(): ?int
    {
        if (!$this->data_envio || !$this->data_solicitacao) {
            return null;
        }

        return $this->data_envio->diffInMinutes($this->data_solicitacao);
    }

    /**
     * Verificar se comando foi bem-sucedido
     */
    public function isSucesso(): bool
    {
        return !is_null($this->data_confirmacao);
    }
}
