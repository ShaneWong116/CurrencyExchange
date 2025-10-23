<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'transaction_label',
        'rmb_amount',
        'hkd_amount',
        'exchange_rate',
        'instant_rate',
        'channel_id',
        'location_id',
        'location',
        'notes',
        'status',
        'submit_time'
    ];

    protected $casts = [
        'rmb_amount' => 'decimal:2',
        'hkd_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:5',
        'instant_rate' => 'decimal:5',
        'submit_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(FieldUser::class, 'user_id');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function isIncome()
    {
        return $this->type === 'income';
    }

    public function isOutcome()
    {
        return $this->type === 'outcome';
    }

    public function isExchange()
    {
        return $this->type === 'exchange';
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
