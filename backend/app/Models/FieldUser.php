<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class FieldUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'name',
        'location_id',
        'status',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function drafts()
    {
        return $this->hasMany(TransactionDraft::class, 'user_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
