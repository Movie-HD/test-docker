<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappInstance extends Model
{
    protected $fillable = [
        'phone_number',
        'instance_name',
        'qr_code',
        'status',
        'organizacion_id',
    ];

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class);
    }

    public function templates() {
        return $this->hasMany(WhatsappTemplate::class);
    }

}
