// Learning (courses, lessons, live classes) — mirrors Api\LearningController.

int _int(dynamic v) => (v as num?)?.toInt() ?? 0;
String? _str(dynamic v) => v as String?;
DateTime? _date(dynamic v) => v == null ? null : DateTime.tryParse(v as String)?.toLocal();
List<T> _list<T>(dynamic v, T Function(Map<String, dynamic>) f) =>
    ((v as List?) ?? const []).map((e) => f(e as Map<String, dynamic>)).toList();

class NamedRef {
  const NamedRef({required this.id, required this.name, this.slug});
  final int id;
  final String name;
  final String? slug;

  static NamedRef? maybe(dynamic j, {String nameKey = 'name'}) {
    if (j == null) return null;
    final m = j as Map<String, dynamic>;
    return NamedRef(id: _int(m['id']), name: (m[nameKey] ?? m['name'] ?? '') as String, slug: _str(m['slug']));
  }
}

class LessonProgress {
  const LessonProgress({required this.positionSeconds, required this.percent, required this.completed});
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

  factory VideoSource.fromJson(Map<String, dynamic> j) =>
      VideoSource(label: j['label'] as String? ?? 'Video', url: j['url'] as String, height: (j['height'] as num?)?.toInt());
}

class LessonResource {
  const LessonResource({required this.title, required this.type, this.url, this.sizeLabel});
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
  const CourseProgress({required this.total, required this.completed, required this.percent, required this.started});
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
  const CourseSection({required this.title, required this.lessons, this.description});
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
        nextLessonSlug: (j['next_lesson'] as Map<String, dynamic>?)?['slug'] as String?,
        nextLessonTitle: (j['next_lesson'] as Map<String, dynamic>?)?['title'] as String?,
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
  final List<({List<String> urls, String? username, String? credential})> iceServers;
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
          urls: urls is List ? urls.map((u) => u.toString()).toList() : [urls.toString()],
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
