<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhitelabelSetting extends Model
{
    protected $fillable = [
        'system_name',
        'logo_path',
        'proprietary_slug',
    ];
}

