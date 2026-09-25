<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomMaterial;
use App\Services\Learning\LearningStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Teaching materials the host shares in a live room (slides, worksheets…).
 * Same file rules as lesson resources (config learning.resource_extensions /
 * max_resource_mb); files live on the private learning disk.
 */
class RoomMaterialController extends Controller
{
    public function __construct(protected LearningStorage $storage)
    {
    }

    public function store(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.(max(1, (int) config('learning.max_resource_mb', 50)) * 1024)],
        ]);

        try {
            $stored = $this->storage->storeResourceFile($request->file('file'));
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $title = trim((string) $request->input('title'));
        $material = $room->materials()->create($stored + [
            'title' => Str::limit($title !== '' ? $title : pathinfo($stored['original_name'], PATHINFO_FILENAME), 255, ''),
            'uploaded_by' => $request->user()->id,
        ]);

        ActivityLog::log('learning_room_material_added', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'material_id' => $material->id,
        ]);

        return $request->expectsJson()
            ? response()->json(['material' => self::payload($material)], 201)
            : back()->with('success', '“'.$material->title.'” is now available in the class.');
    }

    public function destroy(Request $request, LearningRoom $room, LearningRoomMaterial $material): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);
        abort_unless((int) $material->learning_room_id === (int) $room->id, 404);

        $this->storage->deleteFile($material->disk, $material->path);
        $material->delete();

        ActivityLog::log('learning_room_material_deleted', 'LearningRoom', $room->id, [
            'title' => $room->title,
            'material_id' => $material->id,
        ]);

        return $request->expectsJson()
            ? response()->json(['deleted' => true, 'id' => $material->id])
            : back()->with('success', '“'.$material->title.'” was removed.');
    }

    /** @return array{id:int,title:string,name:string,size:int,url:string,created_at:?string} */
    public static function payload(LearningRoomMaterial $material): array
    {
        return [
            'id' => $material->id,
            'title' => $material->title,
            'name' => $material->original_name,
            'size' => (int) $material->size_bytes,
            'url' => route('learn.rooms.materials.download', ['room' => $material->room, 'material' => $material->id]),
            'created_at' => $material->created_at?->toIso8601String(),
        ];
    }
}
