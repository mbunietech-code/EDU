<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance report per session (?session=) and CSV export.
 */
class RoomAttendanceController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, LearningRoom $room): View
    {
        $this->authorize('viewAttendance', $room);

        $sessions = $room->sessions()->limit(100)->get(['id', 'learning_room_id', 'started_at', 'ended_at', 'peak_participants']);
        $session = $this->selectedSession($request, $room, $sessions);

        $rows = null;
        $totals = null;

        if ($session) {
            $base = LearningRoomAttendance::query()->where('learning_room_session_id', $session->id);

            $rows = (clone $base)
                ->with(['user:id,name,email', 'remover:id,name'])
                ->orderByRaw("CASE WHEN role = 'host' THEN 0 ELSE 1 END")
                ->orderBy('first_joined_at')
                ->orderBy('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $count = (clone $base)->count();
            $sum = (int) (clone $base)->sum('total_seconds');

            $totals = [
                'attendees' => $count,
                'participants' => (clone $base)->where('role', 'participant')->count(),
                'removed' => (clone $base)->whereNotNull('removed_at')->count(),
                'total_seconds' => $sum,
                'average_seconds' => $count > 0 ? intdiv($sum, $count) : 0,
                'peak' => (int) $session->peak_participants,
                'duration_seconds' => $session->durationSeconds(),
            ];
        }

        return view('studio.rooms.attendance', [
            'room' => $room->load('host:id,name'),
            'sessions' => $sessions,
            'session' => $session,
            'rows' => $rows,
            'totals' => $totals,
            'showEmail' => (bool) $request->user()->is_admin,
        ]);
    }

    public function export(Request $request, LearningRoom $room): StreamedResponse
    {
        $this->authorize('viewAttendance', $room);

        $session = $this->selectedSession($request, $room, null);
        abort_unless($session, 404);

        $showEmail = (bool) $request->user()->is_admin;
        $date = ($session->started_at ?? now())->copy()->setTimezone((string) config('app.timezone'))->format('Y-m-d');
        $filename = (Str::slug($room->slug ?: $room->title) ?: 'room').'-'.$date.'.csv';

        return response()->streamDownload(function () use ($session, $showEmail) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly

            $header = ['Name'];
            if ($showEmail) {
                $header[] = 'Email';
            }
            array_push($header, 'Role', 'First joined', 'Last seen', 'Left', 'Total time', 'Total seconds', 'Joins', 'Removed at', 'Removed by');
            fputcsv($out, $header, ',', '"', '');

            LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->with(['user:id,name,email', 'remover:id,name'])
                ->orderBy('first_joined_at')
                ->orderBy('id')
                ->chunk(500, function ($chunk) use ($out, $showEmail) {
                    foreach ($chunk as $a) {
                        $row = [$a->user?->name ?? 'Former member'];
                        if ($showEmail) {
                            $row[] = $a->user?->email ?? '';
                        }
                        array_push(
                            $row,
                            $a->role,
                            $this->stamp($a->first_joined_at),
                            $this->stamp($a->last_seen_at),
                            $this->stamp($a->left_at),
                            self::hms((int) $a->total_seconds),
                            (string) (int) $a->total_seconds,
                            (string) (int) $a->join_count,
                            $this->stamp($a->removed_at),
                            $a->remover?->name ?? '',
                        );

                        fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Neutralise spreadsheet formulas (CSV injection): a cell starting with
     * = + - @, a tab or a carriage return gets a leading apostrophe.
     */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public static function hms(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private function stamp(?Carbon $at): string
    {
        return $at ? $at->copy()->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s') : '';
    }

    /** ?session= when it belongs to this room, else the latest session. */
    private function selectedSession(Request $request, LearningRoom $room, $sessions): ?LearningRoomSession
    {
        $id = $request->integer('session');

        if ($id > 0) {
            $found = $sessions
                ? $sessions->firstWhere('id', $id)
                : $room->sessions()->whereKey($id)->first();

            if ($found) {
                return $found;
            }
        }

        return $sessions ? $sessions->first() : $room->sessions()->first();
    }
}
