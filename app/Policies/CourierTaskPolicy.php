<?php

namespace App\Policies;

use App\Models\CourierTask;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourierTaskPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is authorized to operate on this courier task.
     * The user must own the courier record associated with the task, and the courier must be verified.
     */
    public function operate(User $user, CourierTask $task): bool
    {
        $courier = $task->courier;
        if (! $courier || (int) $courier->user_id !== (int) $user->id) {
            return false;
        }

        return (bool) $courier->is_verified;
    }

    public function view(User $user, CourierTask $task): bool
    {
        return $this->operate($user, $task);
    }

    public function update(User $user, CourierTask $task): bool
    {
        return $this->operate($user, $task);
    }
}
