<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    protected $table = 'ordem_servico';
    public $timestamps = false;

    protected $fillable = [
        'numero_os',
        'status',
        'prioridade',
        'descricao',
        'observacoes',
        'id_tecnico_responsavel',
        'id_user_abertura',
        'id_user_conclusao',
        'data_abertura',
        'data_prevista_conclusao',
        'data_conclusao',
        'data_cancelamento'
    ];

    protected $casts = [
        'data_abertura' => 'datetime',
        'data_prevista_conclusao' => 'date',
        'data_conclusao' => 'datetime',
        'data_cancelamento' => 'datetime'
    ];

    public function veiculos()
    {
        return $this->hasMany(OrdemServicoVeiculo::class, 'id_ordem_servico');
    }

    public function tecnicoResponsavel()
    {
        return $this->belongsTo(User::class, 'id_tecnico_responsavel', 'id');
    }

    public function usuarioAbertura()
    {
        return $this->belongsTo(User::class, 'id_user_abertura', 'id');
    }

    public function usuarioConclusao()
    {
        return $this->belongsTo(User::class, 'id_user_conclusao', 'id');
    }

    public function historico()
    {
        return $this->hasMany(OrdemServicoHistorico::class, 'id_ordem_servico');
    }

    public function scopeAbertas($query)
    {
        return $query->where('status', 'ABERTA');
    }

    public function scopeEmAndamento($query)
    {
        return $query->where('status', 'EM_ANDAMENTO');
    }

    public function getPercentualConclusao(): float
    {
        $total = $this->veiculos->count();
        if ($total === 0) return 0;

        $concluidos = $this->veiculos->where('status_veiculo', 'CONCLUIDO')->count();
        return ($concluidos / $total) * 100;
    }

    public static function gerarNumeroOS(): string
    {
        $ano = date('Y');
        $ultimo = self::where('numero_os', 'LIKE', "OS-{$ano}-%")
            ->orderBy('numero_os', 'desc')
            ->first();

        $numero = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo->numero_os);
            $numero = (int)end($partes) + 1;
        }

        return sprintf('OS-%s-%03d', $ano, $numero);
    }

    public function registrarHistorico(int $idUser, string $acao, ?string $detalhes = null)
    {
        OrdemServicoHistorico::create([
            'id_ordem_servico' => $this->id,
            'id_user' => $idUser,
            'acao' => $acao,
            'detalhes' => $detalhes
        ]);
    }
}
