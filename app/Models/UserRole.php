<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserRole extends Model {

    use  HasFactory;

    protected $table = 'user_roles';
    public $timestamps = false;
    protected $fillable = [
        'role_id', 'user_id', 'id'
    ];

    /**
     * @return mixed
     */
    public function role(){
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return mixed
     */
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

}
