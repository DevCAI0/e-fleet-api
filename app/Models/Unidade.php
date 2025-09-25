<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidade extends Model
{
    use HasFactory;

    protected $table = 'unidades';
    protected $primaryKey = 'id_unidade';
    public $timestamps = false;

    protected $fillable = [
        'id_regiao',
        'id_setor',
        'modulo',
        'serial',
        'frequencia_envio',
        'unidade_nome',
        'n_ordem',
        'sim_numero',
        'sim_operadora',
        'pin',
        'puk',
        'tipo_veiculo',
        'marca_veiculo',
        'modelo_veiculo',
        'ano_fabricacao',
        'ano_modelo',
        'cor',
        'placa',
        'chassis',
        'renavam',
        'hodometro',
        'eixo',
        'combustivel',
        'capacidade_tanque',
        'consumo_medio',
        'vel_maxima',
        'vel_chuva',
        'rpm_azul',
        'rpm_verde',
        'rpm_amarelo',
        'st1_min',
        'st1_max',
        'st2_min',
        'st2_max',
        'st3_min',
        'st3_max',
        'obs',
        'status',
        'vendido',
        'id_ponto',
        'id_motorista',
        'data_server',
        'data_evento',
        'lat',
        'lon',
        'velocidade',
        'direcao',
        'gps_fix',
        'voltagem',
        'ignicao',
        'input_1',
        'input_2',
        'input_3',
        'out_1',
        'out_2',
        'quilometragem',
        'tipo_alerta',
        'real_time',
        'rpm',
        'ibutton',
        'unidade',
        'endereco_completo',
        'cidade_estado',
        'id_garagem',
        'id_pedagio',
        'data_cadastro',
        'id_cadastro',
        'data_atualizacao',
        'id_atualizacao',
        'ultimo_ponto',
        'id_risco',
        'vel_risco',
        'ultima_parada',
        'ibutton_manutencao',
        'id_mot_manutencao',
        'gestao',
        'parada_status',
        'id_unidade_antiga'
    ];

    protected $casts = [
        'id_regiao' => 'integer',
        'id_setor' => 'integer',
        'frequencia_envio' => 'integer',
        'eixo' => 'boolean',
        'vel_maxima' => 'integer',
        'vel_chuva' => 'integer',
        'st1_min' => 'float',
        'st1_max' => 'float',
        'st2_min' => 'float',
        'st2_max' => 'float',
        'st3_min' => 'float',
        'id_ponto' => 'integer',
        'id_motorista' => 'integer',
        'data_server' => 'datetime',
        'data_evento' => 'datetime',
        'velocidade' => 'integer',
        'gps_fix' => 'integer',
        'quilometragem' => 'integer',
        'rpm' => 'integer',
        'id_garagem' => 'integer',
        'data_cadastro' => 'datetime',
        'id_cadastro' => 'integer',
        'data_atualizacao' => 'datetime',
        'id_atualizacao' => 'integer',
        'ultimo_ponto' => 'integer',
        'id_risco' => 'integer',
        'vel_risco' => 'boolean',
        'ultima_parada' => 'datetime',
        'ibutton_manutencao' => 'boolean',
        'id_mot_manutencao' => 'integer',
        'gestao' => 'integer',
        'parada_status' => 'boolean',
        'id_unidade_antiga' => 'integer'
    ];

    /**
     * Scope para unidades ativas
     */
    public function scopeAtivas($query)
    {
        return $query->where('status', 'S');
    }

    /**
     * Scope para unidades com módulo e serial definidos
     */
    public function scopeComModuloSerial($query)
    {
        return $query->whereNotNull('modulo')
                    ->where('modulo', '!=', '')
                    ->whereNotNull('serial')
                    ->where('serial', '!=', '');
    }

    /**
     * Scope para filtrar por região
     */
    public function scopeRegiao($query, $regiao)
    {
        return $query->where('id_regiao', $regiao);
    }

    /**
     * Scope para filtrar por setor
     */
    public function scopeSetor($query, $setor)
    {
        return $query->where('id_setor', $setor);
    }

    /**
     * Verificar se unidade está ativa
     */
    public function isAtiva(): bool
    {
        return $this->status === 'S';
    }

    /**
     * Obter identificação completa da unidade
     */
    public function getIdentificacaoAttribute(): string
    {
        return $this->unidade_nome . ' - ' . ($this->placa ?? 'Sem placa');
    }

    /**
     * Obter chave módulo|serial
     */
    public function getModuloSerialAttribute(): string
    {
        return $this->modulo . '|' . $this->serial;
    }

    /**
     * Relacionamento com comandos pendentes
     */
    public function comandosPendentes()
    {
        return $this->hasMany(ComandoPendente::class, 'ID_Disp', 'serial');
    }

    /**
     * Relacionamento com comando resposta (histórico)
     */
    public function comandosResposta()
    {
        return $this->hasMany(ComandoResposta::class, 'id_unidade', 'serial');
    }

    /**
     * Relacionamento com motorista
     */
    public function motorista()
    {
        return $this->belongsTo(User::class, 'id_motorista', 'id_user');
    }

    /**
     * Accessor para status formatado
     */
    public function getStatusFormatadoAttribute(): string
    {
        return match ($this->status) {
            'S' => 'Ativa',
            'N' => 'Inativa',
            default => 'Indefinido'
        };
    }

    /**
     * Accessor para posição atual
     */
    public function getPosicaoAtualAttribute(): ?array
    {
        if (!$this->lat || !$this->lon) {
            return null;
        }

        return [
            'latitude' => (float) $this->lat,
            'longitude' => (float) $this->lon,
            'data_evento' => $this->data_evento,
            'velocidade' => $this->velocidade,
            'endereco' => $this->endereco_completo
        ];
    }
}
