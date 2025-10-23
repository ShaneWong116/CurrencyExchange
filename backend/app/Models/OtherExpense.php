<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_type',
        'period_value',
        'expense_name',
        'amount_hkd',
    ];

    protected $casts = [
        'amount_hkd' => 'decimal:2',
    ];
}


