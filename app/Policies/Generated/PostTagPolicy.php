<?php

namespace App\Policies\Generated;

use Illuminate\Auth\Access\HandlesAuthorization;

class PostTagPolicy
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