<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalMailTemplate extends Model
{
    protected $fillable = ['template_key', 'subject', 'body'];
}
