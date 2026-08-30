<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletedRecord;
use Illuminate\Http\Request;

class DeletedRecordController extends Controller
{
    public function index(Request $request)
    {
        $entity = $request->input('entity');

        $records = DeletedRecord::with('deleter')
            ->when($entity, fn ($q) => $q->where('entity', $entity))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $entities = DeletedRecord::select('entity')->distinct()->orderBy('entity')->pluck('entity');

        return view('admin.deleted-records.index', compact('records', 'entities', 'entity'));
    }

    public function show(DeletedRecord $deletedRecord)
    {
        return view('admin.deleted-records.show', compact('deletedRecord'));
    }
}
