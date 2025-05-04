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
        // Case 1: Profile is public
        if ($owner->privacy === 'public') {
            return true;
        }
    
        // Case 2: Profile is private but user is viewing their own profile
        if ($asker->id === $owner->id) {
            return true;
        }
    
        // Case 3: Profile is private and requesting user follows the profile owner
        return $asker->isFollowing($owner);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Post $post): bool
    {
        // Case 1: Public post
        if ($post->privacy === 'public') {
            return true;
        }

        // Case 2: Private post but user is the owner
        if ($user->id === $post->user_id) {
            return true;
        }

        // Case 3: Private post and user follows the owner
        $postOwner = User::find($post->user_id);
        return $user->isFollowing($postOwner);

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
