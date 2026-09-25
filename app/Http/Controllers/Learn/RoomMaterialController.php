<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomMaterial;
use App\Services\Learning\LearningStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Download a teaching material of a room the viewer may see. */
class RoomMaterialController extends Controller
{
    public function __invoke(Request $request, LearningRoom $room, LearningRoomMaterial $material, LearningStorage $storage)
    {
        $this->authorize('view', $room);
        abort_unless((int) $material->learning_room_id === (int) $room->id, 404);

        $extension = $material->extension();
        $name = (Str::slug($material->title) ?: 'material').($extension !== '' ? '.'.$extension : '');

        return $storage->streamResponse($material->disk, $material->path, $material->mime ?: 'application/octet-stream', $name);
    }
}
