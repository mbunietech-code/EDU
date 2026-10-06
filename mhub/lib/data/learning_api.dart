import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/learning.dart';

/// Learning API: home, courses, lessons (video + progress) and live classes.
class LearningRepository {
  LearningRepository(this._api);
  final ApiClient _api;

  Map<String, dynamic> _data(dynamic body) =>
      (body as Map<String, dynamic>)['data'] as Map<String, dynamic>;

  List<T> _page<T>(dynamic body, T Function(Map<String, dynamic>) f) =>
      ((body as Map<String, dynamic>)['data'] as List)
          .map((e) => f(e as Map<String, dynamic>))
          .toList();

  Future<LearningHome> home() async => LearningHome.fromJson(
    await _api.get('/learning') as Map<String, dynamic>,
  );

  Future<LearningProgressDashboard> progressDashboard() async =>
      LearningProgressDashboard.fromJson(
        await _api.get('/learning/progress') as Map<String, dynamic>,
      );

  Future<LearningCalendar> calendar(String month) async =>
      LearningCalendar.fromJson(
        await _api.get('/learning/calendar', query: {'month': month})
            as Map<String, dynamic>,
      );

  Future<List<LearningCategoryCard>> categories() async => _page(
    await _api.get('/learning/categories'),
    LearningCategoryCard.fromJson,
  );

  Future<LearningCategoryDetail> category(String slug) async =>
      LearningCategoryDetail.fromJson(
        _data(await _api.get('/learning/categories/$slug')),
      );

  Future<List<InstructorCard>> instructors({String? search}) async => _page(
    await _api.get(
      '/learning/instructors',
      query: {if (search != null && search.isNotEmpty) 'q': search},
    ),
    InstructorCard.fromJson,
  );

  Future<InstructorDetail> instructor(int id) async =>
      InstructorDetail.fromJson(
        _data(await _api.get('/learning/instructors/$id')),
      );

  Future<List<CourseCard>> courses({String? search, bool mine = false}) async =>
      _page(
        await _api.get(
          '/learning/courses',
          query: {
            if (search != null && search.isNotEmpty) 'q': search,
            if (mine) 'mine': 1,
          },
        ),
        CourseCard.fromJson,
      );

  Future<CourseDetail> course(String slug) async =>
      CourseDetail.fromJson(_data(await _api.get('/learning/courses/$slug')));

  Future<String> enroll(String slug) async =>
      ((await _api.post('/learning/courses/$slug/enroll'))
              as Map<String, dynamic>)['message']
          as String? ??
      'You are enrolled.';

  Future<List<LessonCard>> lessons({String? search, String? status}) async =>
      _page(
        await _api.get(
          '/learning/videos',
          query: {
            if (search != null && search.isNotEmpty) 'q': search,
            'status': ?status,
          },
        ),
        LessonCard.fromJson,
      );

  Future<LessonDetail> lesson(String slug) async =>
      LessonDetail.fromJson(_data(await _api.get('/learning/videos/$slug')));

  /// Player beacon. [watched] = seconds actually played since the last one.
  Future<void> reportProgress(
    String slug, {
    required int position,
    int? duration,
    required String event,
    int watched = 0,
  }) => _api.post(
    '/learning/videos/$slug/progress',
    data: {
      'position': position,
      'duration': ?duration,
      'event': event,
      'watched': watched.clamp(0, 600),
    },
  );

  Future<void> setCompleted(String slug, bool completed) => _api.post(
    '/learning/videos/$slug/complete',
    data: {'completed': completed},
  );

  Future<LessonComment> comment(
    String slug,
    String body, {
    int? parentId,
  }) async => LessonComment.fromJson(
    _data(
      await _api.post(
        '/learning/videos/$slug/comments',
        data: {'body': body, 'parent_id': ?parentId},
      ),
    ),
  );

  Future<void> deleteComment(String slug, int commentId) =>
      _api.delete('/learning/videos/$slug/comments/$commentId');

  Future<List<RoomCard>> rooms(String tab) async => _page(
    await _api.get('/learning/rooms', query: {'tab': tab}),
    RoomCard.fromJson,
  );

  Future<RoomDetail> room(String slug) async =>
      RoomDetail.fromJson(_data(await _api.get('/learning/rooms/$slug')));

  /// Server-issued LiveKit connection details for a live class.
  Future<LiveJoinConfig> join(String slug) async => LiveJoinConfig.fromJson(
    ((await _api.post('/learning/rooms/$slug/join'))
            as Map<String, dynamic>)['config']
        as Map<String, dynamic>,
  );

  Future<void> leave(String slug) => _api.post('/learning/rooms/$slug/leave');

  Future<LiveRoomFeed> liveFeed(String slug) async => LiveRoomFeed.fromJson(
    await _api.get('/learning/rooms/$slug/feed') as Map<String, dynamic>,
  );

  Future<LiveRoomMessage> sendLiveMessage(
    String slug, {
    required String type,
    required String body,
  }) async => LiveRoomMessage.fromJson(
    await _api.post(
          '/learning/rooms/$slug/messages',
          data: {'type': type, 'body': body},
        )
        as Map<String, dynamic>,
  );

  Future<void> setHand(String slug, bool raised) =>
      _api.post('/learning/rooms/$slug/hand', data: {'raised': raised});

  Future<List<LivePoll>> votePoll(String slug, int pollId, int option) async {
    final body =
        await _api.post(
              '/learning/rooms/$slug/polls/$pollId/vote',
              data: {'option': option},
            )
            as Map<String, dynamic>;
    return ((body['polls'] as List?) ?? const [])
        .map((e) => LivePoll.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<LivePoll>> createPoll(
    String slug, {
    required String question,
    required List<String> options,
  }) async {
    final body =
        await _api.post(
              '/learning/rooms/$slug/polls',
              data: {'question': question, 'options': options},
            )
            as Map<String, dynamic>;
    return ((body['polls'] as List?) ?? const [])
        .map((e) => LivePoll.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<LivePoll>> closePoll(String slug, int pollId) async {
    final body =
        await _api.post('/learning/rooms/$slug/polls/$pollId/close')
            as Map<String, dynamic>;
    return ((body['polls'] as List?) ?? const [])
        .map((e) => LivePoll.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<LiveBoardFeed> board(String slug) async => LiveBoardFeed.fromJson(
    await _api.get('/learning/rooms/$slug/board') as Map<String, dynamic>,
  );

  Future<void> addBoardStroke(
    String slug, {
    required String uid,
    required String color,
    required int width,
    required List<int> points,
  }) => _api.post(
    '/learning/rooms/$slug/board/strokes',
    data: {'uid': uid, 'c': color, 'w': width, 'p': points},
  );

  Future<void> deleteBoardStroke(String slug, int strokeId) =>
      _api.delete('/learning/rooms/$slug/board/strokes/$strokeId');

  Future<LiveBoardState> updateBoard(
    String slug, {
    bool? active,
    bool? allCanDraw,
    bool? clear,
  }) async => LiveBoardState.fromJson(
    await _api.post(
          '/learning/rooms/$slug/board',
          data: {
            'active': ?active,
            'all_can_draw': ?allCanDraw,
            'clear': ?clear,
          },
        )
        as Map<String, dynamic>,
  );

  Future<void> moderateRoom(String slug, Map<String, dynamic> body) =>
      _api.post('/learning/rooms/$slug/moderate', data: body);

  /// Host controls (participants/{id}/mute, guests/admit-all, breakouts/open …).
  /// Returns the server's message.
  Future<String> hostAction(String slug, String path, [Map<String, dynamic>? body]) async {
    final res = await _api.post('/learning/rooms/$slug/$path', data: body ?? const {});
    return (res is Map<String, dynamic> ? res['message'] as String? : null) ?? 'Done.';
  }
}

final learningRepositoryProvider = Provider(
  (ref) => LearningRepository(ref.watch(apiClientProvider)),
);

final learningHomeProvider = FutureProvider.autoDispose(
  (ref) => ref.watch(learningRepositoryProvider).home(),
);

final learningProgressProvider = FutureProvider.autoDispose(
  (ref) => ref.watch(learningRepositoryProvider).progressDashboard(),
);

final learningCalendarProvider = FutureProvider.autoDispose
    .family<LearningCalendar, String>(
      (ref, month) => ref.watch(learningRepositoryProvider).calendar(month),
    );

final learningCategoriesProvider = FutureProvider.autoDispose(
  (ref) => ref.watch(learningRepositoryProvider).categories(),
);

final learningCategoryProvider = FutureProvider.autoDispose
    .family<LearningCategoryDetail, String>(
      (ref, slug) => ref.watch(learningRepositoryProvider).category(slug),
    );

final instructorsProvider = FutureProvider.autoDispose
    .family<List<InstructorCard>, String>(
      (ref, search) =>
          ref.watch(learningRepositoryProvider).instructors(search: search),
    );

final instructorProvider = FutureProvider.autoDispose
    .family<InstructorDetail, int>(
      (ref, id) => ref.watch(learningRepositoryProvider).instructor(id),
    );

final coursesProvider = FutureProvider.autoDispose
    .family<List<CourseCard>, ({String search, bool mine})>(
      (ref, f) => ref
          .watch(learningRepositoryProvider)
          .courses(search: f.search, mine: f.mine),
    );

final courseDetailProvider = FutureProvider.autoDispose
    .family<CourseDetail, String>(
      (ref, slug) => ref.watch(learningRepositoryProvider).course(slug),
    );

final lessonsProvider = FutureProvider.autoDispose
    .family<List<LessonCard>, ({String search, String? status})>(
      (ref, f) => ref
          .watch(learningRepositoryProvider)
          .lessons(search: f.search, status: f.status),
    );

final lessonDetailProvider = FutureProvider.autoDispose
    .family<LessonDetail, String>(
      (ref, slug) => ref.watch(learningRepositoryProvider).lesson(slug),
    );

final roomsProvider = FutureProvider.autoDispose.family<List<RoomCard>, String>(
  (ref, tab) => ref.watch(learningRepositoryProvider).rooms(tab),
);

final roomDetailProvider = FutureProvider.autoDispose
    .family<RoomDetail, String>(
      (ref, slug) => ref.watch(learningRepositoryProvider).room(slug),
    );
