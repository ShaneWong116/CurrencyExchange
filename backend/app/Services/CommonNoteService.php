<?php

namespace App\Services;

use App\Models\CommonNote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * 常用备注服务类
 * 
 * 提供常用备注的创建、查询和删除功能
 * 
 * @package App\Services
 */
class CommonNoteService
{
    /**
     * 获取用户的常用备注列表
     * 
     * @param int $userId 用户ID
     * @param string $userType 用户类型 ('admin' 或 'field')
     * @return Collection 常用备注集合，按创建时间倒序排列
     */
    public function getUserCommonNotes(int $userId, string $userType): Collection
    {
        try {
            return CommonNote::where('user_id', $userId)
                ->where('user_type', $userType)
                ->latest()
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get user common notes', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * 创建常用备注
     * 
     * @param int $userId 用户ID
     * @param string $userType 用户类型 ('admin' 或 'field')
     * @param string $content 备注内容
     * @return CommonNote 创建的常用备注实例
     * @throws ValidationException 当内容为空或超过500字符时
     */
    public function createCommonNote(int $userId, string $userType, string $content): CommonNote
    {
        // 去除首尾空白字符
        $content = trim($content);
        
        // 验证内容不为空
        if (empty($content)) {
            throw ValidationException::withMessages([
                'content' => ['备注内容不能为空'],
            ]);
        }
        
        // 验证内容长度不超过500字符
        if (mb_strlen($content) > 500) {
            throw ValidationException::withMessages([
                'content' => ['备注内容不能超过500字符'],
            ]);
        }
        
        try {
            $note = CommonNote::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'content' => $content,
            ]);
            
            Log::info('Common note created successfully', [
                'note_id' => $note->id,
                'user_id' => $userId,
                'user_type' => $userType,
            ]);
            
            return $note;
        } catch (\Exception $e) {
            Log::error('Failed to create common note', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * 删除常用备注
     * 
     * @param int $userId 用户ID
     * @param string $userType 用户类型 ('admin' 或 'field')
     * @param int $noteId 备注ID
     * @return bool 删除是否成功
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 当备注不存在或不属于当前用户时
     */
    public function deleteCommonNote(int $userId, string $userType, int $noteId): bool
    {
        try {
            // 查找备注并验证权限
            $note = CommonNote::where('id', $noteId)
                ->where('user_id', $userId)
                ->where('user_type', $userType)
                ->firstOrFail();
            
            $deleted = $note->delete();
            
            if ($deleted) {
                Log::info('Common note deleted successfully', [
                    'note_id' => $noteId,
                    'user_id' => $userId,
                    'user_type' => $userType,
                ]);
            }
            
            return $deleted;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Attempted to delete non-existent or unauthorized common note', [
                'note_id' => $noteId,
                'user_id' => $userId,
                'user_type' => $userType,
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete common note', [
                'note_id' => $noteId,
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
