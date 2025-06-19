<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StatusPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $authUser, Status $status): bool
    {
        $owner = $status->user;

        //it's your own status
        if ($authUser->id === $owner->id) {
            return true;
        }

        $isFollower = $owner->followers()->where('follower_id', $authUser->id)->exists();

        //owner is public
        if (!$owner->is_private) {
            //if status is public, allow
            if ($status->privacy === 'public') {
                return true;
            }
            //status is private → only allow if viewer is a follower
            return $isFollower;
        }

        //owner is private → only allow if viewer is a follower
        return $isFollower;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Status $status): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Status $status): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Status $status): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Status $status): bool
    {
        return false;
    }
}
