<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Transaction;
use App\Models\TransactionDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image as ImageProcessor;

class ImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB
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
            // 压缩图片
            $image = ImageProcessor::make($file);
            
            // 保持宽高比，最大宽度1200px
            if ($image->width() > 1200) {
                $image->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }
            
            // 压缩质量
            $compressedImage = $image->encode($file->getClientOriginalExtension(), 80);
            
            // 保存到数据库
            $imageRecord = Image::create([
                'transaction_id' => $request->transaction_id,
                'draft_id' => $request->draft_id,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => strlen($compressedImage),
                'mime_type' => $file->getMimeType(),
                'width' => $image->width(),
                'height' => $image->height(),
                'file_content' => base64_encode($compressedImage),
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
            return response()->json([
                'message' => '图片上传失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Image $image, Request $request)
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

        // 返回图片二进制数据
        $imageData = base64_decode($image->file_content);
        
        return response($imageData)
            ->header('Content-Type', $image->mime_type)
            ->header('Content-Length', strlen($imageData))
            ->header('Content-Disposition', 'inline; filename="' . $image->original_name . '"');
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
            'images' => 'required|array|max:10',
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

        $results = [];

        foreach ($request->images as $index => $base64Image) {
            try {
                // 解析Base64图片
                $imageData = base64_decode($base64Image);
                $image = ImageProcessor::make($imageData);
                
                // 压缩处理
                if ($image->width() > 1200) {
                    $image->resize(1200, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
                
                $compressedImage = $image->encode('jpg', 80);
                
                // 保存到数据库
                $imageRecord = Image::create([
                    'transaction_id' => $request->transaction_id,
                    'draft_id' => $request->draft_id,
                    'original_name' => 'upload_' . time() . '_' . $index . '.jpg',
                    'file_size' => strlen($compressedImage),
                    'mime_type' => 'image/jpeg',
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'file_content' => base64_encode($compressedImage),
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
                $results[] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => '批量上传完成',
            'results' => $results
        ]);
    }
}
