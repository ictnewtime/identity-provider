<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CustomAuditable;

class Parameter extends Model
{
    use SoftDeletes;
    use HasFactory;
    use CustomAuditable;

    protected $table = "patemeters";
    protected $fillable = ["key", "value", "type"];

    public $timestamps = false;
}
