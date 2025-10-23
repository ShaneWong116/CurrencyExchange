<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'user_id',
        'currency',
        'before_amount',
        'adjustment_amount',
        'after_amount',
        'type',
        'reason'
    ];

    protected $casts = [
        'before_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'after_amount' => 'decimal:2',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function isIncrease()
    {
        return $this->adjustment_amount > 0;
    }

    public function isDecrease()
    {
        return $this->adjustment_amount < 0;
    }
}
