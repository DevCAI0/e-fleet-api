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
     * Scope para comandos pendentes
     */
    public function scopePendentes($query)
    {
        return $query->whereNull('data_envio');
    }

    /**
     * Scope para comandos enviados
     */
    public function scopeEnviados($query)
    {
        return $query->whereNotNull('data_envio');
    }

    /**
     * Scope para comandos confirmados
     */
    public function scopeConfirmados($query)
    {
        return $query->whereNotNull('data_confirmacao');
    }

    /**
     * Scope para comandos não confirmados
     */
    public function scopeNaoConfirmados($query)
    {
        return $query->whereNotNull('data_envio')
                    ->whereNull('data_confirmacao');
    }

    /**
     * Verificar se comando está pendente
     */
    public function isPendente(): bool
    {
        return is_null($this->data_envio);
    }

    /**
     * Verificar se comando foi enviado
     */
    public function isEnviado(): bool
    {
        return !is_null($this->data_envio);
    }

    /**
     * Verificar se comando foi confirmado
     */
    public function isConfirmado(): bool
    {
        return !is_null($this->data_confirmacao);
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
     * Obter tempo desde solicitação em minutos
     */
    public function getTempoEsperaAttribute(): ?int
    {
        if (!$this->data_solicitacao) {
            return null;
        }

        return now()->diffInMinutes($this->data_solicitacao);
    }

    /**
     * Marcar comando como enviado
     */
    public function marcarComoEnviado(): bool
    {
        $this->data_envio = now();
        return $this->save();
    }

    /**
     * Marcar comando como confirmado
     */
    public function marcarComoConfirmado(): bool
    {
        $this->data_confirmacao = now();
        return $this->save();
    }
}
