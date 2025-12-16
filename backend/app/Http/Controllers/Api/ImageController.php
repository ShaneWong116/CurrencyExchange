<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Transaction;
use App\Models\TransactionDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    /**
     * 使用 GD 库处理图片（压缩和调整大小）
     */
    private function processImage($imageData, $maxWidth = 1200, $quality = 80)
    {
        // 检查 GD 库是否可用
        if (!function_exists('imagecreatefromstring')) {
            Log::error('GD library not available');
            return ['success' => false, 'error' => 'GD库未安装'];
        }
        
        // 检查图片数据是否为空
        if (empty($imageData)) {
            return ['success' => false, 'error' => '图片数据为空'];
        }
        
        // 尝试获取图片信息
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            Log::error('Invalid image data', [
                'data_length' => strlen($imageData),
                'first_bytes' => bin2hex(substr($imageData, 0, 16))
            ]);
            return ['success' => false, 'error' => '无效的图片格式'];
        }
        
        Log::info('Image info', [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime' => $imageInfo['mime']
        ]);
        
        // 从二进制数据创建图片资源
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            Log::error('imagecreatefromstring failed', [
                'mime' => $imageInfo['mime'],
                'data_length' => strlen($imageData)
            ]);
            return ['success' => false, 'error' => '无法解析图片数据'];
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // 如果图片宽度超过最大值，进行缩放
        if ($originalWidth > $maxWidth) {
            $ratio = $maxWidth / $originalWidth;
            $newWidth = $maxWidth;
            $newHeight = intval($originalHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // 保持透明度（PNG）
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            imagedestroy($image);
            $image = $resized;
            
            $finalWidth = $newWidth;
            $finalHeight = $newHeight;
        } else {
            $finalWidth = $originalWidth;
            $finalHeight = $originalHeight;
        }

        // 输出为 JPEG
        ob_start();
        imagejpeg($image, null, $quality);
        $compressedData = ob_get_clean();
        imagedestroy($image);

        return [
            'success' => true,
            'data' => $compressedData,
            'width' => $finalWidth,
            'height' => $finalHeight,
            'mime_type' => 'image/jpeg'
        ];
    }

    /**
     * 保存图片到文件系统
     */
    private function saveToFileSystem($imageData, $uuid)
    {
        $directory = 'images/' . date('Y/m');
        $filename = $uuid . '.jpg';
        $path = $directory . '/' . $filename;
        
        // 确保目录存在
        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
        }
        
        // 保存文件
        $saved = Storage::disk('local')->put($path, $imageData);
        
        if (!$saved) {
            throw new \Exception('无法保存图片到文件系统: ' . $path);
        }
        
        Log::info('Image saved to filesystem', [
            'path' => $path,
            'size' => strlen($imageData)
        ]);
        
        return $path;
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png|max:5120',
            'transaction_id' => 'nullable|exists:transactions,id',
            'draft_id' => 'nullable|exists:transaction_drafts,id',
        ]);

        // 验证关联记录的权限
        if ($request->transaction_id) {
            $transaction = Transaction::find($request->transaction_id);
            if ($transaction->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        if ($request->draft_id) {
            $draft = TransactionDraft::find($request->draft_id);
            if ($draft->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        $file = $request->file('image');
        
        try {
            $imageData = file_get_contents($file->getRealPath());
            $result = $this->processImage($imageData);
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['error'],
                    'error_code' => 'INVALID_IMAGE'
                ], 400);
            }
            
            // 生成 UUID
            $uuid = Str::uuid()->toString();
            
            // 保存到数据库（使用 Base64 存储，兼容现有数据库结构）
            $imageRecord = Image::create([
                'uuid' => $uuid,
                'transaction_id' => $request->transaction_id,
                'draft_id' => $request->draft_id,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => strlen($result['data']),
                'mime_type' => $result['mime_type'],
                'width' => $result['width'],
                'height' => $result['height'],
                'file_content' => base64_encode($result['data']),
            ]);

            return response()->json([
                'message' => '图片上传成功',
                'image' => [
                    'id' => $imageRecord->id,
                    'uuid' => $imageRecord->uuid,
                    'original_name' => $imageRecord->original_name,
                    'file_size' => $imageRecord->file_size,
                    'file_size_formatted' => $imageRecord->getFileSizeFormatted(),
                    'width' => $imageRecord->width,
                    'height' => $imageRecord->height,
                    'url' => $imageRecord->getFileUrl(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => '图片上传失败，请稍后重试',
                'error_code' => 'IMAGE_UPLOAD_FAILED'
            ], 500);
        }
    }

    public function show(Image $image, Request $request)
    {
        // 验证权限
        if ($image->transaction_id) {
            $transaction = Transaction::find($image->transaction_id);
            if ($transaction && $transaction->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        if ($image->draft_id) {
            $draft = TransactionDraft::find($image->draft_id);
            if ($draft && $draft->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        return $this->returnImageResponse($image);
    }
    
    /**
     * 公开访问图片（通过 UUID）
     * 图片通过 UUID 访问，不需要认证
     */
    public function showPublic(string $uuid)
    {
        $image = Image::where('uuid', $uuid)->first();
        
        if (!$image) {
            return response()->json(['message' => '图片不存在'], 404);
        }
        
        return $this->returnImageResponse($image);
    }
    
    /**
     * 返回图片响应
     */
    private function returnImageResponse(Image $image)
    {
        // 获取图片数据（兼容文件系统和数据库存储）
        $imageData = $image->getImageData();
        
        if (!$imageData) {
            return response()->json(['message' => '图片不存在'], 404);
        }
        
        return response($imageData)
            ->header('Content-Type', $image->mime_type)
            ->header('Content-Length', strlen($imageData))
            ->header('Content-Disposition', 'inline; filename="' . $image->original_name . '"')
            ->header('Cache-Control', 'public, max-age=86400'); // 缓存1天
    }

    public function destroy(Image $image, Request $request)
    {
        // 验证权限
        if ($image->transaction_id) {
            $transaction = Transaction::find($image->transaction_id);
            if ($transaction->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        if ($image->draft_id) {
            $draft = TransactionDraft::find($image->draft_id);
            if ($draft->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        $image->delete();

        return response()->json([
            'message' => '图片删除成功'
        ]);
    }

    public function batchUpload(Request $request)
    {
        $request->validate([
            'images' => 'required|array|max:10|min:1',
            'images.*' => 'required|string', // Base64图片数据
            'transaction_id' => 'nullable|exists:transactions,id',
            'draft_id' => 'nullable|exists:transaction_drafts,id',
        ]);

        // 验证权限
        if ($request->transaction_id) {
            $transaction = Transaction::find($request->transaction_id);
            if ($transaction->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        if ($request->draft_id) {
            $draft = TransactionDraft::find($request->draft_id);
            if ($draft->user_id !== $request->user()->id) {
                return response()->json(['message' => '无权访问'], 403);
            }
        }

        // 计算总大小（Base64解码后），限制为50MB
        $totalSize = 0;
        foreach ($request->images as $base64Image) {
            $decoded = base64_decode($base64Image, true);
            if ($decoded === false) {
                return response()->json([
                    'message' => '无效的图片数据',
                    'error_code' => 'INVALID_BASE64'
                ], 400);
            }
            $totalSize += strlen($decoded);
        }
        
        if ($totalSize > 50 * 1024 * 1024) { // 50MB
            return response()->json([
                'message' => '批量上传总大小超过限制(50MB)',
                'error_code' => 'BATCH_SIZE_EXCEEDED'
            ], 400);
        }

        $results = [];

        foreach ($request->images as $index => $base64Image) {
            try {
                // 清理 Base64 字符串（移除可能的前缀、空格、换行）
                $cleanBase64 = $base64Image;
                
                // 如果包含 data URI 前缀，移除它
                if (strpos($cleanBase64, 'data:') === 0) {
                    $cleanBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $cleanBase64);
                }
                
                // 移除空格和换行
                $cleanBase64 = preg_replace('/\s+/', '', $cleanBase64);
                
                // 解析Base64图片
                $imageData = base64_decode($cleanBase64, true);
                
                if ($imageData === false) {
                    Log::error('Base64 decode failed', [
                        'index' => $index,
                        'base64_length' => strlen($base64Image),
                        'clean_base64_length' => strlen($cleanBase64),
                        'base64_preview' => substr($base64Image, 0, 100)
                    ]);
                    $results[] = [
                        'status' => 'error',
                        'message' => 'Base64解码失败'
                    ];
                    continue;
                }
                
                Log::info('Processing image', [
                    'index' => $index,
                    'decoded_size' => strlen($imageData),
                    'first_bytes' => bin2hex(substr($imageData, 0, 16))
                ]);
                
                $result = $this->processImage($imageData);
                
                if (!$result['success']) {
                    Log::error('Image processing failed', [
                        'index' => $index,
                        'error' => $result['error'],
                        'decoded_size' => strlen($imageData)
                    ]);
                    $results[] = [
                        'status' => 'error',
                        'message' => $result['error']
                    ];
                    continue;
                }
                
                // 生成 UUID
                $uuid = Str::uuid()->toString();
                
                Log::info('Saving image to database', [
                    'index' => $index,
                    'uuid' => $uuid,
                    'transaction_id' => $request->transaction_id,
                    'draft_id' => $request->draft_id,
                    'file_size' => strlen($result['data']),
                    'width' => $result['width'],
                    'height' => $result['height'],
                ]);
                
                // 保存到数据库（使用 Base64 存储，兼容现有数据库结构）
                $imageRecord = Image::create([
                    'uuid' => $uuid,
                    'transaction_id' => $request->transaction_id,
                    'draft_id' => $request->draft_id,
                    'original_name' => 'upload_' . time() . '_' . $index . '.jpg',
                    'file_size' => strlen($result['data']),
                    'mime_type' => $result['mime_type'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'file_content' => base64_encode($result['data']),
                ]);
                
                Log::info('Image saved successfully', [
                    'index' => $index,
                    'image_id' => $imageRecord->id
                ]);

                $results[] = [
                    'status' => 'success',
                    'image' => [
                        'id' => $imageRecord->id,
                        'uuid' => $imageRecord->uuid,
                        'original_name' => $imageRecord->original_name,
                        'url' => $imageRecord->getFileUrl(),
                    ]
                ];

            } catch (\Exception $e) {
                Log::error('Batch image upload failed', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                $results[] = [
                    'status' => 'error',
                    'message' => '图片处理失败: ' . $e->getMessage(),
                    'debug' => [
                        'error' => $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ]
                ];
            }
        }

        return response()->json([
            'message' => '批量上传完成',
            'results' => $results
        ]);
    }
}
