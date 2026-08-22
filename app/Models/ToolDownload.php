<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolDownload extends Model
{
    protected $fillable = [
        'tool_id',
        'file_path',
        'file_filename',
        'file_type',
        'file_size',
        'sort_order',
    ];

    public function tool()
    {
        return $this->belongsTo(Tool::class);
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }
}
