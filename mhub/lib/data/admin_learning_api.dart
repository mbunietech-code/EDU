import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

int _n(Object? v) => (v as num?)?.toInt() ?? 0;

class LearningAdminOverview {
  const LearningAdminOverview({
    required this.counts,
    required this.canManage,
    required this.canTrash,
    required this.canManageRooms,
    required this.guestLinks,
    required this.levels,
    required this.access,
    required this.instructors,
  });

  final Map<String, int> counts;
  final bool canManage;
  final bool canTrash;
  final bool canManageRooms;
  final bool guestLinks;
  final Map<String, String> levels;
  final Map<String, String> access;
  final List<({int id, String name})> instructors;

  factory LearningAdminOverview.fromJson(Map<String, dynamic> j) => LearningAdminOverview(
        counts: (j['counts'] as Map<String, dynamic>? ?? const {}).map((k, v) => MapEntry(k, _n(v))),
        canManage: j['can_manage'] == true,
        canTrash: j['can_trash'] == true,
        canManageRooms: j['can_manage_rooms'] == true,
        guestLinks: j['guest_links'] == true,
        levels: (j['levels'] as Map<String, dynamic>? ?? const {}).map((k, v) => MapEntry(k, '$v')),
        access: (j['access'] as Map<String, dynamic>? ?? const {}).map((k, v) => MapEntry(k, '$v')),
        instructors: [
          for (final u in (j['instructors'] as List? ?? const []).cast<Map<String, dynamic>>())
            (id: _n(u['id']), name: u['name'] as String? ?? ''),
        ],
      );
}

class AdminLearningCategory {
  const AdminLearningCategory(this.id, this.name, this.description, this.position, this.courses, this.lessons, this.rooms);
  final int id;
  final String name;
  final String? description;
  final int position;
  final int courses;
  final int lessons;
  final int rooms;

  factory AdminLearningCategory.fromJson(Map<String, dynamic> j) => AdminLearningCategory(
        _n(j['id']),
        j['name'] as String? ?? '',
        j['description'] as String?,
        _n(j['position']),
        _n(j['courses_count']),
        _n(j['videos_count']),
        _n(j['rooms_count']),
      );
}

class AdminCourse {
  const AdminCourse({
    required this.id,
    required this.title,
    required this.status,
    required this.lessons,
    required this.enrolments,
    this.category,
    this.instructor,
    this.thumbnailUrl,
  });

  final int id;
  final String title;
  final String status;
  final int lessons;
  final int enrolments;
  final String? category;
  final String? instructor;
  final String? thumbnailUrl;

  factory AdminCourse.fromJson(Map<String, dynamic> j) => AdminCourse(
        id: _n(j['id']),
        title: j['title'] as String? ?? '',
        status: j['status'] as String? ?? 'draft',
        lessons: _n(j['videos_count']),
        enrolments: _n(j['enrolments_count']),
        category: j['category'] as String?,
        instructor: j['instructor'] as String?,
        thumbnailUrl: j['thumbnail_url'] as String?,
      );
}

class AdminEnrolment {
  const AdminEnrolment(this.id, this.name, this.email, this.source, this.enrolledAt);
  final int id;
  final String name;
  final String? email;
  final String? source;
  final DateTime? enrolledAt;
}

class AdminCourseDetail {
  const AdminCourseDetail({
    required this.card,
    required this.categoryId,
    required this.access,
    required this.enrolments,
    this.instructorId,
    this.summary,
    this.description,
    this.level,
  });

  final AdminCourse card;
  final int categoryId;
  final String access;
  final List<AdminEnrolment> enrolments;
  final int? instructorId;
  final String? summary;
  final String? description;
  final String? level;

  factory AdminCourseDetail.fromJson(Map<String, dynamic> j) => AdminCourseDetail(
        card: AdminCourse.fromJson(j),
        categoryId: _n(j['learning_category_id']),
        access: j['access'] as String? ?? 'open',
        instructorId: j['instructor_id'] == null ? null : _n(j['instructor_id']),
        summary: j['summary'] as String?,
        description: j['description'] as String?,
        level: j['level'] as String?,
        enrolments: [
          for (final e in (j['enrolments'] as List? ?? const []).cast<Map<String, dynamic>>())
            AdminEnrolment(
              _n(e['id']),
              e['name'] as String? ?? '',
              e['email'] as String?,
              e['source'] as String?,
              DateTime.tryParse(e['enrolled_at'] as String? ?? '')?.toLocal(),
            ),
        ],
      );
}

class TrashPage {
  const TrashPage(this.type, this.types, this.items, this.retentionDays);
  final String type;
  final Map<String, ({String label, int count})> types;
  final List<({int id, String label, DateTime? deletedAt})> items;
  final int retentionDays;
}

class AdminLearningRepository {
  AdminLearningRepository(this._api);
  final ApiClient _api;

  String _msg(Object? body) => (body is Map<String, dynamic> ? body['message'] as String? : null) ?? 'Saved.';

  Future<LearningAdminOverview> overview() async => LearningAdminOverview.fromJson(
      ((await _api.get('/admin/learning')) as Map<String, dynamic>)['data'] as Map<String, dynamic>);

  Future<String> setGuestLinks(bool enabled) async =>
      _msg(await _api.post('/admin/learning/guest-links', data: {'enabled': enabled}));

  Future<List<AdminLearningCategory>> categories() async =>
      (((await _api.get('/admin/learning/categories')) as Map<String, dynamic>)['data'] as List)
          .map((e) => AdminLearningCategory.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<String> saveCategory({int? id, required String name, String? description, int? position}) async {
    final body = {'name': name, 'description': description, 'position': ?position};
    return _msg(id == null
        ? await _api.post('/admin/learning/categories', data: body)
        : await _api.put('/admin/learning/categories/$id', data: body));
  }

  Future<String> deleteCategory(int id, String reason) async =>
      _msg(await _api.delete('/admin/learning/categories/$id', data: {'reason': reason}));

  Future<List<AdminCourse>> courses({String search = '', String? status}) async =>
      (((await _api.get('/admin/learning/courses', query: {
        if (search.isNotEmpty) 'q': search,
        'status': ?status,
      })) as Map<String, dynamic>)['data'] as List)
          .map((e) => AdminCourse.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<AdminCourseDetail> course(int id) async => AdminCourseDetail.fromJson(
      ((await _api.get('/admin/learning/courses/$id')) as Map<String, dynamic>)['data'] as Map<String, dynamic>);

  /// Create (id null) or update a course. Returns the server message.
  Future<String> saveCourse(int? id, Map<String, dynamic> body) async => _msg(id == null
      ? await _api.post('/admin/learning/courses', data: body)
      : await _api.post('/admin/learning/courses/$id', data: body));

  Future<String> deleteCourse(int id, String reason, {bool withLessons = false}) async =>
      _msg(await _api.delete('/admin/learning/courses/$id', data: {'reason': reason, 'with_videos': withLessons}));

  Future<String> enrol(int courseId, List<int> userIds) async =>
      _msg(await _api.post('/admin/learning/courses/$courseId/enrolments', data: {'user_ids': userIds}));

  Future<String> unenrol(int courseId, int enrolmentId, String reason) async =>
      _msg(await _api.delete('/admin/learning/courses/$courseId/enrolments/$enrolmentId', data: {'reason': reason}));

  Future<TrashPage> trash([String? type]) async {
    final body = await _api.get('/admin/learning/trash', query: {'type': ?type}) as Map<String, dynamic>;
    return TrashPage(
      body['type'] as String? ?? 'course',
      (body['types'] as Map<String, dynamic>? ?? const {}).map((k, v) {
        final m = v as Map<String, dynamic>;
        return MapEntry(k, (label: m['label'] as String? ?? k, count: _n(m['count'])));
      }),
      [
        for (final i in (body['data'] as List? ?? const []).cast<Map<String, dynamic>>())
          (
            id: _n(i['id']),
            label: i['label'] as String? ?? '',
            deletedAt: DateTime.tryParse(i['deleted_at'] as String? ?? '')?.toLocal(),
          ),
      ],
      _n(body['retention_days']),
    );
  }

  Future<String> restore(String type, int id) async => _msg(await _api.post('/admin/learning/trash/$type/$id/restore'));

  Future<String> purge(String type, int id, String reason) async =>
      _msg(await _api.delete('/admin/learning/trash/$type/$id', data: {'reason': reason}));
}

final adminLearningRepositoryProvider = Provider((ref) => AdminLearningRepository(ref.watch(apiClientProvider)));

final adminLearningOverviewProvider =
    FutureProvider.autoDispose((ref) => ref.watch(adminLearningRepositoryProvider).overview());

final adminLearningCategoriesProvider =
    FutureProvider.autoDispose((ref) => ref.watch(adminLearningRepositoryProvider).categories());

final adminCoursesProvider = FutureProvider.autoDispose
    .family<List<AdminCourse>, String>((ref, search) => ref.watch(adminLearningRepositoryProvider).courses(search: search));

final adminCourseProvider = FutureProvider.autoDispose
    .family<AdminCourseDetail, int>((ref, id) => ref.watch(adminLearningRepositoryProvider).course(id));

final adminLearningTrashProvider = FutureProvider.autoDispose
    .family<TrashPage, String?>((ref, type) => ref.watch(adminLearningRepositoryProvider).trash(type));
