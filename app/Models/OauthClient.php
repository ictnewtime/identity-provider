<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthClient extends Model
{
    use HasFactory;
    protected $table = 'oauth_clients';

    public function client()
    {
        return $this->hasOne('App\Models\Client', 'oauth_client_id');
    }
}
