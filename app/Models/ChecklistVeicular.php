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
        'modulo_rastreador',
        'modulo_rastreador_obs',
        'sirene',
        'sirene_obs',
        'leitor_ibutton',
        'leitor_ibutton_obs',
        'camera',
        'camera_obs',
        'tomada_usb',
        'tomada_usb_obs',
        'wifi',
        'wifi_obs',
        'sensor_velocidade',
        'sensor_velocidade_obs',
        'sensor_rpm',
        'sensor_rpm_obs',
        'antena_gps',
        'antena_gps_obs',
        'antena_gprs',
        'antena_gprs_obs',
        'instalacao_eletrica',
        'instalacao_eletrica_obs',
        'fixacao_equipamento',
        'fixacao_equipamento_obs',
        'observacoes_gerais',
        'data_prevista_conclusao',
        'finalizado',
        'data_finalizacao',
        'id_user_finalizacao'
    ];

    protected $casts = [
        'id_unidade' => 'integer',
        'id_rpr' => 'integer',
        'id_user_analise' => 'integer',
        'id_user_finalizacao' => 'integer',
        'data_analise' => 'datetime',
        'data_prevista_conclusao' => 'date',
        'data_finalizacao' => 'datetime',
        'finalizado' => 'boolean'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function unidade()
    {
        return $this->belongsTo(Unidade::class, 'id_unidade', 'id');
    }

    public function rpr()
    {
        return $this->belongsTo(Rpr::class, 'id_rpr', 'id');
    }

    public function usuarioAnalise()
    {
        return $this->belongsTo(User::class, 'id_user_analise', 'id');
    }

    public function usuarioFinalizacao()
    {
        return $this->belongsTo(User::class, 'id_user_finalizacao', 'id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivos($query)
    {
        return $query->where('finalizado', false);
    }

    public function scopeFinalizados($query)
    {
        return $query->where('finalizado', true);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status_geral', $status);
    }

    // ========================================
    // MÉTODOS
    // ========================================

    public function getPercentualOk(): float
    {
        $campos = [
            'modulo_rastreador', 'sirene', 'leitor_ibutton', 'camera',
            'tomada_usb', 'wifi', 'sensor_velocidade', 'sensor_rpm',
            'antena_gps', 'antena_gprs', 'instalacao_eletrica', 'fixacao_equipamento'
        ];

        $total = 0;
        $ok = 0;

        foreach ($campos as $campo) {
            if ($this->$campo && $this->$campo !== 'NAO_VERIFICADO') {
                $total++;
                if ($this->$campo === 'OK') {
                    $ok++;
                }
            }
        }

        return $total > 0 ? ($ok / $total) * 100 : 0;
    }

    public function getItensComProblema(): array
    {
        $campos = [
            'modulo_rastreador' => 'Módulo Rastreador',
            'sirene' => 'Sirene',
            'leitor_ibutton' => 'Leitor iButton',
            'camera' => 'Câmera',
            'tomada_usb' => 'Tomada USB',
            'wifi' => 'WiFi',
            'sensor_velocidade' => 'Sensor de Velocidade',
            'sensor_rpm' => 'Sensor RPM',
            'antena_gps' => 'Antena GPS',
            'antena_gprs' => 'Antena GPRS',
            'instalacao_eletrica' => 'Instalação Elétrica',
            'fixacao_equipamento' => 'Fixação do Equipamento'
        ];

        $itensComProblema = [];

        foreach ($campos as $campo => $nome) {
            if ($this->$campo === 'PROBLEMA') {
                $itensComProblema[] = [
                    'item' => $nome,
                    'observacao' => $this->{$campo . '_obs'}
                ];
            }
        }

        return $itensComProblema;
    }

    public function getTotalItensVerificados(): int
    {
        $campos = [
            'modulo_rastreador', 'sirene', 'leitor_ibutton', 'camera',
            'tomada_usb', 'wifi', 'sensor_velocidade', 'sensor_rpm',
            'antena_gps', 'antena_gprs', 'instalacao_eletrica', 'fixacao_equipamento'
        ];

        $total = 0;

        foreach ($campos as $campo) {
            if ($this->$campo && $this->$campo !== 'NAO_VERIFICADO') {
                $total++;
            }
        }

        return $total;
    }
}
