<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuarterlyDividend extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'amount' => 'decimal:2',
    ];
}
