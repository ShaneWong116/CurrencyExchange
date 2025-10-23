<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettlementExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'item_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * 关联到结余记录
     */
    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }
}
