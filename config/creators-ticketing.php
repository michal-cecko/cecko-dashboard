<?php

use App\Filament\Tickets\Actions\NotifyClientNowAction;
use App\Models\Common\User;
use App\Services\Tickets\TicketNumber;
use App\Support\Tickets\StaffUsersOnly;

/*
 * Only our overrides. Laravel merges the package's config/creators-ticketing.php
 * under this file, top-level keys only, so a nested array here replaces the whole
 * package array.
 */
return [

    'allow_guest_tickets' => false,

    'public_page_enabled' => false,

    // Clients get short notices from the app (App\Listeners\Tickets), never ticket text by e-mail.
    'customer_emails_enabled' => false,

    // The plugin route checks access per ticket, so a requester could fetch images from internal notes.
    // Clients use the signed route in the client panel instead.
    'requester_attachment_access' => false,

    'login_redirect' => '/tikety/login',

    'show_stats_widget' => false,

    'navigation_group' => 'Tikety',

    'user_model' => User::class,

    // User::ticketingRole() returns 'admin' for MANAGE_TICKETS.
    'navigation_visibility' => [
        'field' => 'ticketing_role',
        'allowed' => ['admin'],
    ],

    'ticket_assign_scope' => 'department_only',

    'assignee_scope' => StaffUsersOnly::class,

    'ticket_uid_generator' => TicketNumber::class,

    'view_ticket_actions' => [NotifyClientNowAction::class],

    'spam_protection' => [
        'enabled' => false,
    ],

];
