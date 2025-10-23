<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceCarryForward extends Model
{
    use HasFactory;

    protected $table = 'balance_carry_forward';

    protected $fillable = [
        'channel_id',
        'date',
        'balance_cny',
    ];

    protected $casts = [
        'date' => 'date',
        'balance_cny' => 'decimal:2',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}


