<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleanupLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'operator',
        'time_range',
        'start_date',
        'end_date',
        'content_types',
        'deleted_records',
        'created_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'content_types' => 'array',
        'deleted_records' => 'array',
        'created_at' => 'datetime',
    ];
}


