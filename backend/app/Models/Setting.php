<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_name',
        'key_value',
        'description',
        'type'
    ];

    public static function get($key, $default = null)
    {
        $setting = static::where('key_name', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return match ($setting->type) {
            'number' => (float) $setting->key_value,
            'boolean' => filter_var($setting->key_value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->key_value, true),
            default => $setting->key_value,
        };
    }

    public static function set($key, $value, $description = null, $type = 'string')
    {
        $setting = static::where('key_name', $key)->first();
        
        $processedValue = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
        
        if ($setting) {
            $setting->update([
                'key_value' => $processedValue,
                'description' => $description ?? $setting->description,
                'type' => $type,
            ]);
        } else {
            static::create([
                'key_name' => $key,
                'key_value' => $processedValue,
                'description' => $description,
                'type' => $type,
            ]);
        }
    }
}
