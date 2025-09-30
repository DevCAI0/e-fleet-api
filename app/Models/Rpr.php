<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rpr extends Model
{
    use HasFactory;

    protected $table = 'rpr';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_unidade',
        'id_user',
        'status_t1',
        'status_t2',
        'status_t3',
        'status_t4',
        'status_t5',
        'status_t6',
        'status_t7',
        'status_t8',
        'status_t9',
        'status_t10',
        'status_t11',
        'cor_t1',
        'cor_t2',
        'cor_t3',
        'cor_t4',
        'cor_t5',
        'cor_t6',
        'cor_t7',
        'cor_t9',
        'cor_t10',
        'observacao',
        'data_cadastro'
    ];

    protected $casts = [
        'data_cadastro' => 'datetime',
        'id_unidade' => 'integer',
        'id_user' => 'integer'
    ];

    /**
     * Relacionamento com Unidade
     */
    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade', 'id_unidade');
    }

    /**
     * Relacionamento com Usuário - CORRIGIDO
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Relacionamento com Checklists
     */
    public function checklists()
    {
        return $this->hasMany(ChecklistVeicular::class, 'id_rpr', 'id');
    }

    /**
     * Obter checklist ativo (não finalizado)
     */
    public function checklistAtivo()
    {
        return $this->hasOne(ChecklistVeicular::class, 'id_rpr', 'id')
                    ->where('finalizado', false)
                    ->latest('data_analise');
    }

    /**
     * Verificar se tem algum status ativo
     */
    public function temProblema(): bool
    {
        return $this->status_t1 === 'S' ||
               $this->status_t2 === 'S' ||
               $this->status_t3 === 'S' ||
               $this->status_t4 === 'S' ||
               $this->status_t5 === 'S' ||
               $this->status_t6 === 'S' ||
               $this->status_t9 === 'S' ||
               $this->status_t10 === 'S';
    }

    /**
     * Verificar se está OK
     */
    public function estaOk(): bool
    {
        return $this->status_t7 === 'S';
    }

    /**
     * Obter lista de problemas ativos
     */
    public function getProblemasAtivos(): array
    {
        $problemas = [];

        if ($this->status_t1 === 'S') {
            $problemas[] = ['tipo' => 1, 'descricao' => 'Velocidade irregular', 'correcao' => $this->cor_t1];
        }
        if ($this->status_t2 === 'S') {
            $problemas[] = ['tipo' => 2, 'descricao' => 'RPM irregular', 'correcao' => $this->cor_t2];
        }
        if ($this->status_t3 === 'S') {
            $problemas[] = ['tipo' => 3, 'descricao' => 'Sinal GPS/GPRS irregular', 'correcao' => $this->cor_t3];
        }
        if ($this->status_t4 === 'S') {
            $problemas[] = ['tipo' => 4, 'descricao' => 'ID Motorista irregular', 'correcao' => $this->cor_t4];
        }
        if ($this->status_t5 === 'S') {
            $problemas[] = ['tipo' => 5, 'descricao' => 'Revisão estrutura rastreador', 'correcao' => $this->cor_t5];
        }
        if ($this->status_t6 === 'S') {
            $problemas[] = ['tipo' => 6, 'descricao' => 'Manutenção veículo +5 dias', 'correcao' => $this->cor_t6];
        }
        if ($this->status_t9 === 'S') {
            $problemas[] = ['tipo' => 9, 'descricao' => 'Sem rastreador', 'correcao' => $this->cor_t9];
        }
        if ($this->status_t10 === 'S') {
            $problemas[] = ['tipo' => 10, 'descricao' => 'Agendado para correção', 'correcao' => $this->cor_t10];
        }

        return $problemas;
    }
}
