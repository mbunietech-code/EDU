<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreToolRequest;
use App\Http\Requests\Admin\UpdateToolRequest;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ToolController extends Controller
{
    public function index()
    {
        $tools = Tool::withCount('orders')
            ->latest()
            ->paginate(15);

        return view('admin.tools.index', compact('tools'));
    }

    public function create()
    {
        return view('admin.tools.create');
    }

    public function store(StoreToolRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tools', 'public');
        }

        $fileData = null;
        if ($request->hasFile('tool_file')) {
            $fileData = $this->storeFile($request->file('tool_file'));
        } elseif ($request->filled('tool_file_path')) {
            $fileData = [
                'path' => $request->input('tool_file_path'),
                'filename' => $request->input('tool_filename'),
            ];
        }
        unset($validated['tool_file'], $validated['tool_file_path'], $validated['tool_filename']);

        $tool = Tool::create($validated);

        if ($fileData) {
            $this->saveDownload($tool, $fileData);
        }

        \App\Models\ActivityLog::log(
            'tool_created',
            'Tool',
            $tool->id,
            ['name' => $tool->name]
        );

        return redirect()->route('admin.tools.index')
            ->with('success', 'Tool created.');
    }

    public function edit(Tool $tool)
    {
        return view('admin.tools.edit', compact('tool'));
    }

    public function update(UpdateToolRequest $request, Tool $tool)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('tools', 'public');
        }

        if ($request->boolean('remove_file')) {
            $this->deleteDownload($tool);
        } elseif ($request->hasFile('tool_file')) {
            $this->deleteDownload($tool);
            $this->saveDownload($tool, $this->storeFile($request->file('tool_file')));
        } elseif ($request->filled('tool_file_path') && $request->input('tool_file_path') !== $tool->primaryDownload?->file_path) {
            $this->deleteDownload($tool);
            $this->saveDownload($tool, [
                'path' => $request->input('tool_file_path'),
                'filename' => $request->input('tool_filename'),
            ]);
        }
        unset($validated['tool_file'], $validated['tool_file_path'], $validated['tool_filename'], $validated['remove_file']);

        $tool->update($validated);

        \App\Models\ActivityLog::log(
            'tool_updated',
            'Tool',
            $tool->id,
            ['name' => $tool->name]
        );

        return redirect()->route('admin.tools.index')
            ->with('success', 'Tool updated.');
    }

    public function destroy(Tool $tool)
    {
        $name = $tool->name;
        $id = $tool->id;

        $this->deleteDownload($tool);
        $tool->delete();

        \App\Models\ActivityLog::log(
            'tool_deleted',
            'Tool',
            $id,
            ['name' => $name]
        );

        return back()->with('success', 'Tool deleted.');
    }

    public function uploadFile(Request $request)
    {
        $validated = $request->validate([
            'tool_file' => ['required', 'file', 'mimes:exe,zip,msi,rar,apk', 'max:153600'],
        ]);

        $file = $this->storeFile($request->file('tool_file'));

        return response()->json([
            'path' => $file['path'],
            'filename' => $file['filename'],
        ]);
    }

    protected function storeFile($file): array
    {
        return [
            'path' => $file->store('tools', config('software.download_disk', 'private')),
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ];
    }

    protected function saveDownload(Tool $tool, array $fileData): void
    {
        $tool->downloads()->create([
            'file_path' => $fileData['path'],
            'file_filename' => $fileData['filename'],
            'file_type' => strtolower(pathinfo($fileData['filename'], PATHINFO_EXTENSION)) ?: 'bin',
            'file_size' => $fileData['size'] ?? null,
            'sort_order' => 0,
        ]);
    }

    protected function deleteDownload(Tool $tool): void
    {
        $download = $tool->primaryDownload;

        if (! $download) {
            return;
        }

        Storage::disk(config('software.download_disk', 'private'))->delete($download->file_path);
        $download->delete();
    }
}
