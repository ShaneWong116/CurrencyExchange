<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ChannelBalance;

class ChannelBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isFinance();
    }

    public function view(User $user, ChannelBalance $channelBalance): bool
    {
        return $user->isAdmin() || $user->isFinance();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ChannelBalance $channelBalance): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ChannelBalance $channelBalance): bool
    {
        return $user->isAdmin();
    }
}
