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
        'id_tecnico_responsavel',
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
        'observacoes_gerais',
        'fotos',
        'data_prevista_conclusao',
        'finalizado',
        'data_finalizacao',
        'id_user_finalizacao'
    ];

    protected $casts = [
        'id_unidade' => 'integer',
        'id_rpr' => 'integer',
        'id_user_analise' => 'integer',
        'id_tecnico_responsavel' => 'integer',
        'id_user_finalizacao' => 'integer',
        'data_analise' => 'datetime',
        'data_prevista_conclusao' => 'date',
        'data_finalizacao' => 'datetime',
        'finalizado' => 'boolean',
        'fotos' => 'array'
    ];

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

    public function tecnicoResponsavel()
    {
        return $this->belongsTo(User::class, 'id_tecnico_responsavel', 'id');
    }

    public function usuarioFinalizacao()
    {
        return $this->belongsTo(User::class, 'id_user_finalizacao', 'id');
    }

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

    public function scopeDoTecnico($query, $idTecnico)
    {
        return $query->where('id_tecnico_responsavel', $idTecnico);
    }

    public function getPercentualConclusao(): float
    {
        $campos = [
            'modulo_rastreador',
            'sirene',
            'leitor_ibutton',
            'camera',
            'tomada_usb',
            'wifi'
        ];

        $total = 0;
        $concluidos = 0;

        foreach ($campos as $campo) {
            if ($this->$campo && $this->$campo !== 'NAO_VERIFICADO') {
                $total++;
                if (in_array($this->$campo, ['OK', 'PROBLEMA', 'NAO_INSTALADO'])) {
                    $concluidos++;
                }
            }
        }

        return $total > 0 ? ($concluidos / $total) * 100 : 0;
    }



    public function getItensComProblema(): array
    {
        $itens = [];
        $campos = [
            'modulo_rastreador' => 'Módulo Rastreador',
            'sirene' => 'Sirene',
            'leitor_ibutton' => 'Leitor iButton',
            'camera' => 'Câmera',
            'tomada_usb' => 'Tomada USB',
            'wifi' => 'WiFi'
        ];

        foreach ($campos as $campo => $nome) {
            $statusCampo = $this->$campo;

            if ($statusCampo && $statusCampo !== 'NAO_VERIFICADO') {
                $itens[] = [
                    'item' => $nome,
                    'observacao' => $this->{$campo . '_obs'},
                    'status' => $statusCampo,
                ];
            }
        }

        return $itens;
    }

    public function pertenceAoTecnico($idTecnico): bool
    {
        return $this->id_tecnico_responsavel == $idTecnico;
    }
}
