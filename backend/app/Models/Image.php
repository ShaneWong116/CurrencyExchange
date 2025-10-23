<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Image extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'transaction_id',
        'draft_id',
        'original_name',
        'file_size',
        'mime_type',
        'width',
        'height',
        'file_content'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($image) {
            if (empty($image->uuid)) {
                $image->uuid = Str::uuid();
            }
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function draft()
    {
        return $this->belongsTo(TransactionDraft::class, 'draft_id');
    }

    public function getFileUrl()
    {
        return route('api.images.show', $this->uuid);
    }

    public function getFileSizeFormatted()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 获取文件扩展名
     */
    public function getExtension()
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /**
     * 检查是否为图片
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * 获取图片的缩略图数据（Base64）
     */
    public function getThumbnail($maxWidth = 150, $maxHeight = 150)
    {
        if (!$this->isImage() || !$this->file_content) {
            return null;
        }

        try {
            $image = imagecreatefromstring(base64_decode($this->file_content));
            if (!$image) {
                return null;
            }

            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // 计算缩略图尺寸
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $thumbWidth = intval($originalWidth * $ratio);
            $thumbHeight = intval($originalHeight * $ratio);

            // 创建缩略图
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);

            // 输出为base64
            ob_start();
            imagejpeg($thumbnail, null, 80);
            $thumbnailData = ob_get_clean();

            imagedestroy($image);
            imagedestroy($thumbnail);

            return base64_encode($thumbnailData);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 压缩图片
     */
    public static function compressImage($imageData, $quality = 80, $maxWidth = 1920, $maxHeight = 1080)
    {
        try {
            $image = imagecreatefromstring($imageData);
            if (!$image) {
                return $imageData;
            }

            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // 如果图片尺寸已经足够小，直接返回
            if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
                imagedestroy($image);
                return $imageData;
            }

            // 计算新尺寸
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);

            // 创建新图像
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            // 输出压缩图像
            ob_start();
            imagejpeg($newImage, null, $quality);
            $compressedData = ob_get_clean();

            imagedestroy($image);
            imagedestroy($newImage);

            return $compressedData;

        } catch (\Exception $e) {
            return $imageData;
        }
    }
}
