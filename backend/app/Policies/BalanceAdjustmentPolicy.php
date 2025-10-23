<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BalanceAdjustment;

class BalanceAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isFinance();
    }

    public function view(User $user, BalanceAdjustment $balanceAdjustment): bool
    {
        return $user->isAdmin() || $user->isFinance();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, BalanceAdjustment $balanceAdjustment): bool
    {
        return false; // 余额调整记录不允许修改
    }

    public function delete(User $user, BalanceAdjustment $balanceAdjustment): bool
    {
        return false; // 余额调整记录不允许删除
    }
}
