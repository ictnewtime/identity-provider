<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CustomAuditable;

class Role extends Model
{
    use SoftDeletes;
    use HasFactory;
    use CustomAuditable;

    protected $table = "roles";
    protected $fillable = ["name", "provider_id"];

    public $timestamps = false;

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
