import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class AdminTaskRow {
  const AdminTaskRow({
    required this.id,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.priorityLabel,
    this.dueAt,
    this.overdue = false,
    this.assignedTo,
    this.assigneeName,
    this.assigneeJobTitle,
    this.createdBy,
    this.creatorName,
    this.commentsCount = 0,
    this.updatedAgo,
    this.description,
    this.canEdit = false,
    this.canMove = false,
    this.comments = const [],
  });

  final int id;
  final String title;
  final String status;
  final String statusLabel;
  final String priority;
  final String priorityLabel;
  final String? dueAt;
  final bool overdue;
  final int? assignedTo;
  final String? assigneeName;
  final String? assigneeJobTitle;
  final int? createdBy;
  final String? creatorName;
  final int commentsCount;
  final String? updatedAgo;
  final String? description;
  final bool canEdit;
  final bool canMove;
  final List<AdminTaskComment> comments;

  bool get isOpen => status == 'todo' || status == 'in_progress';

  factory AdminTaskRow.fromJson(Map<String, dynamic> j) => AdminTaskRow(
        id: (j['id'] as num).toInt(),
        title: j['title'] as String? ?? '',
        status: j['status'] as String? ?? 'todo',
        statusLabel: j['status_label'] as String? ?? '',
        priority: j['priority'] as String? ?? 'normal',
        priorityLabel: j['priority_label'] as String? ?? '',
        dueAt: j['due_at'] as String?,
        overdue: j['overdue'] == true,
        assignedTo: (j['assigned_to'] as num?)?.toInt(),
        assigneeName: j['assignee_name'] as String?,
        assigneeJobTitle: j['assignee_job_title'] as String?,
        createdBy: (j['created_by'] as num?)?.toInt(),
        creatorName: j['creator_name'] as String?,
        commentsCount: (j['comments_count'] as num?)?.toInt() ?? 0,
        updatedAgo: j['updated_ago'] as String?,
        description: j['description'] as String?,
        canEdit: j['can_edit'] == true,
        canMove: j['can_move'] == true,
        comments: (j['comments'] as List? ?? const [])
            .map((e) => AdminTaskComment.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class AdminTaskComment {
  const AdminTaskComment({
    required this.id,
    required this.body,
    required this.userName,
    this.userJobTitle,
    this.mine = false,
    this.createdAgo,
  });
  final int id;
  final String body;
  final String userName;
  final String? userJobTitle;
  final bool mine;
  final String? createdAgo;

  factory AdminTaskComment.fromJson(Map<String, dynamic> j) => AdminTaskComment(
        id: (j['id'] as num).toInt(),
        body: j['body'] as String? ?? '',
        userName: j['user_name'] as String? ?? 'Someone',
        userJobTitle: j['user_job_title'] as String?,
        mine: j['mine'] == true,
        createdAgo: j['created_ago'] as String?,
      );
}

class AdminTaskPerson {
  const AdminTaskPerson({required this.id, required this.name, this.jobTitle});
  final int id;
  final String name;
  final String? jobTitle;

  String get label => jobTitle == null || jobTitle!.isEmpty ? name : '$name ($jobTitle)';
}

class AdminTaskPage {
  const AdminTaskPage(
    this.items, {
    this.currentPage = 1,
    this.lastPage = 1,
    this.total = 0,
    this.counts = const {},
    this.canSeeAll = false,
  });
  final List<AdminTaskRow> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final Map<String, int?> counts;
  final bool canSeeAll;
}

class AdminTasksRepository {
  AdminTasksRepository(this._api);
  final ApiClient _api;

  /// [view]: mine, given or all. [status]: open, done, cancelled or any.
  Future<AdminTaskPage> tasks({
    String view = 'mine',
    String status = 'open',
    String? q,
    int page = 1,
  }) async {
    final j = await _api.get('/admin/tasks', query: {
      'view': view,
      'status': status,
      if (q != null && q.isNotEmpty) 'q': q,
      'page': page,
    }) as Map<String, dynamic>;
    final meta = j['meta'] as Map<String, dynamic>? ?? const {};
    final counts = (meta['counts'] as Map<String, dynamic>? ?? const {})
        .map((k, v) => MapEntry(k, (v as num?)?.toInt()));
    return AdminTaskPage(
      (j['data'] as List)
          .map((e) => AdminTaskRow.fromJson(e as Map<String, dynamic>))
          .toList(),
      currentPage: (meta['current_page'] as num?)?.toInt() ?? page,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? page,
      total: (meta['total'] as num?)?.toInt() ?? 0,
      counts: counts,
      canSeeAll: meta['can_see_all'] == true,
    );
  }

  Future<AdminTaskRow> task(int id) async => AdminTaskRow.fromJson(
      (await _api.get('/admin/tasks/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<List<AdminTaskPerson>> people() async {
    final j = await _api.get('/admin/tasks/people') as Map<String, dynamic>;
    return (j['data'] as List)
        .map((e) => e as Map<String, dynamic>)
        .map((e) => AdminTaskPerson(
              id: (e['id'] as num).toInt(),
              name: e['name'] as String? ?? '',
              jobTitle: e['job_title'] as String?,
            ))
        .toList();
  }

  Future<int> create(Map<String, dynamic> body) async {
    final j = await _api.post('/admin/tasks', data: body) as Map<String, dynamic>;
    return ((j['data'] as Map<String, dynamic>)['id'] as num).toInt();
  }

  Future<void> update(int id, Map<String, dynamic> body) =>
      _api.put('/admin/tasks/$id', data: body);

  Future<void> setStatus(int id, String status) =>
      _api.post('/admin/tasks/$id/status', data: {'status': status});

  Future<void> comment(int id, String body) =>
      _api.post('/admin/tasks/$id/comments', data: {'body': body});

  Future<void> delete(int id) => _api.delete('/admin/tasks/$id');
}

final adminTasksRepositoryProvider =
    Provider((ref) => AdminTasksRepository(ref.watch(apiClientProvider)));

final adminTaskViewProvider = StateProvider<String>((ref) => 'mine');
final adminTaskStatusProvider = StateProvider<String>((ref) => 'open');
final adminTaskSearchProvider = StateProvider<String>((ref) => '');

/// First page for the current view / status / search.
final adminTasksProvider = FutureProvider.autoDispose<AdminTaskPage>(
    (ref) => ref.watch(adminTasksRepositoryProvider).tasks(
          view: ref.watch(adminTaskViewProvider),
          status: ref.watch(adminTaskStatusProvider),
          q: ref.watch(adminTaskSearchProvider),
        ));

final adminTaskProvider = FutureProvider.autoDispose.family<AdminTaskRow, int>(
    (ref, id) => ref.watch(adminTasksRepositoryProvider).task(id));

final adminTaskPeopleProvider = FutureProvider.autoDispose<List<AdminTaskPerson>>(
    (ref) => ref.watch(adminTasksRepositoryProvider).people());
