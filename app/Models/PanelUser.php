<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\PanelUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelUser extends Model
{
    /** @use HasFactory<PanelUserFactory> */
    use HasFactory;

    protected $table = 'usuarios';

    /** @var list<string> */
    protected $fillable = [
        'username',
        'senha',
        'role',
        'ativo',
        'tentativas',
        'bloqueado_ate',
        'criado_em',
    ];

    /** @var list<string> */
    protected $hidden = [
        'senha',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'role' => Role::class,
        'ativo' => 'boolean',
        'tentativas' => 'integer',
    ];

    public $timestamps = false;
}
