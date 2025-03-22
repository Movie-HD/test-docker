<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $fillable = [
        'whatsapp_instance_id',
        'nombre',
        'mensaje',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function whatsappInstance() {
        return $this->belongsTo(WhatsappInstance::class);
    }

}
