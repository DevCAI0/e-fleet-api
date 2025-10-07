<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidade extends Model
{
    use HasFactory;

    protected $table = 'unidades';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'numero_ordem',
        'id_modulo',
        'id_chip',
        'id_empresa',
        'id_limite',
        'id_tipo_unidade',
        'id_modelo',
        'id_modelo_chassi',
        'id_cor',
        'ano_fabricacao',
        'ano_modelo',
        'placa',
        'chassi',
        'renavam',
        'hodometro',
        'categoria_cnh',
        'observacao',
        'id_cadastro',
        'data_cadastro',
        'id_alteracao',
        'data_alteracao',
        'status',
        'id_estabelecimento',
        'id_funcionario',
        'data_server',
        'data_evento',
        'lat',
        'lon',
        'velocidade',
        'direcao',
        'gps_fix',
        'voltagem',
        'ignicao',
        'quilometragem',
        'tipo_alerta',
        'real_time',
        'rpm',
        'ibutton',
        'id_ibutton',
        'endereco_completo',
        'cidade_estado',
        'vel_excedida',
        'pacotes_enviados_cittati',
        'ocorrencias_desativadas',
        'id_alteracao_ocorrencias_desativadas',
        'data_alteracao_ocorrencias_desativadas',
        'id_ponto_parada',
        'id_externo',
        'link_camera_motorista',
        'link_camera_pista',
        'ano_fabricacao_chassi',
        'validade_vistoria',
        'validade_seguro',
        'validade_contrato',
        'ager_mt',
        'capacidade',
        'ativo',
        'sem_servico',
        'data_ultima_movimentacao',
        'data_ultimo_ponto_processado',
        'id_funcionario_app',
        'data_conexao_funcionario_app',
        'data_server_app',
        'data_evento_app',
        'latitude_app',
        'longitude_app',
        'velocidade_app',
        'tipo_evento_app',
        'endereco_completo_app',
        'cidade_estado_app',
        'id_ponto_parada_app',
        'alerta_velocidade_app',
        'id_area_velocidade_app',
        'conflito_funcionarios',
        'sem_servico_app'
    ];

    protected $casts = [
        'id_modulo' => 'integer',
        'id_chip' => 'integer',
        'id_empresa' => 'integer',
        'id_limite' => 'integer',
        'id_tipo_unidade' => 'integer',
        'id_modelo' => 'integer',
        'id_modelo_chassi' => 'integer',
        'id_cor' => 'integer',
        'hodometro' => 'integer',
        'id_cadastro' => 'integer',
        'data_cadastro' => 'datetime',
        'id_alteracao' => 'integer',
        'data_alteracao' => 'datetime',
        'status' => 'boolean',
        'id_estabelecimento' => 'integer',
        'id_funcionario' => 'integer',
        'data_server' => 'datetime',
        'data_evento' => 'datetime',
        'velocidade' => 'integer',
        'gps_fix' => 'boolean',
        'ignicao' => 'boolean',
        'quilometragem' => 'integer',
        'tipo_alerta' => 'integer',
        'real_time' => 'boolean',
        'rpm' => 'integer',
        'id_ibutton' => 'integer',
        'vel_excedida' => 'boolean',
        'pacotes_enviados_cittati' => 'integer',
        'ocorrencias_desativadas' => 'boolean',
        'id_alteracao_ocorrencias_desativadas' => 'integer',
        'data_alteracao_ocorrencias_desativadas' => 'datetime',
        'id_ponto_parada' => 'integer',
        'validade_vistoria' => 'date',
        'validade_seguro' => 'date',
        'validade_contrato' => 'date',
        'ager_mt' => 'boolean',
        'capacidade' => 'integer',
        'ativo' => 'boolean',
        'sem_servico' => 'boolean',
        'data_ultima_movimentacao' => 'datetime',
        'data_ultimo_ponto_processado' => 'datetime',
        'id_funcionario_app' => 'integer',
        'velocidade_app' => 'integer',
        'tipo_evento_app' => 'integer',
        'id_ponto_parada_app' => 'integer',
        'alerta_velocidade_app' => 'integer',
        'id_area_velocidade_app' => 'integer',
        'conflito_funcionarios' => 'boolean',
        'sem_servico_app' => 'boolean'
    ];

    // ========================================
    // RELACIONAMENTOS
    // ========================================

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id');
    }

    public function usuarioCadastro()
    {
        return $this->belongsTo(User::class, 'id_cadastro', 'id');
    }

    public function usuarioAlteracao()
    {
        return $this->belongsTo(User::class, 'id_alteracao', 'id');
    }

    public function comandosPendentes()
    {
        return $this->hasManyThrough(
            ComandoPendente::class,
            Modulo::class,
            'id',
            'ID_Disp',
            'id_modulo',
            'serial'
        );
    }

    public function comandosHistorico()
    {
        return $this->hasManyThrough(
            ComandoHistorico::class,
            Modulo::class,
            'id',
            'ID_Disp',
            'id_modulo',
            'serial'
        );
    }

    public function comandosResposta()
    {
        return $this->hasManyThrough(
            ComandoResposta::class,
            Modulo::class,
            'id',
            'id',
            'id_modulo',
            'serial'
        );
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAtivas($query)
    {
        return $query->where('status', 1);
    }

    public function scopeAtivo($query)
    {
        return $query->where('ativo', 1);
    }

    public function scopeComModuloSerial($query)
    {
        return $query->whereHas('modulo', function($q) {
            $q->whereNotNull('serial')
              ->where('status', 1);
        });
    }

    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function scopeEstabelecimento($query, $idEstabelecimento)
    {
        return $query->where('id_estabelecimento', $idEstabelecimento);
    }

    public function scopeTipoUnidade($query, $idTipo)
    {
        return $query->where('id_tipo_unidade', $idTipo);
    }

    public function scopePlaca($query, $placa)
    {
        return $query->where('placa', 'like', "%{$placa}%");
    }

    public function scopeSemServico($query, $incluirApp = false)
    {
        $query->where('sem_servico', 1);

        if ($incluirApp) {
            $query->orWhere('sem_servico_app', 1);
        }

        return $query;
    }

    public function scopeComServico($query)
    {
        return $query->where('sem_servico', 0)
                    ->where('sem_servico_app', 0);
    }

    public function scopeComGPS($query)
    {
        return $query->whereNotNull('lat')
                    ->whereNotNull('lon')
                    ->where('gps_fix', 1);
    }

    public function scopeEmMovimento($query)
    {
        return $query->where('velocidade', '>', 0);
    }

    public function scopeParadas($query)
    {
        return $query->where('velocidade', 0);
    }

    public function scopeComIgnicao($query)
    {
        return $query->where('ignicao', 1);
    }

    public function scopeVelocidadeExcedida($query)
    {
        return $query->where('vel_excedida', 1);
    }

    public function scopeAtualizadosRecentemente($query, $minutos = 30)
    {
        return $query->where('data_server', '>=', now()->subMinutes($minutos));
    }

    // ========================================
    // MÉTODOS DE VERIFICAÇÃO
    // ========================================

    public function isAtiva(): bool
    {
        return $this->status === true || $this->status === 1;
    }

    public function isAtivo(): bool
    {
        return $this->ativo === true || $this->ativo === 1;
    }

    public function hasModuloAtivo(): bool
    {
        return $this->modulo && $this->modulo->isAtivo();
    }

    public function temServico(): bool
    {
        return !$this->sem_servico && !$this->sem_servico_app;
    }

    public function temGPS(): bool
    {
        return !empty($this->lat) && !empty($this->lon) && $this->gps_fix;
    }

    public function estaEmMovimento(): bool
    {
        return $this->velocidade > 0;
    }

    public function estaParada(): bool
    {
        return $this->velocidade == 0;
    }

    public function temIgnicaoLigada(): bool
    {
        return $this->ignicao === true || $this->ignicao === 1;
    }

    public function temVelocidadeExcedida(): bool
    {
        return $this->vel_excedida === true || $this->vel_excedida === 1;
    }

    public function hasComandosPendentes(): bool
    {
        if (!$this->modulo) {
            return false;
        }

        return $this->comandosPendentes()
            ->whereNull('data_envio')
            ->exists();
    }

    public function suportaComandos(): bool
    {
        return $this->modulo && $this->modulo->suportaComandos();
    }

    public function temConflitoFuncionarios(): bool
    {
        return $this->conflito_funcionarios === true || $this->conflito_funcionarios === 1;
    }

    public function temValidadeVistoriaVencida(): bool
    {
        return $this->validade_vistoria && $this->validade_vistoria < now()->toDateString();
    }

    public function temValidadeSeguroVencida(): bool
    {
        return $this->validade_seguro && $this->validade_seguro < now()->toDateString();
    }

    public function temValidadeContratoVencida(): bool
    {
        return $this->validade_contrato && $this->validade_contrato < now()->toDateString();
    }

    // ========================================
    // MÉTODOS DE CONTAGEM
    // ========================================

    public function countComandosPendentes(): int
    {
        if (!$this->modulo) {
            return 0;
        }

        return $this->comandosPendentes()
            ->whereNull('data_envio')
            ->count();
    }

    // ========================================
    // MÉTODOS DE BUSCA
    // ========================================

    public function ultimoComando()
    {
        if (!$this->modulo) {
            return null;
        }

        return $this->comandosPendentes()
            ->whereNotNull('data_envio')
            ->orderBy('data_envio', 'desc')
            ->first();
    }

    public function ultimaResposta()
    {
        if (!$this->modulo) {
            return null;
        }

        return $this->comandosResposta()
            ->orderBy('data_recebimento', 'desc')
            ->first();
    }

    // ========================================
    // ATTRIBUTES
    // ========================================

    public function getSerialAttribute(): ?string
    {
        return $this->modulo?->serial;
    }

    public function getModeloModuloAttribute(): ?string
    {
        return $this->modulo?->modelo;
    }

    public function getNumeroOrdemFormatadoAttribute(): string
    {
        $numero = str_pad($this->numero_ordem, 3, '0', STR_PAD_LEFT);

        if ($this->empresa && $this->empresa->sigla) {
            return strtoupper($this->empresa->sigla) . ' - ' . $numero;
        }

        return $numero;
    }

    public function getNumeroOrdemSimplesAttribute(): string
    {
        return str_pad($this->numero_ordem, 3, '0', STR_PAD_LEFT);
    }

    public function getPlacaFormatadaAttribute(): string
    {
        if (!$this->placa) {
            return 'S/Placa';
        }

        $placa = preg_replace('/[^A-Z0-9]/', '', strtoupper($this->placa));

        // Formato antigo: ABC1234
        if (strlen($placa) === 7 && preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)) {
            return substr($placa, 0, 3) . '-' . substr($placa, 3);
        }

        // Formato Mercosul: ABC1D23
        if (strlen($placa) === 7 && preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa)) {
            return substr($placa, 0, 3) . substr($placa, 3, 1) .
                   substr($placa, 4, 1) . substr($placa, 5, 2);
        }

        return $this->placa;
    }

    public function getIdentificacaoAttribute(): string
    {
        return $this->numero_ordem_formatado . ' - ' . ($this->placa_formatada ?? 'S/Placa');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->numero_ordem_formatado . ' - ' . $this->placa_formatada .
               ($this->modulo ? ' (Módulo: ' . $this->modulo->modelo . ')' : '');
    }

    public function getUnidadeKeyAttribute(): ?string
    {
        if (!$this->modulo) {
            return null;
        }

        return $this->modulo->modelo . '|' . $this->modulo->serial;
    }

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
            'direcao' => $this->direcao,
            'endereco' => $this->endereco_completo,
            'cidade_estado' => $this->cidade_estado,
            'gps_fix' => $this->gps_fix
        ];
    }

    public function getPosicaoAppAttribute(): ?array
    {
        if (!$this->latitude_app || !$this->longitude_app) {
            return null;
        }

        return [
            'latitude' => (float) $this->latitude_app,
            'longitude' => (float) $this->longitude_app,
            'data_evento' => $this->data_evento_app,
            'velocidade' => $this->velocidade_app,
            'endereco' => $this->endereco_completo_app,
            'cidade_estado' => $this->cidade_estado_app
        ];
    }

    public function getStatusFormatadoAttribute(): string
    {
        return $this->status ? 'Ativa' : 'Inativa';
    }

    public function getStatusCorAttribute(): string
    {
        if (!$this->status || !$this->ativo) {
            return 'danger';
        }

        if ($this->sem_servico || $this->sem_servico_app) {
            return 'warning';
        }

        if ($this->temVelocidadeExcedida()) {
            return 'danger';
        }

        if ($this->estaEmMovimento()) {
            return 'success';
        }

        return 'info';
    }

    public function getTempoUltimaAtualizacaoAttribute(): ?string
    {
        if (!$this->data_server) {
            return 'Nunca';
        }

        $minutos = now()->diffInMinutes($this->data_server);

        if ($minutos < 1) {
            return 'Agora mesmo';
        }

        if ($minutos < 60) {
            return "Há {$minutos} min";
        }

        if ($minutos < 1440) {
            $horas = floor($minutos / 60);
            return "Há {$horas}h";
        }

        $dias = floor($minutos / 1440);
        return "Há {$dias} dia" . ($dias > 1 ? 's' : '');
    }

    public function getHodometroFormatadoAttribute(): string
    {
        if (!$this->hodometro) {
            return '0 km';
        }

        return number_format($this->hodometro, 0, ',', '.') . ' km';
    }

    public function getQuilometragemFormatadaAttribute(): string
    {
        if (!$this->quilometragem) {
            return '0 km';
        }

        return number_format($this->quilometragem, 0, ',', '.') . ' km';
    }

    public function getVoltagemFormatadaAttribute(): string
    {
        if (!$this->voltagem) {
            return '-';
        }

        return number_format((float) $this->voltagem, 2, ',', '.') . ' V';
    }

    public function getDiasValidadeVistoriaAttribute(): ?int
    {
        if (!$this->validade_vistoria) {
            return null;
        }

        return now()->diffInDays($this->validade_vistoria, false);
    }

    public function getDiasValidadeSeguroAttribute(): ?int
    {
        if (!$this->validade_seguro) {
            return null;
        }

        return now()->diffInDays($this->validade_seguro, false);
    }

    public function getDiasValidadeContratoAttribute(): ?int
    {
        if (!$this->validade_contrato) {
            return null;
        }

        return now()->diffInDays($this->validade_contrato, false);
    }
}
