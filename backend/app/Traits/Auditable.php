<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * 记录操作审计日志
     *
     * @param string $action 操作类型 (如: 'settlement.created', 'balance.adjusted')
     * @param mixed $model 关联的模型实例
     * @param array $details 操作详情
     * @return void
     */
    protected function audit(string $action, $model = null, array $details = [])
    {
        // 检查审计日志功能是否启用
        if (!config('app.audit_log_enabled', true)) {
            return;
        }

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'user_type' => auth()->user() ? get_class(auth()->user()) : null,
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->id : null,
                'details' => $details,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // 审计日志失败不应影响主业务，记录错误日志
            \Log::error('Failed to create audit log', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 记录批量操作审计日志
     *
     * @param string $action
     * @param int $count 操作数量
     * @param array $details
     * @return void
     */
    protected function auditBatch(string $action, int $count, array $details = [])
    {
        $details['count'] = $count;
        $this->audit($action, null, $details);
    }

    /**
     * 记录敏感操作（如删除、清空数据）
     *
     * @param string $action
     * @param mixed $model
     * @param array $details
     * @return void
     */
    protected function auditSensitive(string $action, $model = null, array $details = [])
    {
        $details['sensitive'] = true;
        $details['timestamp'] = now()->toDateTimeString();
        $this->audit($action, $model, $details);
    }
}
