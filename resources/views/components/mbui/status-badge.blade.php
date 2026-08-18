@props(['status'])

@php
    $map = [
        'active' => 'success',
        'available' => 'success',
        'approved' => 'success',
        'paid' => 'success',
        'confirmed' => 'success',
        'published' => 'success',
        'pending' => 'warning',
        'expiring_soon' => 'warning',
        'suspended' => 'warning',
        'inactive' => 'warning',
        'draft' => 'warning',
        'maintenance' => 'warning',
        'expired' => 'danger',
        'rejected' => 'danger',
        'revoked' => 'danger',
        'cancelled' => 'danger',
        'archived' => 'danger',
        'assigned' => 'info',
    ];

    $appearance = $map[strtolower((string) $status)] ?? 'neutral';
@endphp

<x-mbui.badge :appearance="$appearance">{{ ucwords(str_replace('_', ' ', $status)) }}</x-mbui.badge>