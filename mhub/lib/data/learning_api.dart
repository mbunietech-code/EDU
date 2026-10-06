import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/learning.dart';

/// Learning API: home, courses, lessons (video + progress) and live classes.
class LearningRepository {
  LearningRepository(this._api);
  final ApiClient _api;

  Map<String, dynamic> _data(dynamic body) => (body as Map<String, dynamic>)['data'] as Map<String, dynamic>;

  List<T> _page<T>(dynamic body, T Function(Map<String, dynamic>) f) =>
      ((body as Map<String, dynamic>)['data'] as List).map((e) => f(e as Map<String, dynamic>)).toList();

  Future<LearningHome> home() async => LearningHome.fromJson(await _api.get('/learning') as Map<String, dynamic>);

  Future<List<CourseCard>> courses({String? search, bool mine = false}) async => _page(
        await _api.get('/learning/courses', query: {
          if (search != null && search.isNotEmpty) 'q': search,
          if (mine) 'mine': 1,
        }),
        CourseCard.fromJson,
      );

  Future<CourseDetail> course(String slug) async => CourseDetail.fromJson(_data(await _api.get('/learning/courses/$slug')));

  Future<String> enroll(String slug) async =>
      ((await _api.post('/learning/courses/$slug/enroll')) as Map<String, dynamic>)['message'] as String? ??
      'You are enrolled.';

  Future<List<LessonCard>> lessons({String? search, String? status}) async => _page(
        await _api.get('/learning/videos', query: {
          if (search != null && search.isNotEmpty) 'q': search,
          'status': ?status,
        }),
        LessonCard.fromJson,
      );

  Future<LessonDetail> lesson(String slug) async => LessonDetail.fromJson(_data(await _api.get('/learning/videos/$slug')));

  /// Player beacon. [watched] = seconds actually played since the last one.
  Future<void> reportProgress(String slug,
          {required int position, int? duration, required String event, int watched = 0}) =>
      _api.post('/learning/videos/$slug/progress', data: {
        'position': position,
        'duration': ?duration,
        'event': event,
        'watched': watched.clamp(0, 600),
      });

  Future<void> setCompleted(String slug, bool completed) =>
      _api.post('/learning/videos/$slug/complete', data: {'completed': completed});

  Future<List<RoomCard>> rooms(String tab) async =>
      _page(await _api.get('/learning/rooms', query: {'tab': tab}), RoomCard.fromJson);

  Future<RoomDetail> room(String slug) async => RoomDetail.fromJson(_data(await _api.get('/learning/rooms/$slug')));
}

final learningRepositoryProvider = Provider((ref) => LearningRepository(ref.watch(apiClientProvider)));

final learningHomeProvider =
    FutureProvider.autoDispose((ref) => ref.watch(learningRepositoryProvider).home());

final coursesProvider = FutureProvider.autoDispose
    .family<List<CourseCard>, ({String search, bool mine})>(
        (ref, f) => ref.watch(learningRepositoryProvider).courses(search: f.search, mine: f.mine));

final courseDetailProvider = FutureProvider.autoDispose
    .family<CourseDetail, String>((ref, slug) => ref.watch(learningRepositoryProvider).course(slug));

final lessonsProvider = FutureProvider.autoDispose
    .family<List<LessonCard>, ({String search, String? status})>(
        (ref, f) => ref.watch(learningRepositoryProvider).lessons(search: f.search, status: f.status));

final lessonDetailProvider = FutureProvider.autoDispose
    .family<LessonDetail, String>((ref, slug) => ref.watch(learningRepositoryProvider).lesson(slug));

final roomsProvider = FutureProvider.autoDispose
    .family<List<RoomCard>, String>((ref, tab) => ref.watch(learningRepositoryProvider).rooms(tab));

final roomDetailProvider = FutureProvider.autoDispose
    .family<RoomDetail, String>((ref, slug) => ref.watch(learningRepositoryProvider).room(slug));
