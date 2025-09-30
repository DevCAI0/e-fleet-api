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

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    public $timestamps = false;

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

    protected $hidden = [
        'senha',
        'senha_escala',
        'remember_token',
    ];

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

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->senha,
            set: fn (string $value) => $this->senha = bcrypt($value),
        );
    }

    public function getAuthIdentifierName()
    {
        return 'id_user';
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
        return $query->where('ativo', 'S');
    }

    public function scopeNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    public function scopeRegiao($query, $regiao)
    {
        return $query->where('regiao_user', $regiao);
    }

    public function scopeSetor($query, $setor)
    {
        return $query->where('setor_user', $setor);
    }

    public function isAtivo(): bool
    {
        return $this->ativo === 'S';
    }

    public function hasPermissao(string $permissao): bool
    {
        if (empty($this->permissoes)) {
            return false;
        }
        return in_array($permissao, $this->permissoes);
    }

    public function hasPermissaoMotorista(string $permissao): bool
    {
        if (empty($this->permissoes_motorista)) {
            return false;
        }
        return in_array($permissao, $this->permissoes_motorista);
    }

    public function hasPermissaoLinha(string $linha): bool
    {
        if (empty($this->permissoes_linhas)) {
            return false;
        }
        return in_array($linha, $this->permissoes_linhas);
    }

    public function hasPermissaoGestao(string $permissao): bool
    {
        if (empty($this->permissoes_gestao)) {
            return false;
        }
        return in_array($permissao, $this->permissoes_gestao);
    }

    public function getNomeCompletoAttribute(): string
    {
        return $this->nome ?? '';
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = strtolower($value);
    }
}
