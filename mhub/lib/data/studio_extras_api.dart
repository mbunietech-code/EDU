import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import 'studio_api.dart';

DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v)?.toLocal() : null;

class StudioRecording {
  const StudioRecording({
    required this.id,
    required this.status,
    required this.isShared,
    required this.isPublished,
    this.name,
    this.size,
    this.streamUrl,
    this.createdAt,
  });

  final int id;
  final String status;
  final bool isShared;
  final bool isPublished;
  final String? name;
  final String? size;
  final String? streamUrl;
  final DateTime? createdAt;

  factory StudioRecording.fromJson(Map<String, dynamic> j) => StudioRecording(
        id: (j['id'] as num).toInt(),
        status: j['status'] as String? ?? 'ready',
        isShared: j['is_shared'] == true,
        isPublished: j['is_published'] == true,
        name: j['name'] as String?,
        size: j['size'] as String?,
        streamUrl: j['stream_url'] as String?,
        createdAt: _date(j['created_at']),
      );
}

class StudioMaterial {
  const StudioMaterial(this.id, this.title, this.url, this.size);
  final int id;
  final String title;
  final String? url;
  final int size;
}

class StudioMember {
  const StudioMember(this.id, this.name, this.email);
  final int id;
  final String name;
  final String? email;
}

class StudioSession {
  const StudioSession(this.id, this.startedAt, this.endedAt);
  final int id;
  final DateTime? startedAt;
  final DateTime? endedAt;
}

class StudioRoomExtras {
  const StudioRoomExtras({
    required this.canManage,
    required this.isPrivate,
    required this.recordings,
    required this.materials,
    required this.members,
    required this.sessions,
  });

  final bool canManage;
  final bool isPrivate;
  final List<StudioRecording> recordings;
  final List<StudioMaterial> materials;
  final List<StudioMember> members;
  final List<StudioSession> sessions;

  factory StudioRoomExtras.fromJson(Map<String, dynamic> j) {
    List<Map<String, dynamic>> list(String key) => (j[key] as List? ?? const []).cast<Map<String, dynamic>>();
    return StudioRoomExtras(
      canManage: j['can_manage'] == true,
      isPrivate: j['is_private'] == true,
      recordings: list('recordings').map(StudioRecording.fromJson).toList(),
      materials: [
        for (final m in list('materials'))
          StudioMaterial((m['id'] as num).toInt(), m['title'] as String? ?? '', m['url'] as String?, (m['size'] as num?)?.toInt() ?? 0),
      ],
      members: [
        for (final m in list('members')) StudioMember((m['id'] as num).toInt(), m['name'] as String? ?? '', m['email'] as String?),
      ],
      sessions: [
        for (final s in list('sessions')) StudioSession((s['id'] as num).toInt(), _date(s['started_at']), _date(s['ended_at'])),
      ],
    );
  }
}

class AttendanceRow {
  const AttendanceRow({
    required this.name,
    required this.role,
    required this.seconds,
    required this.joins,
    required this.removed,
    this.email,
    this.firstJoinedAt,
  });

  final String name;
  final String role;
  final int seconds;
  final int joins;
  final bool removed;
  final String? email;
  final DateTime? firstJoinedAt;
}

class AttendanceReport {
  const AttendanceReport({
    required this.sessionId,
    required this.attendees,
    required this.removed,
    required this.averageSeconds,
    required this.peak,
    required this.durationSeconds,
    required this.rows,
    this.startedAt,
  });

  final int sessionId;
  final int attendees;
  final int removed;
  final int averageSeconds;
  final int peak;
  final int durationSeconds;
  final List<AttendanceRow> rows;
  final DateTime? startedAt;

  factory AttendanceReport.fromJson(Map<String, dynamic> j) {
    final session = j['session'] as Map<String, dynamic>;
    final totals = j['totals'] as Map<String, dynamic>;
    int n(Object? v) => (v as num?)?.toInt() ?? 0;
    return AttendanceReport(
      sessionId: n(session['id']),
      startedAt: _date(session['started_at']),
      durationSeconds: n(session['duration_seconds']),
      attendees: n(totals['attendees']),
      removed: n(totals['removed']),
      averageSeconds: n(totals['average_seconds']),
      peak: n(totals['peak']),
      rows: [
        for (final r in (j['rows'] as List? ?? const []).cast<Map<String, dynamic>>())
          AttendanceRow(
            name: r['name'] as String? ?? '',
            email: r['email'] as String?,
            role: r['role'] as String? ?? 'participant',
            seconds: n(r['total_seconds']),
            joins: n(r['join_count']),
            removed: r['removed'] == true,
            firstJoinedAt: _date(r['first_joined_at']),
          ),
      ],
    );
  }
}

class MemberHit {
  const MemberHit(this.id, this.name, this.email);
  final int id;
  final String name;
  final String? email;
}

class StudioExtrasRepository {
  StudioExtrasRepository(this._api, this._studio);
  final ApiClient _api;
  final StudioRepository _studio;

  String _msg(Object? body, String fallback) =>
      (body is Map<String, dynamic> ? body['message'] as String? : null) ?? fallback;

  Future<StudioRoomExtras> extras(int roomId) async => StudioRoomExtras.fromJson(
      ((await _api.get('/studio/rooms/$roomId/extras')) as Map<String, dynamic>)['data'] as Map<String, dynamic>);

  Future<AttendanceReport?> attendance(int roomId, {int? sessionId}) async {
    final data = ((await _api.get('/studio/rooms/$roomId/attendance', query: {'session': ?sessionId}))
        as Map<String, dynamic>)['data'];
    return data is Map<String, dynamic> ? AttendanceReport.fromJson(data) : null;
  }

  Future<String> uploadRecording(int roomId, File file,
      {bool shared = false, int? sessionId, void Function(double)? onProgress}) async {
    final token = await _studio.uploadVideo(file, purpose: 'recording', onProgress: onProgress);
    return _msg(
      await _api.post('/studio/rooms/$roomId/recordings', data: {
        'upload_token': token,
        'is_shared': shared,
        'learning_room_session_id': ?sessionId,
      }),
      'Recording uploaded.',
    );
  }

  Future<String> shareRecording(int roomId, int id, bool shared) async =>
      _msg(await _api.post('/studio/rooms/$roomId/recordings/$id/share', data: {'is_shared': shared}), 'Saved.');

  Future<String> publishRecording(int roomId, int id) async =>
      _msg(await _api.post('/studio/rooms/$roomId/recordings/$id/publish'), 'Draft lesson created.');

  Future<String> deleteRecording(int roomId, int id, String reason) async =>
      _msg(await _api.delete('/studio/rooms/$roomId/recordings/$id', data: {'reason': reason}), 'Recording deleted.');

  Future<String> addMaterial(int roomId, String path, {String? title}) async => _msg(
        await _api.post('/studio/rooms/$roomId/materials',
            data: FormData.fromMap({
              if (title != null && title.isNotEmpty) 'title': title,
              'file': await MultipartFile.fromFile(path),
            })),
        'Material added.',
      );

  Future<String> deleteMaterial(int roomId, int id) async =>
      _msg(await _api.delete('/studio/rooms/$roomId/materials/$id'), 'Material removed.');

  Future<List<MemberHit>> searchUsers(String q) async => [
        for (final u in ((await _api.get('/studio/users/search', query: {'q': q})) as List).cast<Map<String, dynamic>>())
          MemberHit((u['id'] as num).toInt(), u['name'] as String? ?? '', u['email'] as String?),
      ];

  Future<String> invite(int roomId, List<int> userIds) async =>
      _msg(await _api.post('/studio/rooms/$roomId/members', data: {'user_ids': userIds}), 'Invited.');

  Future<String> removeMember(int roomId, int memberId) async =>
      _msg(await _api.delete('/studio/rooms/$roomId/members/$memberId'), 'Invitation removed.');
}

final studioExtrasRepositoryProvider = Provider(
    (ref) => StudioExtrasRepository(ref.watch(apiClientProvider), ref.watch(studioRepositoryProvider)));

final studioRoomExtrasProvider = FutureProvider.autoDispose
    .family<StudioRoomExtras, int>((ref, id) => ref.watch(studioExtrasRepositoryProvider).extras(id));

final studioAttendanceProvider = FutureProvider.autoDispose
    .family<AttendanceReport?, ({int roomId, int? sessionId})>(
        (ref, f) => ref.watch(studioExtrasRepositoryProvider).attendance(f.roomId, sessionId: f.sessionId));
