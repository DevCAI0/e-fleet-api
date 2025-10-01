<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistVeicular extends Model
{
    use HasFactory;

    protected $table = 'checklist_veicular';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_unidade',
        'id_rpr',
        'id_user_analise',
        'data_analise',
        'status_geral',

        // Módulo de Rastreamento
        'modulo_rastreador',
        'modulo_rastreador_obs',
        'sirene',
        'sirene_obs',
        'leitor_ibutton',
        'leitor_ibutton_obs',

        // Acessórios
        'camera',
        'camera_obs',
        'tomada_usb',
        'tomada_usb_obs',
        'wifi',
        'wifi_obs',

        // Sensores
        'sensor_velocidade',
        'sensor_velocidade_obs',
        'sensor_rpm',
        'sensor_rpm_obs',
        'antena_gps',
        'antena_gps_obs',
        'antena_gprs',
        'antena_gprs_obs',

        // Instalação
        'instalacao_eletrica',
        'instalacao_eletrica_obs',
        'fixacao_equipamento',
        'fixacao_equipamento_obs',

        // Conclusão
        'observacoes_gerais',
        'data_prevista_conclusao',
        'finalizado',
        'data_finalizacao',
        'id_user_finalizacao'
    ];

    protected $casts = [
        'data_analise' => 'datetime',
        'data_prevista_conclusao' => 'date',
        'data_finalizacao' => 'datetime',
        'finalizado' => 'boolean',
        'id_unidade' => 'integer',
        'id_rpr' => 'integer',
        'id_user_analise' => 'integer',
        'id_user_finalizacao' => 'integer'
    ];

    /**
     * Relacionamento com Unidade
     */
    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade', 'id_unidade');
    }

    /**
     * Relacionamento com RPR
     */
    public function rpr()
    {
        return $this->belongsTo(Rpr::class, 'id_rpr', 'id');
    }

    /**
     * Relacionamento com Usuário de Análise
     */
    public function usuarioAnalise()
    {
        return $this->belongsTo(User::class, 'id_user_analise', 'id_user');
    }

    /**
     * Relacionamento com Usuário de Finalização
     */
    public function usuarioFinalizacao()
    {
        return $this->belongsTo(User::class, 'id_user_finalizacao', 'id_user');
    }

    /**
     * Scope para checklists pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('finalizado', false);
    }

    /**
     * Scope para checklists finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->where('finalizado', true);
    }

    /**
     * Verificar se está aprovado
     */
    public function isAprovado(): bool
    {
        return $this->status_geral === 'APROVADO';
    }

    /**
     * Obter itens com problema
     */
    public function getItensComProblema(): array
    {
        $problemas = [];

        $itens = [
            'modulo_rastreador' => 'Módulo Rastreador',
            'sirene' => 'Sirene',
            'leitor_ibutton' => 'Leitor iButton',
            'camera' => 'Câmera',
            'tomada_usb' => 'Tomada USB',
            'wifi' => 'WiFi',
            'sensor_velocidade' => 'Sensor Velocidade',
            'sensor_rpm' => 'Sensor RPM',
            'antena_gps' => 'Antena GPS',
            'antena_gprs' => 'Antena GPRS',
            'instalacao_eletrica' => 'Instalação Elétrica',
            'fixacao_equipamento' => 'Fixação Equipamento'
        ];

        foreach ($itens as $campo => $nome) {
            if ($this->$campo === 'PROBLEMA') {
                $problemas[] = [
                    'item' => $nome,
                    'campo' => $campo,
                    'observacao' => $this->{$campo . '_obs'}
                ];
            }
        }

        return $problemas;
    }

    /**
     * Calcular percentual de itens OK
     */
    public function getPercentualOk(): float
    {
        $campos = [
            'modulo_rastreador',
            'sirene',
            'leitor_ibutton',
            'camera',
            'tomada_usb',
            'wifi',
            'sensor_velocidade',
            'sensor_rpm',
            'antena_gps',
            'antena_gprs',
            'instalacao_eletrica',
            'fixacao_equipamento'
        ];

        $total = count($campos);
        $ok = 0;

        foreach ($campos as $campo) {
            if ($this->$campo === 'OK') {
                $ok++;
            }
        }

        return ($ok / $total) * 100;
    }
}
