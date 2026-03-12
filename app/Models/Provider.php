<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Attribute
 * @package App\Models
 *
 * @property string $domain
 * @property string $secret_key
 * @property string $logoutUrl
 */
class Provider extends Model
{
    use SoftDeletes;
    protected $table = "providers";

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ["domain", "logoutUrl", "secret_key", "protocol", "url", "name"];

    /**
     * The attributes that should be hiddend for auditing.
     */
    protected $auditExclude = ["secret_key"];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ["secret_key"];

    public function providerUserRoles()
    {
        // Sostituisci ProviderUserRole::class col nome esatto del tuo modello, se diverso
        return $this->hasMany(ProviderUserRole::class, "provider_id", "id");
    }
}
