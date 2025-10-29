<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'rmb_amount',
        'hkd_amount',
        'exchange_rate',
        'instant_rate',
        'channel_id',
        'location_id',
        'location',
        'notes',
        'last_modified'
    ];

    protected $casts = [
        'rmb_amount' => 'decimal:2',
        'hkd_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:5',
        'instant_rate' => 'decimal:5',
        'last_modified' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($draft) {
            if (empty($draft->uuid)) {
                $draft->uuid = Str::uuid();
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

    public function images()
    {
        return $this->hasMany(Image::class, 'draft_id');
    }

    public function convertToTransaction()
    {
        // 如果草稿没有 location_id,自动使用用户的 location_id
        $locationId = $this->location_id ?: $this->user->location_id;
        
        return Transaction::create([
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'transaction_label' => property_exists($this, 'transaction_label') ? $this->transaction_label : null,
            'rmb_amount' => $this->rmb_amount,
            'hkd_amount' => $this->hkd_amount,
            'exchange_rate' => $this->exchange_rate,
            'instant_rate' => $this->instant_rate,
            'channel_id' => $this->channel_id,
            'location_id' => $locationId,
            'location' => $this->location,
            'notes' => $this->notes,
        ]);
    }
}
