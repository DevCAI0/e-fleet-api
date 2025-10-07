<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'serial',
        'modelo',
        'fabricante',
        'situacao',
        'observacao',
        'id_cadastro',
        'data_cadastro',
        'id_alteracao',
        'data_alteracao',
        'status'
    ];

    protected $casts = [
        'situacao' => 'integer',
        'id_cadastro' => 'integer',
        'data_cadastro' => 'datetime',
        'id_alteracao' => 'integer',
        'data_alteracao' => 'datetime',
        'status' => 'boolean'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function unidade()
    {
        return $this->hasOne(Unidade::class, 'id_modulo', 'id');
    }

    public function comandosPendentes()
    {
        return $this->hasMany(ComandoPendente::class, 'ID_Disp', 'serial');
    }

    public function comandosHistorico()
    {
        return $this->hasMany(ComandoHistorico::class, 'ID_Disp', 'serial');
    }

    public function comandosResposta()
    {
        return $this->hasMany(ComandoResposta::class, 'id', 'serial');
    }

    public function usuarioCadastro()
    {
        return $this->belongsTo(User::class, 'id_cadastro', 'id');
    }

    public function usuarioAlteracao()
    {
        return $this->belongsTo(User::class, 'id_alteracao', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivos($query)
    {
        return $query->where('status', 1);
    }

    public function scopeSituacao($query, $situacao)
    {
        return $query->where('situacao', $situacao);
    }

    public function scopeFabricante($query, $fabricante)
    {
        return $query->where('fabricante', $fabricante);
    }

    public function scopeModelo($query, $modelo)
    {
        return $query->where('modelo', $modelo);
    }

    public function scopeSerial($query, $serial)
    {
        return $query->where('serial', $serial);
    }

    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================

    public function isAtivo(): bool
    {
        return $this->status === true || $this->status === 1;
    }

    public function isDisponivel(): bool
    {
        return $this->isAtivo() && $this->situacao === 1;
    }

    public function hasComandosPendentes(): bool
    {
        return $this->comandosPendentes()
            ->whereNull('data_envio')
            ->exists();
    }

    public function countComandosPendentes(): int
    {
        return $this->comandosPendentes()
            ->whereNull('data_envio')
            ->count();
    }

    public function ultimoComando()
    {
        return $this->comandosPendentes()
            ->whereNotNull('data_envio')
            ->orderBy('data_envio', 'desc')
            ->first();
    }

    public function ultimaResposta()
    {
        return $this->comandosResposta()
            ->orderBy('data_recebimento', 'desc')
            ->first();
    }

    public function isModelo($modelo): bool
    {
        return strtoupper($this->modelo) === strtoupper($modelo);
    }

    public function suportaComandos(): bool
    {
        $modelosSuportados = ['ST215', 'ST300', 'ST310', 'ST340'];
        return in_array(strtoupper($this->modelo), $modelosSuportados);
    }

    // ========================================
    // ATTRIBUTES
    // ========================================

    public function getNomeCompletoAttribute(): string
    {
        return "{$this->fabricante} {$this->modelo} - {$this->serial}";
    }

    public function getSerialFormatadoAttribute(): string
    {
        if (strlen($this->serial) === 10) {
            return substr($this->serial, 0, 3) . '.' .
                   substr($this->serial, 3, 3) . '.' .
                   substr($this->serial, 6, 4);
        }

        return $this->serial;
    }
}
