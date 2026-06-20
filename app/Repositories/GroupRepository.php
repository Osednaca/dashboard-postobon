<?php

namespace App\Repositories;

use App\Models\Group;
use App\Repositories\Contracts\GroupRepositoryInterface;

class GroupRepository extends BaseRepository implements GroupRepositoryInterface
{
    /**
     * GroupRepository constructor.
     *
     * @param Group $group
     */
    public function __construct(Group $group)
    {
        parent::__construct($group);
    }
}
