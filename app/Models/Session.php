<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Session extends Model
{
    use HasFactory;

    protected $table = "sessions";
    protected $fillable = [
        "id",
        "user_id",
        "provider_id",
        "ip_address",
        "token",
        "refresh_token",
        "expires_at",
        "last_activity",
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    protected function casts(): array
    {
        return [
            "expires_at" => "datetime",
            "last_activity" => "datetime",
        ];
    }
}
