<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Provider;
use App\Models\User;
use App\Models\Role;

class ProviderUserRole extends Model
{
    protected $fillable = ["user_id", "provider_id", "role_id"];
    public $timestamps = true;

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
