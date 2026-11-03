<?php

namespace App\Support\Tickets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps client users out of the plugin's agent and assignee pickers. An agent can open
 * internal notes and their files, so a client must never become one.
 */
class StaffUsersOnly
{
    public function __invoke(Builder $query): Builder
    {
        return $query->whereNull('customer_id');
    }
}
