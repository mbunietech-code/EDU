import 'dart:io';
import 'dart:math' as math;

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

// Teaching Studio for the app — /studio/* (Api\StudioController) and the
// chunked upload endpoints shared with the web Studio (/studio/uploads/*).

class StudioRoom {
  StudioRoom(Map<String, dynamic> j)
      : id = (j['id'] as num).toInt(),
        slug = j['slug'] as String? ?? '',
        title = j['title'] as String? ?? '',
        status = j['status'] as String? ?? 'draft',
        statusLabel = j['status_label'] as String? ?? '',
        access = j['access'] as String? ?? 'public',
        scheduledAt = j['scheduled_at'] == null ? null : DateTime.tryParse(j['scheduled_at'] as String)?.toLocal(),
        durationMinutes = (j['duration_minutes'] as num?)?.toInt() ?? 60,
        host = j['host'] as String?,
        course = j['course'] as String?,
        canEdit = j['can_edit'] as bool? ?? false,
        canStart = j['can_start'] as bool? ?? false,
        canCancel = j['can_cancel'] as bool? ?? false,
        raw = j;

  final int id;
  final String slug;
  final String title;
  final String status;
  final String statusLabel;
  final String access;
  final DateTime? scheduledAt;
  final int durationMinutes;
  final String? host;
  final String? course;
  final bool canEdit;
  final bool canStart;
  final bool canCancel;

  /// Full payload (the detail endpoint adds form fields).
  final Map<String, dynamic> raw;
}

class StudioVideo {
  StudioVideo(Map<String, dynamic> j)
      : id = (j['id'] as num).toInt(),
        slug = j['slug'] as String? ?? '',
        title = j['title'] as String? ?? '',
        status = j['status'] as String? ?? 'draft',
        visibility = j['visibility'] as String? ?? 'course',
        durationLabel = j['duration_label'] as String?,
        thumbnailUrl = j['thumbnail_url'] as String?,
        hasFile = j['has_file'] as bool? ?? false,
        course = j['course'] as String?,
        category = j['category'] as String?,
        canPublish = j['can_publish'] as bool? ?? false,
        raw = j;

  final int id;
  final String slug;
  final String title;
  final String status;
  final String visibility;
  final String? durationLabel;
  final String? thumbnailUrl;
  final bool hasFile;
  final String? course;
  final String? category;
  final bool canPublish;
  final Map<String, dynamic> raw;
}

class Choice {
  const Choice(this.value, this.label, {this.parent});
  final Object value;
  final String label;

  /// Category id of a course (to filter courses by category).
  final int? parent;
}

class StudioHome {
  StudioHome(Map<String, dynamic> j)
      : canHostRooms = j['can_host_rooms'] as bool? ?? false,
        canUploadLessons = j['can_upload_lessons'] as bool? ?? false,
        rooms = ((j['rooms'] as List?) ?? const []).map((e) => StudioRoom(e as Map<String, dynamic>)).toList(),
        videos = ((j['videos'] as List?) ?? const []).map((e) => StudioVideo(e as Map<String, dynamic>)).toList(),
        categories = _choices((j['options'] as Map)['categories'], 'id', 'name'),
        courses = (((j['options'] as Map)['courses'] as List?) ?? const [])
            .map((e) => Choice((e as Map)['id'] as int, e['title'] as String, parent: (e['learning_category_id'] as num?)?.toInt()))
            .toList(),
        roomAccess = _choices((j['options'] as Map)['room_access'], 'value', 'label'),
        videoVisibility = _choices((j['options'] as Map)['video_visibility'], 'value', 'label'),
        minDuration = (((j['options'] as Map)['duration'] as Map?)?['min'] as num?)?.toInt() ?? 5,
        maxDuration = (((j['options'] as Map)['duration'] as Map?)?['max'] as num?)?.toInt() ?? 1440,
        webUrl = j['web_url'] as String?;

  static List<Choice> _choices(dynamic list, String v, String l) =>
      ((list as List?) ?? const []).map((e) => Choice((e as Map)[v] as Object, e[l] as String)).toList();

  final bool canHostRooms;
  final bool canUploadLessons;
  final List<StudioRoom> rooms;
  final List<StudioVideo> videos;
  final List<Choice> categories;
  final List<Choice> courses;
  final List<Choice> roomAccess;
  final List<Choice> videoVisibility;
  final int minDuration;
  final int maxDuration;
  final String? webUrl;
}

class StudioRepository {
  StudioRepository(this._api);
  final ApiClient _api;

  Map<String, dynamic> _data(dynamic body) => (body as Map<String, dynamic>)['data'] as Map<String, dynamic>;
  String _message(dynamic body, String fallback) => ((body as Map<String, dynamic>)['message'] as String?) ?? fallback;

  Future<StudioHome> home() async => StudioHome(_data(await _api.get('/studio')));

  // Classes
  Future<StudioRoom> room(int id) async => StudioRoom(_data(await _api.get('/studio/rooms/$id')));
  Future<String> saveRoom(int? id, Map<String, dynamic> body) async => _message(
      id == null ? await _api.post('/studio/rooms', data: body) : await _api.put('/studio/rooms/$id', data: body), 'Saved.');
  Future<String> cancelRoom(int id, String? reason) async =>
      _message(await _api.post('/studio/rooms/$id/cancel', data: {'reason': ?reason}), 'Class cancelled.');
  Future<void> startRoom(String slug) => _api.post('/learning/rooms/$slug/start');
  Future<void> endRoom(String slug) => _api.post('/learning/rooms/$slug/end');

  // Lessons
  Future<StudioVideo> video(int id) async => StudioVideo(_data(await _api.get('/studio/videos/$id')));
  Future<String> saveVideo(int? id, Map<String, dynamic> body) async => _message(
      id == null ? await _api.post('/studio/videos', data: body) : await _api.post('/studio/videos/$id', data: body), 'Saved.');
  Future<String> setPublished(int id, bool publish) async =>
      _message(await _api.post('/studio/videos/$id/${publish ? 'publish' : 'unpublish'}'), 'Saved.');

  /// Upload a video file in chunks (resumable size limits come from the
  /// server). Returns the completed upload token to attach to a lesson
  /// (purpose "video") or to a class recording (purpose "recording").
  Future<String> uploadVideo(File file,
      {void Function(double progress)? onProgress, String purpose = 'video'}) async {
    final size = await file.length();
    final name = file.uri.pathSegments.isEmpty ? 'video.mp4' : file.uri.pathSegments.last;
    final init = await _api.post('/studio/uploads', data: {'purpose': purpose, 'filename': name, 'size': size})
        as Map<String, dynamic>;
    final token = init['token'] as String;
    final chunkBytes = math.max(64 * 1024, (init['chunk_bytes'] as num).toInt());

    final raf = await file.open();
    try {
      var index = 0;
      var sent = 0;
      while (sent < size) {
        final bytes = await raf.read(math.min(chunkBytes, size - sent));
        await _api.post('/studio/uploads/$token/chunk',
            data: FormData.fromMap({'index': index, 'chunk': MultipartFile.fromBytes(bytes, filename: 'blob')}));
        sent += bytes.length;
        index++;
        onProgress?.call(sent / size);
      }
      await _api.post('/studio/uploads/$token/complete');
      return token;
    } catch (_) {
      _api.delete('/studio/uploads/$token').catchError((_) => null);
      rethrow;
    } finally {
      await raf.close();
    }
  }
}

final studioRepositoryProvider = Provider((ref) => StudioRepository(ref.watch(apiClientProvider)));
final studioHomeProvider = FutureProvider.autoDispose((ref) => ref.watch(studioRepositoryProvider).home());
