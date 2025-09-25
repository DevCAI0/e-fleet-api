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

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id_user';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nome',
        'regiao_user',
        'setor_user',
        'setor',
        'login',
        'email',
        'senha',
        'nivel',
        'ativo',
        'permissoes',
        'permissoes_motorista',
        'mostra_papeleta',
        'mostra_ajuste_papeleta',
        'permissoes_linhas',
        'permissoes_gestao',
        'senha_escala',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'senha',
        'senha_escala',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'regiao_user' => 'integer',
        'setor_user' => 'integer',
        'mostra_papeleta' => 'boolean',
        'mostra_ajuste_papeleta' => 'boolean',
        'data_cadastro' => 'datetime',
        'permissoes' => 'array',
        'permissoes_motorista' => 'array',
        'permissoes_linhas' => 'array',
        'permissoes_gestao' => 'array',
    ];

    /**
     * Get the password for the user (Laravel Auth compatibility).
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->senha,
            set: fn (string $value) => $this->senha = bcrypt($value),
        );
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName()
    {
        return 'id_user';
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->senha;
    }

    /**
     * Scope para filtrar usuários ativos
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', 'S');
    }

    /**
     * Scope para filtrar por nível
     */
    public function scopeNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    /**
     * Scope para filtrar por região
     */
    public function scopeRegiao($query, $regiao)
    {
        return $query->where('regiao_user', $regiao);
    }

    /**
     * Scope para filtrar por setor
     */
    public function scopeSetor($query, $setor)
    {
        return $query->where('setor_user', $setor);
    }

    /**
     * Verifica se o usuário está ativo
     */
    public function isAtivo(): bool
    {
        return $this->ativo === 'S';
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public function hasPermissao(string $permissao): bool
    {
        if (empty($this->permissoes)) {
            return false;
        }

        return in_array($permissao, $this->permissoes);
    }

    /**
     * Verifica se o usuário tem permissão de motorista específica
     */
    public function hasPermissaoMotorista(string $permissao): bool
    {
        if (empty($this->permissoes_motorista)) {
            return false;
        }

        return in_array($permissao, $this->permissoes_motorista);
    }

    /**
     * Verifica se o usuário tem acesso a uma linha específica
     */
    public function hasPermissaoLinha(string $linha): bool
    {
        if (empty($this->permissoes_linhas)) {
            return false;
        }

        return in_array($linha, $this->permissoes_linhas);
    }

    /**
     * Verifica se o usuário tem permissão de gestão específica
     */
    public function hasPermissaoGestao(string $permissao): bool
    {
        if (empty($this->permissoes_gestao)) {
            return false;
        }

        return in_array($permissao, $this->permissoes_gestao);
    }

    /**
     * Accessor para o nome completo
     */
    public function getNomeCompletoAttribute(): string
    {
        return $this->nome ?? '';
    }

    /**
     * Mutator para garantir que o email seja sempre lowercase
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Mutator para garantir que o login seja sempre lowercase
     */
    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = strtolower($value);
    }
}
