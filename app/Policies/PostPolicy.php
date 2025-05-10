<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $asker , User $owner): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $authUser, Post $post): bool
    {
        // It's your own post
        if ($authUser->id === $post->user_id) {
            return true;
        }

        $owner = $post->user;

        // You are blocked by the user or you blocked them (optional, add this check if you want)
        if ($authUser->isBlockedBy($owner) || $authUser->hasBlocked($owner)) {
            return false;
        }

        // You follow them
        if ($authUser->isFollowing($owner)) {
            return true;
        }

        // Not following, check if the account is public and the post is public
        if (!$owner->is_private && $post->privacy === 'public') {
            return true;
        }

        // Private user and not following
        return false;
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
    public function update(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }
}
