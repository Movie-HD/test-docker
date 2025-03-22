<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sucursal extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'organizacion_id'
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
