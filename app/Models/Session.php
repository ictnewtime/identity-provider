<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\CustomAuditable;

class Session extends Model
{
    use CustomAuditable;
    use HasFactory;

    protected $table = "sessions";

    public $incrementing = false;
    protected $keyType = "string";

    // protected $auditableEvents = ["created", "deleted"];
    // Se lo configuri così, traccia gli update, ma ignora questi due campi specifici:
    protected $auditExclude = ["last_activity", "expires_at"];

    protected $fillable = [
        "id",
        "user_id",
        "provider_id",
        "ip_address",
        "user_agent",
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
