// Learning (courses, lessons, live classes) — mirrors Api\LearningController.

int _int(dynamic v) => (v as num?)?.toInt() ?? 0;
String? _str(dynamic v) => v as String?;
DateTime? _date(dynamic v) =>
    v == null ? null : DateTime.tryParse(v as String)?.toLocal();
List<T> _list<T>(dynamic v, T Function(Map<String, dynamic>) f) =>
    ((v as List?) ?? const [])
        .map((e) => f(e as Map<String, dynamic>))
        .toList();

class NamedRef {
  const NamedRef({required this.id, required this.name, this.slug});
  final int id;
  final String name;
  final String? slug;

  static NamedRef? maybe(dynamic j, {String nameKey = 'name'}) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return NamedRef(
      id: _int(m['id']),
      name: (m[nameKey] ?? m['name'] ?? '') as String,
      slug: _str(m['slug']),
    );
  }
}

class LessonProgress {
  const LessonProgress({
    required this.positionSeconds,
    required this.percent,
    required this.completed,
  });
  final int positionSeconds;
  final int percent;
  final bool completed;

  static LessonProgress? maybe(dynamic j) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return LessonProgress(
      positionSeconds: _int(m['position_seconds']),
      percent: _int(m['percent']),
      completed: m['completed'] as bool? ?? false,
    );
  }
}

class LessonCard {
  const LessonCard({
    required this.id,
    required this.slug,
    required this.title,
    this.durationLabel,
    this.thumbnailUrl,
    this.course,
    this.instructor,
    this.progress,
  });

  final int id;
  final String slug;
  final String title;
  final String? durationLabel;
  final String? thumbnailUrl;
  final NamedRef? course;
  final NamedRef? instructor;
  final LessonProgress? progress;

  factory LessonCard.fromJson(Map<String, dynamic> j) => LessonCard(
    id: _int(j['id']),
    slug: j['slug'] as String,
    title: j['title'] as String? ?? '',
    durationLabel: _str(j['duration_label']),
    thumbnailUrl: _str(j['thumbnail_url']),
    course: NamedRef.maybe(j['course'], nameKey: 'title'),
    instructor: NamedRef.maybe(j['instructor']),
    progress: LessonProgress.maybe(j['progress']),
  );
}

class VideoSource {
  const VideoSource({required this.label, required this.url, this.height});
  final String label;
  final String url;
  final int? height;

  factory VideoSource.fromJson(Map<String, dynamic> j) => VideoSource(
    label: j['label'] as String? ?? 'Video',
    url: j['url'] as String,
    height: (j['height'] as num?)?.toInt(),
  );
}

class LessonResource {
  const LessonResource({
    required this.title,
    required this.type,
    this.url,
    this.sizeLabel,
  });
  final String title;
  final String type;
  final String? url;
  final String? sizeLabel;

  factory LessonResource.fromJson(Map<String, dynamic> j) => LessonResource(
    title: j['title'] as String? ?? 'Resource',
    type: j['type'] as String? ?? 'link',
    url: _str(j['url']) ?? _str(j['download_url']),
    sizeLabel: _str(j['size_label']),
  );
}

class LessonDetail {
  const LessonDetail({
    required this.card,
    required this.canPlay,
    required this.sources,
    required this.resumeAt,
    required this.resources,
    this.description,
    this.topic,
    this.webUrl,
  });

  final LessonCard card;
  final bool canPlay;
  final List<VideoSource> sources;
  final int resumeAt;
  final List<LessonResource> resources;
  final String? description;
  final String? topic;
  final String? webUrl;

  factory LessonDetail.fromJson(Map<String, dynamic> j) => LessonDetail(
    card: LessonCard.fromJson(j),
    canPlay: j['can_play'] as bool? ?? false,
    sources: _list(j['sources'], VideoSource.fromJson),
    resumeAt: _int(j['resume_at']),
    resources: _list(j['resources'], LessonResource.fromJson),
    description: _str(j['description']),
    topic: (j['topic'] as Map<String, dynamic>?)?['title'] as String?,
    webUrl: _str(j['web_url']),
  );
}

class CourseProgress {
  const CourseProgress({
    required this.total,
    required this.completed,
    required this.percent,
    required this.started,
  });
  final int total;
  final int completed;
  final int percent;
  final bool started;

  static CourseProgress? maybe(dynamic j) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return CourseProgress(
      total: _int(m['total']),
      completed: _int(m['completed']),
      percent: _int(m['percent']),
      started: m['started'] as bool? ?? false,
    );
  }
}

class CourseCard {
  const CourseCard({
    required this.id,
    required this.slug,
    required this.title,
    this.summary,
    this.levelLabel,
    this.thumbnailUrl,
    this.lessonsCount,
    this.isEnrolled,
    this.category,
    this.instructor,
    this.progress,
  });

  final int id;
  final String slug;
  final String title;
  final String? summary;
  final String? levelLabel;
  final String? thumbnailUrl;
  final int? lessonsCount;
  final bool? isEnrolled;
  final NamedRef? category;
  final NamedRef? instructor;
  final CourseProgress? progress;

  factory CourseCard.fromJson(Map<String, dynamic> j) => CourseCard(
    id: _int(j['id']),
    slug: j['slug'] as String,
    title: j['title'] as String? ?? '',
    summary: _str(j['summary']),
    levelLabel: _str(j['level_label']),
    thumbnailUrl: _str(j['thumbnail_url']),
    lessonsCount: (j['lessons_count'] as num?)?.toInt(),
    isEnrolled: j['is_enrolled'] as bool?,
    category: NamedRef.maybe(j['category']),
    instructor: NamedRef.maybe(j['instructor']),
    progress: CourseProgress.maybe(j['progress']),
  );
}

class CourseSection {
  const CourseSection({
    required this.title,
    required this.lessons,
    this.description,
  });
  final String title;
  final String? description;
  final List<LessonCard> lessons;

  factory CourseSection.fromJson(Map<String, dynamic> j) => CourseSection(
    title: j['title'] as String? ?? '',
    description: _str(j['description']),
    lessons: _list(j['lessons'], LessonCard.fromJson),
  );
}

class CourseDetail {
  const CourseDetail({
    required this.card,
    required this.sections,
    required this.rooms,
    required this.canEnroll,
    required this.enrolled,
    this.description,
    this.nextLessonSlug,
    this.nextLessonTitle,
  });

  final CourseCard card;
  final List<CourseSection> sections;
  final List<RoomCard> rooms;
  final bool canEnroll;
  final bool enrolled;
  final String? description;
  final String? nextLessonSlug;
  final String? nextLessonTitle;

  factory CourseDetail.fromJson(Map<String, dynamic> j) => CourseDetail(
    card: CourseCard.fromJson(j),
    sections: _list(j['sections'], CourseSection.fromJson),
    rooms: _list(j['rooms'], RoomCard.fromJson),
    canEnroll: j['can_enroll'] as bool? ?? false,
    enrolled: j['enrollment'] != null || (j['is_enrolled'] as bool? ?? false),
    description: _str(j['description']),
    nextLessonSlug:
        (j['next_lesson'] as Map<String, dynamic>?)?['slug'] as String?,
    nextLessonTitle:
        (j['next_lesson'] as Map<String, dynamic>?)?['title'] as String?,
  );
}

class RoomCard {
  const RoomCard({
    required this.id,
    required this.slug,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.isLive,
    required this.durationMinutes,
    this.scheduledAt,
    this.host,
    this.course,
  });

  final int id;
  final String slug;
  final String title;
  final String status;
  final String statusLabel;
  final bool isLive;
  final int durationMinutes;
  final DateTime? scheduledAt;
  final NamedRef? host;
  final NamedRef? course;

  factory RoomCard.fromJson(Map<String, dynamic> j) => RoomCard(
    id: _int(j['id']),
    slug: j['slug'] as String,
    title: j['title'] as String? ?? '',
    status: j['status'] as String? ?? '',
    statusLabel: j['status_label'] as String? ?? '',
    isLive: j['is_live'] as bool? ?? false,
    durationMinutes: _int(j['duration_minutes']),
    scheduledAt: _date(j['scheduled_at']),
    host: NamedRef.maybe(j['host']),
    course: NamedRef.maybe(j['course'], nameKey: 'title'),
  );
}

class RoomDetail {
  const RoomDetail({
    required this.card,
    required this.canJoin,
    required this.isManager,
    this.description,
    this.cancelReason,
    this.webUrl,
  });

  final RoomCard card;
  final bool canJoin;
  final bool isManager;
  final String? description;
  final String? cancelReason;
  final String? webUrl;

  factory RoomDetail.fromJson(Map<String, dynamic> j) => RoomDetail(
    card: RoomCard.fromJson(j),
    canJoin: j['can_join'] as bool? ?? false,
    isManager: j['is_manager'] as bool? ?? false,
    description: _str(j['description']),
    cancelReason: _str(j['cancel_reason']),
    webUrl: _str(j['web_url']),
  );
}

/// What the app needs to connect to a live class (LiveProvider::clientConfig).
class LiveJoinConfig {
  const LiveJoinConfig({
    required this.serverUrl,
    required this.token,
    required this.iceServers,
    required this.relayOnly,
    required this.canSpeak,
    required this.canVideo,
    required this.isModerator,
  });

  final String serverUrl;
  final String token;
  final List<({List<String> urls, String? username, String? credential})>
  iceServers;
  final bool relayOnly;
  final bool canSpeak;
  final bool canVideo;
  final bool isModerator;

  factory LiveJoinConfig.fromJson(Map<String, dynamic> j) {
    final perms = (j['permissions'] as Map<String, dynamic>?) ?? const {};

    return LiveJoinConfig(
      serverUrl: j['server_url'] as String,
      token: j['token'] as String,
      iceServers: ((j['ice_servers'] as List?) ?? const []).map((e) {
        final m = e as Map<String, dynamic>;
        final urls = m['urls'];
        return (
          urls: urls is List
              ? urls.map((u) => u.toString()).toList()
              : [urls.toString()],
          username: m['username'] as String?,
          credential: m['credential'] as String?,
        );
      }).toList(),
      relayOnly: j['ice_transport_policy'] == 'relay',
      canSpeak: perms['audio'] as bool? ?? false,
      canVideo: perms['video'] as bool? ?? false,
      isModerator: j['moderator'] as bool? ?? false,
    );
  }
}

class LiveRoomUser {
  const LiveRoomUser({required this.id, required this.name});
  final int id;
  final String name;

  factory LiveRoomUser.fromJson(Map<String, dynamic> j) =>
      LiveRoomUser(id: _int(j['id']), name: j['name'] as String? ?? 'User');
}

class LiveRoomMessage {
  const LiveRoomMessage({
    required this.id,
    required this.type,
    required this.body,
    required this.user,
    required this.isHost,
    required this.isAnswered,
    required this.isDeleted,
    this.createdAt,
  });

  final int id;
  final String type;
  final String body;
  final LiveRoomUser user;
  final bool isHost;
  final bool isAnswered;
  final bool isDeleted;
  final DateTime? createdAt;

  bool get isQuestion => type == 'question';

  factory LiveRoomMessage.fromJson(Map<String, dynamic> j) => LiveRoomMessage(
    id: _int(j['id']),
    type: j['type'] as String? ?? 'chat',
    body: j['body'] as String? ?? '',
    user: LiveRoomUser.fromJson(
      (j['user'] as Map<String, dynamic>?) ?? const {},
    ),
    isHost: j['is_host'] as bool? ?? false,
    isAnswered: j['is_answered'] as bool? ?? false,
    isDeleted: j['is_deleted'] as bool? ?? false,
    createdAt: _date(j['created_at']),
  );
}

class LiveParticipant {
  const LiveParticipant({
    required this.userId,
    required this.identity,
    required this.name,
    required this.role,
    required this.isMe,
    required this.isGuest,
    this.handRaisedAt,
  });

  final int userId;
  final String identity;
  final String name;
  final String role;
  final bool isMe;
  final bool isGuest;
  final DateTime? handRaisedAt;

  bool get handRaised => handRaisedAt != null;

  factory LiveParticipant.fromJson(Map<String, dynamic> j) => LiveParticipant(
    userId: _int(j['user_id']),
    identity: j['identity'] as String? ?? '',
    name: j['name'] as String? ?? 'Participant',
    role: j['role'] as String? ?? 'student',
    isMe: j['is_me'] as bool? ?? false,
    isGuest: j['is_guest'] as bool? ?? false,
    handRaisedAt: _date(j['hand_raised_at']),
  );
}

class LivePoll {
  const LivePoll({
    required this.id,
    required this.question,
    required this.options,
    required this.results,
    required this.total,
    required this.isOpen,
    required this.isQuiz,
    this.myVote,
    this.correctOption,
  });

  final int id;
  final String question;
  final List<String> options;
  final Map<int, int> results;
  final int total;
  final bool isOpen;
  final bool isQuiz;
  final int? myVote;
  final int? correctOption;

  factory LivePoll.fromJson(Map<String, dynamic> j) {
    final rawResults = (j['results'] as Map?) ?? const {};
    return LivePoll(
      id: _int(j['id']),
      question: j['question'] as String? ?? 'Poll',
      options: ((j['options'] as List?) ?? const [])
          .map((e) => e.toString())
          .toList(),
      results: rawResults.map(
        (key, value) =>
            MapEntry(int.tryParse(key.toString()) ?? 0, _int(value)),
      ),
      total: _int(j['total']),
      isOpen: j['is_open'] as bool? ?? false,
      isQuiz: j['is_quiz'] as bool? ?? false,
      myVote: (j['my_vote'] as num?)?.toInt(),
      correctOption: (j['correct_option'] as num?)?.toInt(),
    );
  }
}

class LiveMaterial {
  const LiveMaterial({
    required this.id,
    required this.title,
    this.originalName,
    this.mime,
    this.sizeBytes,
    this.downloadPath,
    this.downloadUrl,
  });

  final int id;
  final String title;
  final String? originalName;
  final String? mime;
  final int? sizeBytes;
  final String? downloadPath;
  final String? downloadUrl;

  String get displayName =>
      title.isNotEmpty ? title : (originalName ?? 'Material');

  factory LiveMaterial.fromJson(Map<String, dynamic> j) => LiveMaterial(
    id: _int(j['id']),
    title: j['title'] as String? ?? '',
    originalName: _str(j['original_name']),
    mime: _str(j['mime']),
    sizeBytes: (j['size_bytes'] as num?)?.toInt(),
    downloadPath: _str(j['download_path']),
    downloadUrl: _str(j['download_url']),
  );
}

class LiveRoomFeed {
  const LiveRoomFeed({
    required this.cursor,
    required this.messages,
    required this.participants,
    required this.polls,
    required this.materials,
    required this.handRaised,
    required this.isManager,
    required this.chatEnabled,
    required this.questionsEnabled,
    required this.participantCount,
    required this.handsCount,
    required this.openQuestionsCount,
  });

  final int cursor;
  final List<LiveRoomMessage> messages;
  final List<LiveParticipant> participants;
  final List<LivePoll> polls;
  final List<LiveMaterial> materials;
  final bool handRaised;
  final bool isManager;
  final bool chatEnabled;
  final bool questionsEnabled;
  final int participantCount;
  final int handsCount;
  final int openQuestionsCount;

  List<LiveRoomMessage> get chatMessages =>
      messages.where((m) => !m.isQuestion).toList();
  List<LiveRoomMessage> get questions =>
      messages.where((m) => m.isQuestion).toList();

  factory LiveRoomFeed.fromJson(Map<String, dynamic> j) {
    final room = (j['room'] as Map<String, dynamic>?) ?? const {};
    final counts = (j['counts'] as Map<String, dynamic>?) ?? const {};
    return LiveRoomFeed(
      cursor: _int(j['cursor']),
      messages: _list(j['messages'], LiveRoomMessage.fromJson),
      participants: _list(j['participants'], LiveParticipant.fromJson),
      polls: _list(j['polls'], LivePoll.fromJson),
      materials: _list(j['materials'], LiveMaterial.fromJson),
      handRaised: j['hand_raised'] as bool? ?? false,
      isManager: j['is_manager'] as bool? ?? false,
      chatEnabled: room['chat_enabled'] as bool? ?? true,
      questionsEnabled: room['questions_enabled'] as bool? ?? true,
      participantCount: _int(counts['participants']),
      handsCount: _int(counts['hands']),
      openQuestionsCount: _int(counts['questions_open']),
    );
  }
}

class LearningHome {
  const LearningHome({
    required this.live,
    required this.continueWatching,
    required this.myCourses,
    required this.upcoming,
    required this.completed,
  });

  final List<RoomCard> live;
  final List<LessonCard> continueWatching;
  final List<CourseCard> myCourses;
  final List<RoomCard> upcoming;
  final List<LessonCard> completed;

  factory LearningHome.fromJson(Map<String, dynamic> j) => LearningHome(
    live: _list(j['live'], RoomCard.fromJson),
    continueWatching: _list(j['continue_watching'], LessonCard.fromJson),
    myCourses: _list(j['my_courses'], CourseCard.fromJson),
    upcoming: _list(j['upcoming'], RoomCard.fromJson),
    completed: _list(j['completed'], LessonCard.fromJson),
  );
}
