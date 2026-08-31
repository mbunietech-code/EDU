<?php

/*
|--------------------------------------------------------------------------
| Admin permissions
|--------------------------------------------------------------------------
|
| The catalogue of fine-grained permissions a "normal admin" can be granted.
|
|  - super_admin  -> implicitly has every permission (Gate::before)
|  - admin        -> only the permission keys stored on users.permissions
|  - user         -> no admin permissions
|
| Each group renders as a section on the Team member permissions form.
| "view" = can open/read the area, "manage" = can create/edit/act.
|
*/

return [

    'groups' => [

        'Catalogue' => [
            'products.view'      => 'View products & plans',
            'products.manage'    => 'Create / edit products & plans',
            'tools.view'         => 'View research tools',
            'tools.manage'       => 'Create / edit research tools',
            'scholarships.view'  => 'View scholarships',
            'scholarships.manage'=> 'Create / edit scholarships',
        ],

        'Sales & access' => [
            'orders.view'         => 'View orders',
            'orders.manage'       => 'Edit / reject / reopen orders',
            'payments.view'       => 'View payments',
            'payments.manage'     => 'Approve / reject payments',
            'payment_methods.manage' => 'Manage payment methods',
            'subscriptions.view'  => 'View subscriptions',
            'subscriptions.manage'=> 'Extend / suspend / revoke subscriptions',
            'accounts.view'       => 'View shared accounts',
            'accounts.manage'     => 'Create / edit / decrypt shared accounts',
        ],

        'People & messages' => [
            'users.view'             => 'View members',
            'users.manage'           => 'Suspend / activate / delete members',
            'chat.view'              => 'Read support messages',
            'chat.manage'            => 'Reply to support messages',
            'contact_messages.view'  => 'View contact messages',
            'contact_messages.manage'=> 'Delete contact messages',
        ],

        'Insights & records' => [
            'reports.view'          => 'View reports',
            'activity_logs.view'    => 'View activity logs',
            'deleted_records.view'  => 'View deleted items',
            'error_logs.view'       => 'View server error logs',
            'error_logs.manage'     => 'Resolve / clear error logs',
        ],

        'Research library' => [
            'research.view'    => 'View all research (incl. drafts & submissions)',
            'research.manage'  => 'Review, approve/reject, publish & manage categories',
        ],

        'System' => [
            'settings.manage'  => 'Change site settings',
            'finance.access'   => 'Open the Finance area (PIN still required)',
        ],

        // Note: "Database" and "Team" are always super-admin only and cannot
        // be granted to a normal admin (see AuthServiceProvider).

    ],

];
