<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nome',
        'login',
        'senha',
        'email',
        'observacao',
        'visao_unidade',
        'todos_unidade',
        'visao_motorista',
        'todos_motorista',
        'visao_setor_almoxarifado',
        'todos_setor_almoxarifado',
        'visao_setor_financeiro',
        'todos_setor_financeiro',
        'visao_dashboard',
        'todos_dashboard',
        'permissao_app_conferencia',
        'id_empresa',
        'id_grupo_permissoes',
        'id_setor',
        'ultimo_acesso',
        'ip_acesso',
        'id_cadastro',
        'data_cadastro',
        'id_alteracao',
        'data_alteracao',
        'status',
        'permissao_app_rastreamento',
        'permissao_app_passagem',
        'permissao_app_vigilante',
        'telefone',
        'celular',
        'data_nascimento',
        'rg',
        'cpf',
        'bloqueado',
        'id_perfil_comercial',
        'ultimo_login',
        'acessa_comercial',
        'acessa_control',
        'acessa_agencias',
        'tipo_usuario',
        'alterou_senha',
        'permissao_empresa',
        'id_estabelecimento',
        'id_permissao_requisicao'
    ];

    protected $hidden = [
        'senha',
        'remember_token',
    ];

    protected $casts = [
        'id_empresa' => 'integer',
        'id_grupo_permissoes' => 'integer',
        'id_setor' => 'integer',
        'id_cadastro' => 'integer',
        'id_alteracao' => 'integer',
        'id_perfil_comercial' => 'integer',
        'id_estabelecimento' => 'integer',
        'id_permissao_requisicao' => 'integer',
        'tipo_usuario' => 'integer',
        'acessa_comercial' => 'integer',
        'acessa_control' => 'integer',
        'acessa_agencias' => 'integer',
        'alterou_senha' => 'integer',
        'todos_unidade' => 'boolean',
        'todos_motorista' => 'boolean',
        'todos_setor_almoxarifado' => 'boolean',
        'todos_setor_financeiro' => 'boolean',
        'todos_dashboard' => 'boolean',
        'permissao_app_conferencia' => 'boolean',
        'permissao_app_rastreamento' => 'boolean',
        'permissao_app_passagem' => 'integer',
        'permissao_app_vigilante' => 'integer',
        'bloqueado' => 'boolean',
        'ultimo_acesso' => 'datetime',
        'data_cadastro' => 'datetime',
        'data_alteracao' => 'datetime',
        'ultimo_login' => 'datetime',
        'data_nascimento' => 'date',
        'visao_unidade' => 'array',
        'visao_motorista' => 'array',
        'visao_setor_almoxarifado' => 'array',
        'visao_setor_financeiro' => 'array',
        'visao_dashboard' => 'array',
        'permissao_empresa' => 'array',
    ];

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->senha,
            set: fn (string $value) => bcrypt($value),
        );
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthPassword()
    {
        return $this->senha;
    }

    // Accessor para compatibilidade com 'name'
    public function getNameAttribute()
    {
        return $this->nome;
    }

    public function scopeAtivo($query)
    {
        return $query->where('status', 1)->where('bloqueado', 0);
    }

    public function scopeTipoUsuario($query, $tipo)
    {
        return $query->where('tipo_usuario', $tipo);
    }

    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function isAtivo(): bool
    {
        return $this->status == 1 && !$this->bloqueado;
    }

    public function isBloqueado(): bool
    {
        return $this->bloqueado == 1;
    }

    public function hasPermissaoApp(string $app): bool
    {
        return match($app) {
            'conferencia' => $this->permissao_app_conferencia == 1,
            'rastreamento' => $this->permissao_app_rastreamento == 1,
            'passagem' => $this->permissao_app_passagem == 1,
            'vigilante' => $this->permissao_app_vigilante == 1,
            default => false
        };
    }

    public function hasAcessoSistema(string $sistema): bool
    {
        return match($sistema) {
            'comercial' => $this->acessa_comercial == 1,
            'control' => $this->acessa_control == 1,
            'agencias' => $this->acessa_agencias == 1,
            default => false
        };
    }

    public function temVisaoCompleta(string $tipo): bool
    {
        return match($tipo) {
            'unidade' => $this->todos_unidade == 1,
            'motorista' => $this->todos_motorista == 1,
            'almoxarifado' => $this->todos_setor_almoxarifado == 1,
            'financeiro' => $this->todos_setor_financeiro == 1,
            'dashboard' => $this->todos_dashboard == 1,
            default => false
        };
    }

    public function getVisao(string $tipo): array
    {
        return match($tipo) {
            'unidade' => $this->visao_unidade ?? [],
            'motorista' => $this->visao_motorista ?? [],
            'almoxarifado' => $this->visao_setor_almoxarifado ?? [],
            'financeiro' => $this->visao_setor_financeiro ?? [],
            'dashboard' => $this->visao_dashboard ?? [],
            default => []
        };
    }

    public function getNomeCompletoAttribute(): string
    {
        return $this->nome ?? '';
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $value ? strtolower($value) : null;
    }

    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = strtolower($value);
    }
}
