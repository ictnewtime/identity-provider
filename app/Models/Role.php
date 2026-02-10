<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $table = "roles";
    protected $fillable = ["name", "provider_id"];

    public $timestamps = false;
}
