<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Services\Learning\ChunkedUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Chunked upload endpoints used by learnChunkUploader().
 *
 * Every endpoint answers JSON. ChunkedUploadService throws
 * ValidationException (keys purpose / filename / size / index / chunk /
 * file / upload_token), which Laravel renders as a 422 JSON body for these
 * fetch/XHR requests. Uploads are bound to the user who started them, so a
 * token from someone else is simply "not found".
 */
class UploadController extends Controller
{
    /** Slack on top of the configured chunk size (multipart overhead, rounding). */
    private const CHUNK_SLACK_BYTES = 65536;

    public function __construct(protected ChunkedUploadService $uploads)
    {
    }

    /** Client limits: chunk_bytes + per-purpose max_bytes / extensions / mimes. */
    public function config(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUploadLessons() || $request->user()->canHostRooms(), 403);

        return response()->json($this->uploads->limits());
    }

    /** Start an upload → {token, chunk_bytes}. */
    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'string', 'in:video,rendition,recording'],
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $allowed = $data['purpose'] === 'recording' ? $user->canHostRooms() : $user->canUploadLessons();

        abort_unless($allowed, 403, $data['purpose'] === 'recording'
            ? 'Only room hosts can upload recordings.'
            : 'Only instructors and content managers can upload lessons.');

        $token = $this->uploads->init($user, $data['purpose'], $data['filename'], (int) $data['size']);

        return response()->json([
            'token' => $token,
            'chunk_bytes' => $this->uploads->limits()['chunk_bytes'],
        ], 201);
    }

    /** Append chunk ?index → {received_bytes, next_index}. Retry-safe. */
    public function chunk(Request $request, string $token): JsonResponse
    {
        $maxKb = (int) ceil(($this->uploads->limits()['chunk_bytes'] + self::CHUNK_SLACK_BYTES) / 1024);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:10000000'],
            'chunk' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'chunk.max' => 'The chunk is larger than the server accepts.',
            'chunk.required' => 'The chunk did not arrive. Please retry.',
            'chunk.file' => 'The chunk did not arrive intact. Please retry.',
            'chunk.uploaded' => 'The chunk did not arrive intact. Please retry.',
        ]);

        return response()->json(
            $this->uploads->appendChunk($request->user(), $token, (int) $data['index'], $request->file('chunk'))
        );
    }

    /** Finish → {token, size, mime, original_name}; the content type is verified server-side. */
    public function complete(Request $request, string $token): JsonResponse
    {
        return response()->json($this->uploads->complete($request->user(), $token));
    }

    /** Cancel and delete the temporary data (idempotent). */
    public function abort(Request $request, string $token): Response
    {
        $this->uploads->abort($request->user(), $token);

        return response()->noContent();
    }
}
