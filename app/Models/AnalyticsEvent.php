<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One page view, app download or app open. Written by App\Support\Analytics,
 * read by App\Services\AnalyticsReport.
 */
class AnalyticsEvent extends Model
{
    public const PAGE_VIEW = 'page_view';

    public const DOWNLOAD = 'download';

    public const APP_OPEN = 'app_open';

    public const UPDATED_AT = null;

    protected $fillable = [
        'type', 'visitor', 'new_visitor', 'platform', 'source', 'app_version',
        'path', 'referrer', 'device', 'os', 'browser', 'user_id', 'created_at',
    ];

    protected $casts = [
        'new_visitor' => 'boolean',
        'created_at' => 'datetime',
    ];
}
