<?php

namespace App\Policies\Models/Blog;

use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny($user)
    {
        return true;
    }

    public function view($user, $model)
    {
        return true;
    }

    public function create($user)
    {
        return true;
    }

    public function update($user, $model)
    {
        return true;
    }

    public function delete($user, $model)
    {
        return true;
    }
}