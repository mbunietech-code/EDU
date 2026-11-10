import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

// Learning admin pages added to match the web: topics, live sessions,
// attendance, learner progress and instructors. Rows are plain JSON maps.

typedef LearningRow = Map<String, dynamic>;

class LearningPage {
  const LearningPage(this.items, this.meta);
  final List<LearningRow> items;
  final Map<String, dynamic> meta;

  int get page => (meta['current_page'] as num?)?.toInt() ?? 1;
  int get lastPage => (meta['last_page'] as num?)?.toInt() ?? 1;
  int get total => (meta['total'] as num?)?.toInt() ?? items.length;
}

class LearningExtrasRepository {
  LearningExtrasRepository(this._api);
  final ApiClient _api;

  Future<Map<String, dynamic>> _map(String path, [Map<String, dynamic>? q]) async =>
      Map<String, dynamic>.from(await _api.get(path, query: q) as Map);

  Future<LearningPage> _page(String path, Map<String, dynamic> q) async {
    final j = await _map(path, q);
    return LearningPage(
      (j['data'] as List? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      Map<String, dynamic>.from(j['meta'] as Map? ?? const {}),
    );
  }

  Future<String> _msg(Future<dynamic> call) async {
    final r = await call;
    return (r is Map ? r['message'] as String? : null) ?? 'Done.';
  }

  Future<Map<String, dynamic>> live({bool check = false}) =>
      _map('/admin/learning/live', {if (check) 'check': 1});
  Future<Map<String, dynamic>> liveRoom(int id) => _map('/admin/learning/live/$id');
  Future<String> deleteLiveMessage(int room, int message) =>
      _msg(_api.delete('/admin/learning/live/$room/messages/$message'));

  Future<LearningPage> attendance({int? room, String? from, String? to, int page = 1}) =>
      _page('/admin/learning/attendance', {
        'room': ?room,
        'from': ?from,
        'to': ?to,
        'page': page,
      });

  Future<LearningPage> progress({String tab = 'learners', String? q, int page = 1}) =>
      _page('/admin/learning/progress', {
        'tab': tab,
        if (q != null && q.isNotEmpty) 'q': q,
        'page': page,
      });
  Future<Map<String, dynamic>> learner(int id) => _map('/admin/learning/progress/$id');

  Future<LearningPage> instructors({String? q, int page = 1}) =>
      _page('/admin/learning/instructors', {
        if (q != null && q.isNotEmpty) 'q': q,
        'page': page,
      });
  Future<List<LearningRow>> candidates(String q) async {
    final j = await _map('/admin/learning/instructors/candidates', {'q': q});
    return (j['data'] as List? ?? const [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
  }

  Future<String> grantInstructor(int userId) =>
      _msg(_api.post('/admin/learning/instructors', data: {'user_id': userId}));
  Future<String> revokeInstructor(int userId, String reason) => _msg(
      _api.delete('/admin/learning/instructors/$userId', data: {'reason': reason}));

  Future<LearningPage> topics({int? course, int page = 1}) =>
      _page('/admin/learning/topics', {'course': ?course, 'page': page});
  Future<String> saveTopic(int? id, Map<String, dynamic> body) => _msg(id == null
      ? _api.post('/admin/learning/topics', data: body)
      : _api.put('/admin/learning/topics/$id', data: body));
  Future<String> deleteTopic(int id, String reason) =>
      _msg(_api.delete('/admin/learning/topics/$id', data: {'reason': reason}));
}

final learningExtrasRepositoryProvider =
    Provider((ref) => LearningExtrasRepository(ref.watch(apiClientProvider)));

// ============================================================== helpers

String _when(dynamic iso, {String fmt = 'd MMM y HH:mm'}) {
  final d = iso == null ? null : DateTime.tryParse('$iso')?.toLocal();
  return d == null ? '—' : DateFormat(fmt).format(d);
}

String _dur(dynamic seconds) {
  final s = (seconds as num?)?.toInt() ?? 0;
  if (s < 60) return '${s}s';
  final h = s ~/ 3600;
  final m = (s % 3600) ~/ 60;
  return h > 0 ? '${h}h ${m}m' : '${m}m';
}

void _toast(BuildContext context, String msg) {
  if (context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }
}

String _err(ApiException e) =>
    e.errors?.values.expand((v) => v).firstOrNull ?? e.message;

class _Sub extends StatelessWidget {
  const _Sub(this.text, {this.color = AppColors.gray500});
  final String text;
  final Color color;
  @override
  Widget build(BuildContext context) =>
      Text(text, style: TextStyle(fontSize: 12, color: color));
}

class _Bold extends StatelessWidget {
  const _Bold(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Text(text,
      style: const TextStyle(
          fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.gray900));
}

/// Paged list with optional search and a header widget; reloads on demand.
class _PagedList extends ConsumerStatefulWidget {
  const _PagedList({
    super.key,
    required this.load,
    required this.itemBuilder,
    this.searchHint,
    this.header,
    this.emptyText = 'Nothing here.',
  });

  final Future<LearningPage> Function(String q, int page) load;
  final Widget Function(BuildContext context, LearningRow row, VoidCallback reload)
      itemBuilder;
  final String? searchHint;
  final Widget Function(LearningPage? first, VoidCallback reload)? header;
  final String emptyText;

  @override
  ConsumerState<_PagedList> createState() => _PagedListState();
}

class _PagedListState extends ConsumerState<_PagedList>
    with AutomaticKeepAliveClientMixin {
  final _search = TextEditingController();
  String _q = '';
  final List<LearningRow> _items = [];
  LearningPage? _first;
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    reload();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> reload() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final p = await widget.load(_q, 1);
      if (!mounted) return;
      setState(() {
        _first = p;
        _items
          ..clear()
          ..addAll(p.items);
        _page = p.page;
        _lastPage = p.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = _err(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _more() async {
    setState(() => _loadingMore = true);
    try {
      final p = await widget.load(_q, _page + 1);
      if (!mounted) return;
      setState(() {
        _items.addAll(p.items);
        _page = p.page;
        _lastPage = p.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final children = <Widget>[
      if (widget.searchHint != null)
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (v) {
              _q = v.trim();
              reload();
            },
            decoration: InputDecoration(
              isDense: true,
              hintText: widget.searchHint,
              prefixIcon: const Icon(Icons.search, size: 20),
            ),
          ),
        ),
      if (widget.header != null) widget.header!(_first, reload),
      if (_loading)
        const Padding(
          padding: EdgeInsets.only(top: 80),
          child: Center(child: CircularProgressIndicator()),
        )
      else if (_error != null)
        Padding(
          padding: const EdgeInsets.only(top: 24),
          child: Text(_error!, style: const TextStyle(color: AppColors.red700)),
        )
      else if (_items.isEmpty)
        Padding(
          padding: const EdgeInsets.only(top: 80),
          child: Center(child: _Sub(widget.emptyText)),
        )
      else ...[
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: _Sub('${_first?.total ?? _items.length} total'),
        ),
        for (final r in _items) widget.itemBuilder(context, r, reload),
        if (_page < _lastPage)
          MbuiButton(
            label: 'Load more',
            variant: MbuiVariant.secondary,
            fullWidth: true,
            loading: _loadingMore,
            onPressed: _more,
          ),
      ],
    ];
    return RefreshIndicator(
      onRefresh: reload,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        children: children,
      ),
    );
  }
}

Widget _card({required Widget child, VoidCallback? onTap}) => Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(padding: const EdgeInsets.all(14), onTap: onTap, child: child),
    );

// ============================================================== topics

class LearningTopicsTab extends ConsumerStatefulWidget {
  const LearningTopicsTab({super.key});

  @override
  ConsumerState<LearningTopicsTab> createState() => _LearningTopicsTabState();
}

class _LearningTopicsTabState extends ConsumerState<LearningTopicsTab> {
  int? _course;
  final _listKey = GlobalKey<_PagedListState>();

  LearningExtrasRepository get _repo => ref.read(learningExtrasRepositoryProvider);

  Future<void> _edit(List<LearningRow> courses, [LearningRow? topic]) async {
    final title = TextEditingController(text: '${topic?['title'] ?? ''}');
    final description = TextEditingController(text: '${topic?['description'] ?? ''}');
    final position = TextEditingController(
        text: topic == null ? '' : '${topic['position'] ?? ''}');
    int? course = topic == null ? _course : (topic['course_id'] as num?)?.toInt();

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheet) => SafeArea(
          child: Padding(
            padding: EdgeInsets.only(
                left: 20, right: 20, bottom: MediaQuery.viewInsetsOf(ctx).bottom + 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(topic == null ? 'Add topic' : 'Edit topic',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                if (topic == null)
                  DropdownButtonFormField<int>(
                    initialValue: course,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Course'),
                    items: [
                      for (final c in courses)
                        DropdownMenuItem(
                            value: (c['id'] as num).toInt(),
                            child: Text('${c['title']}', overflow: TextOverflow.ellipsis)),
                    ],
                    onChanged: (v) => setSheet(() => course = v),
                  ),
                TextField(controller: title, decoration: const InputDecoration(labelText: 'Title')),
                TextField(
                  controller: description,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Description (optional)'),
                ),
                TextField(
                  controller: position,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                      labelText: 'Position (optional)', helperText: 'Empty = at the end'),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () async {
                    if (title.text.trim().isEmpty || (topic == null && course == null)) return;
                    try {
                      final msg = await _repo.saveTopic(
                        (topic?['id'] as num?)?.toInt(),
                        {
                          if (topic == null) 'learning_course_id': course,
                          'title': title.text.trim(),
                          'description': description.text.trim().isEmpty ? null : description.text.trim(),
                          'position': int.tryParse(position.text.trim()),
                        },
                      );
                      if (ctx.mounted) Navigator.pop(ctx, true);
                      if (mounted) _toast(context, msg);
                    } on ApiException catch (e) {
                      if (mounted) _toast(context, _err(e));
                    }
                  },
                  child: Text(topic == null ? 'Add topic' : 'Save topic'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    title.dispose();
    description.dispose();
    position.dispose();
    if (saved == true) _listKey.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return _PagedList(
      key: _listKey,
      emptyText: 'No topics yet.',
      load: (_, page) => _repo.topics(course: _course, page: page),
      header: (first, reload) {
        final courses = ((first?.meta['courses'] as List?) ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        final canManage = first?.meta['can_manage'] == true;
        return Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int?>(
                  initialValue: _course,
                  isExpanded: true,
                  decoration: const InputDecoration(isDense: true, labelText: 'Course'),
                  items: [
                    const DropdownMenuItem<int?>(value: null, child: Text('All courses')),
                    for (final c in courses)
                      DropdownMenuItem<int?>(
                          value: (c['id'] as num).toInt(),
                          child: Text('${c['title']}', overflow: TextOverflow.ellipsis)),
                  ],
                  onChanged: (v) {
                    setState(() => _course = v);
                    reload();
                  },
                ),
              ),
              if (canManage) ...[
                const SizedBox(width: 8),
                IconButton.filled(
                  tooltip: 'Add topic',
                  onPressed: () => _edit(courses),
                  icon: const Icon(Icons.add),
                ),
              ],
            ],
          ),
        );
      },
      itemBuilder: (context, t, reload) {
        final canManage = _listKey.currentState?._first?.meta['can_manage'] == true;
        final courses = ((_listKey.currentState?._first?.meta['courses'] as List?) ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        final impact = (t['delete_impact'] as List? ?? const []).join('\n');
        return _card(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Bold('${t['position']}. ${t['title']}'),
              const SizedBox(height: 4),
              _Sub('${t['course_title'] ?? '—'} · ${t['lessons']} lessons'),
              if ((t['description'] ?? '').toString().isNotEmpty) _Sub('${t['description']}'),
              if (canManage)
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(onPressed: () => _edit(courses, t), child: const Text('Edit')),
                    TextButton(
                      style: TextButton.styleFrom(foregroundColor: AppColors.red600),
                      onPressed: () async {
                        final reason = await promptReason(
                          context,
                          title: 'Delete topic',
                          actionLabel: 'Delete',
                          hint: 'Why? ${impact.isEmpty ? '' : '\n$impact'}',
                        );
                        if (reason == null || reason.length < 3) return;
                        try {
                          final msg = await _repo.deleteTopic((t['id'] as num).toInt(), reason);
                          if (context.mounted) _toast(context, msg);
                          reload();
                        } on ApiException catch (e) {
                          if (context.mounted) _toast(context, _err(e));
                        }
                      },
                      child: const Text('Delete'),
                    ),
                  ],
                ),
            ],
          ),
        );
      },
    );
  }
}

// ============================================================== live

class LearningLiveTab extends ConsumerStatefulWidget {
  const LearningLiveTab({super.key});

  @override
  ConsumerState<LearningLiveTab> createState() => _LearningLiveTabState();
}

class _LearningLiveTabState extends ConsumerState<LearningLiveTab>
    with AutomaticKeepAliveClientMixin {
  Future<Map<String, dynamic>>? _future;
  bool _check = false;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() => setState(() {
        _future = ref.read(learningExtrasRepositoryProvider).live(check: _check);
      });

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          final e = snap.error;
          return Center(child: Text(e is ApiException ? _err(e) : '$e'));
        }
        final d = snap.data!;
        final provider = Map<String, dynamic>.from(d['provider'] as Map? ?? const {});
        final check = d['check'] as Map?;
        final live = (d['live'] as List? ?? const []).cast<Map>();
        final recent = (d['recent'] as List? ?? const []).cast<Map>();
        final issues = (provider['issues'] as List? ?? const []).cast<dynamic>();
        final warnings = (provider['warnings'] as List? ?? const []).cast<dynamic>();

        return RefreshIndicator(
          onRefresh: () async => _load(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: _Bold('${provider['label'] ?? 'Video server'}')),
                        MbuiBadge(
                          provider['configured'] == true ? 'Configured' : 'Not configured',
                          appearance: provider['configured'] == true
                              ? MbuiAppearance.success
                              : MbuiAppearance.danger,
                        ),
                      ],
                    ),
                    if (provider['server_url'] != null) _Sub('${provider['server_url']}'),
                    _Sub('Recording: ${provider['recording'] == true ? 'yes' : 'no'} · TURN: ${provider['turn'] == true ? 'yes' : 'no'}'),
                    for (final i in issues) _Sub('• $i', color: AppColors.red700),
                    for (final w in warnings) _Sub('• $w', color: AppColors.amber700),
                    if (check != null)
                      _Sub(
                        check['reachable'] == true
                            ? 'The video server answered.'
                            : 'The video server did not answer: ${check['error'] ?? 'unknown error'}',
                        color: check['reachable'] == true ? AppColors.emerald700 : AppColors.red700,
                      ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      icon: const Icon(Icons.network_check, size: 18),
                      label: const Text('Check the video server'),
                      onPressed: () {
                        _check = true;
                        _load();
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              MbuiSectionLabel('Live now (${live.length})'),
              const SizedBox(height: 8),
              if (live.isEmpty) const _Sub('No class is live right now.'),
              for (final r in live)
                _card(
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => LiveRoomAdminScreen(roomId: (r['id'] as num).toInt()),
                  )),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: _Bold('${r['title']}')),
                          const MbuiBadge('Live', appearance: MbuiAppearance.danger),
                        ],
                      ),
                      _Sub('Host: ${r['host_name'] ?? '—'}${r['category'] != null ? ' · ${r['category']}' : ''}'),
                      _Sub('${r['present']} in the room now · peak ${r['peak']} · since ${_when(r['started_at'], fmt: 'HH:mm')}'),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Recently ended'),
              const SizedBox(height: 8),
              if (recent.isEmpty) const _Sub('No sessions yet.'),
              for (final s in recent)
                _card(
                  onTap: s['room_deleted'] == true
                      ? null
                      : () => Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) =>
                                LiveRoomAdminScreen(roomId: (s['room_id'] as num).toInt()),
                          )),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _Bold('${s['room_title']}'),
                      _Sub('Host: ${s['host_name'] ?? '—'} · ${s['attendees']} attended · peak ${s['peak']}'),
                      _Sub('${_when(s['started_at'])} → ${_when(s['ended_at'], fmt: 'HH:mm')}'),
                    ],
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class LiveRoomAdminScreen extends ConsumerStatefulWidget {
  const LiveRoomAdminScreen({super.key, required this.roomId});
  final int roomId;

  @override
  ConsumerState<LiveRoomAdminScreen> createState() => _LiveRoomAdminScreenState();
}

class _LiveRoomAdminScreenState extends ConsumerState<LiveRoomAdminScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() => setState(() {
        _future = ref.read(learningExtrasRepositoryProvider).liveRoom(widget.roomId);
      });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Live class')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            final e = snap.error;
            return Center(child: Text(e is ApiException ? _err(e) : '$e'));
          }
          final d = snap.data!;
          final room = Map<String, dynamic>.from(d['room'] as Map);
          final session = d['session'] as Map?;
          final people = (d['attendances'] as List? ?? const []).cast<Map>();
          final messages = (d['messages'] as List? ?? const []).cast<Map>();
          final canModerate = d['can_moderate'] == true;
          final present = people.where((p) => p['present'] == true).length;

          return RefreshIndicator(
            onRefresh: () async => _load(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                MbuiTitle('${room['title']}'),
                const SizedBox(height: 8),
                MbuiCard(
                  child: Column(
                    children: [
                      AdminRow('Status', _capital('${room['status']}')),
                      AdminRow('Host', '${room['host_name'] ?? '—'}'),
                      if (room['category'] != null) AdminRow('Category', '${room['category']}'),
                      if (session != null) ...[
                        AdminRow('Started', _when(session['started_at'])),
                        AdminRow('Ended', session['ended_at'] == null ? 'Still live' : _when(session['ended_at'])),
                        AdminRow('People', '$present here now · ${people.length} came · peak ${session['peak']}'),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                const MbuiSectionLabel('Who came'),
                const SizedBox(height: 8),
                if (people.isEmpty) const _Sub('Nobody yet.'),
                for (final p in people)
                  _card(
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${p['name']}${p['role'] != 'participant' ? ' (${p['role']})' : ''}',
                                  style: const TextStyle(fontWeight: FontWeight.w600)),
                              _Sub('${_dur(p['total_seconds'])} in the class · joined ${p['join_count']}x · first ${_when(p['first_joined_at'], fmt: 'HH:mm')}'),
                              if (p['removed'] == true)
                                _Sub('Removed${p['removed_by'] != null ? ' by ${p['removed_by']}' : ''}',
                                    color: AppColors.red700),
                            ],
                          ),
                        ),
                        if (p['hand_raised'] == true) const Text('✋ '),
                        MbuiBadge(
                          p['present'] == true ? 'Here' : 'Left',
                          appearance: p['present'] == true
                              ? MbuiAppearance.success
                              : MbuiAppearance.neutral,
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 16),
                MbuiSectionLabel('Class chat (${messages.length})'),
                const SizedBox(height: 8),
                if (messages.isEmpty) const _Sub('No messages.'),
                for (final m in messages)
                  _card(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _Sub('${m['user_name']} · ${_when(m['created_at'], fmt: 'HH:mm')}${m['type'] == 'question' ? ' · question' : ''}'),
                              const SizedBox(height: 2),
                              m['deleted'] == true
                                  ? const _Sub('Message removed', color: AppColors.gray400)
                                  : SelectableText('${m['body'] ?? ''}'),
                            ],
                          ),
                        ),
                        if (canModerate && m['deleted'] != true)
                          IconButton(
                            tooltip: 'Remove message',
                            icon: const Icon(Icons.delete_outline, size: 18),
                            onPressed: () async {
                              final ok = await confirmAdminAction(
                                context,
                                title: 'Remove message',
                                message: 'Hide this message from the class chat for everyone?',
                                actionLabel: 'Remove',
                                variant: MbuiVariant.danger,
                              );
                              if (!ok) return;
                              try {
                                final msg = await ref
                                    .read(learningExtrasRepositoryProvider)
                                    .deleteLiveMessage(widget.roomId, (m['id'] as num).toInt());
                                if (context.mounted) _toast(context, msg);
                                _load();
                              } on ApiException catch (e) {
                                if (context.mounted) _toast(context, _err(e));
                              }
                            },
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

String _capital(String s) => s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

// ============================================================== attendance

class LearningAttendanceTab extends ConsumerStatefulWidget {
  const LearningAttendanceTab({super.key});

  @override
  ConsumerState<LearningAttendanceTab> createState() => _LearningAttendanceTabState();
}

class _LearningAttendanceTabState extends ConsumerState<LearningAttendanceTab> {
  int? _room;
  DateTimeRange? _range;

  @override
  Widget build(BuildContext context) {
    final f = DateFormat('yyyy-MM-dd');
    return _PagedList(
      emptyText: 'No sessions for these filters.',
      load: (_, page) => ref.read(learningExtrasRepositoryProvider).attendance(
            room: _room,
            from: _range == null ? null : f.format(_range!.start),
            to: _range == null ? null : f.format(_range!.end),
            page: page,
          ),
      header: (first, reload) {
        final summary = Map<String, dynamic>.from(first?.meta['summary'] as Map? ?? const {});
        final rooms = ((first?.meta['rooms'] as List?) ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<int?>(
              initialValue: rooms.any((r) => r['id'] == _room) ? _room : null,
              isExpanded: true,
              decoration: const InputDecoration(isDense: true, labelText: 'Room'),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('All rooms')),
                for (final r in rooms)
                  DropdownMenuItem<int?>(
                      value: (r['id'] as num).toInt(),
                      child: Text('${r['title']}', overflow: TextOverflow.ellipsis)),
              ],
              onChanged: (v) {
                setState(() => _room = v);
                reload();
              },
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.date_range, size: 18),
                    label: Text(_range == null
                        ? 'Any dates'
                        : '${DateFormat('d MMM').format(_range!.start)} - ${DateFormat('d MMM y').format(_range!.end)}'),
                    onPressed: () async {
                      final now = DateTime.now();
                      final picked = await showDateRangePicker(
                        context: context,
                        firstDate: DateTime(now.year - 5),
                        lastDate: now,
                        initialDateRange: _range,
                      );
                      if (picked == null) return;
                      setState(() => _range = picked);
                      reload();
                    },
                  ),
                ),
                if (_range != null)
                  IconButton(
                    tooltip: 'Any dates',
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      setState(() => _range = null);
                      reload();
                    },
                  ),
              ],
            ),
            if (first != null) ...[
              const SizedBox(height: 8),
              _Sub('${summary['sessions'] ?? 0} sessions · ${summary['attendees'] ?? 0} attendees · ${summary['per_session'] ?? 0} per session'),
            ],
            const SizedBox(height: 8),
          ],
        );
      },
      itemBuilder: (context, s, _) => _card(
        onTap: s['room_deleted'] == true
            ? null
            : () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => LiveRoomAdminScreen(roomId: (s['room_id'] as num).toInt()),
                )),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _Bold('${s['room_title']}'),
            _Sub('${_when(s['started_at'])}${s['ended_at'] != null ? ' → ${_when(s['ended_at'], fmt: 'HH:mm')}' : ' · live'}'),
            _Sub('${s['attendees']} attended · peak ${s['peak']} · about ${_dur(s['average_seconds'])} each'),
          ],
        ),
      ),
    );
  }
}

// ============================================================== progress

class LearningProgressTab extends ConsumerStatefulWidget {
  const LearningProgressTab({super.key});

  @override
  ConsumerState<LearningProgressTab> createState() => _LearningProgressTabState();
}

class _LearningProgressTabState extends ConsumerState<LearningProgressTab> {
  String _tab = 'learners';
  Key _listKey = UniqueKey();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'learners', label: Text('Learners')),
              ButtonSegment(value: 'courses', label: Text('Courses')),
            ],
            selected: {_tab},
            onSelectionChanged: (s) => setState(() {
              _tab = s.first;
              _listKey = UniqueKey();
            }),
          ),
        ),
        Expanded(
          child: _PagedList(
            key: _listKey,
            searchHint: _tab == 'courses' ? 'Course title' : 'Learner name or email',
            emptyText: 'No progress yet.',
            load: (q, page) =>
                ref.read(learningExtrasRepositoryProvider).progress(tab: _tab, q: q, page: page),
            itemBuilder: (context, r, _) => _tab == 'courses'
                ? _card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: _Bold('${r['title']}')),
                            MbuiBadge('${r['average_completion']}% done'),
                          ],
                        ),
                        _Sub('${r['category'] ?? '—'} · ${r['lessons']} lessons'),
                        _Sub('${r['enrolled']} enrolled · ${r['started_learners']} started · ${r['completed']} finished'),
                      ],
                    ),
                  )
                : _card(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => LearnerProgressScreen(
                          userId: (r['id'] as num).toInt(), name: '${r['name']}'),
                    )),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _Bold('${r['name']}'),
                        _Sub('${r['email']}'),
                        _Sub('${r['enrolled']} courses · ${r['started']} lessons started · ${r['completed']} done · ${_dur(r['watch_seconds'])} watched'),
                        if (r['last_activity_at'] != null)
                          _Sub('Last active ${_when(r['last_activity_at'])}'),
                      ],
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}

class LearnerProgressScreen extends ConsumerWidget {
  const LearnerProgressScreen({super.key, required this.userId, required this.name});
  final int userId;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(name)),
      body: FutureBuilder<Map<String, dynamic>>(
        future: ref.read(learningExtrasRepositoryProvider).learner(userId),
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            final e = snap.error;
            return Center(child: Text(e is ApiException ? _err(e) : '$e'));
          }
          final d = snap.data!;
          final stats = Map<String, dynamic>.from(d['stats'] as Map? ?? const {});
          final courses = (d['courses'] as List? ?? const []).cast<Map>();
          final lessons = (d['lessons'] as List? ?? const []).cast<Map>();
          final attendance = (d['attendance'] as List? ?? const []).cast<Map>();
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiCard(
                child: Column(
                  children: [
                    AdminRow('Email', '${(d['user'] as Map?)?['email'] ?? '—'}'),
                    AdminRow('Courses', '${stats['courses_enrolled'] ?? 0} enrolled · ${stats['courses_completed'] ?? 0} finished'),
                    AdminRow('Lessons', '${stats['started'] ?? 0} started · ${stats['completed'] ?? 0} done'),
                    AdminRow('Watched', _dur(stats['watch_seconds'])),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Courses'),
              const SizedBox(height: 8),
              if (courses.isEmpty) const _Sub('No courses yet.'),
              for (final c in courses)
                _card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: _Bold('${c['title']}')),
                          MbuiBadge('${c['percent']}%',
                              appearance: (c['percent'] as num? ?? 0) >= 100
                                  ? MbuiAppearance.success
                                  : MbuiAppearance.info),
                        ],
                      ),
                      const SizedBox(height: 6),
                      LinearProgressIndicator(value: ((c['percent'] as num?) ?? 0) / 100),
                      const SizedBox(height: 6),
                      _Sub('${c['completed']} of ${c['total']} lessons${c['enrolled'] == true ? ' · enrolled ${_when(c['enrolled_at'], fmt: 'd MMM y')}' : ' · not enrolled'}'),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Recent lessons'),
              const SizedBox(height: 8),
              if (lessons.isEmpty) const _Sub('No lessons watched yet.'),
              for (final l in lessons)
                _card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: _Bold('${l['title']}')),
                          if (l['completed'] == true)
                            const MbuiBadge('Done', appearance: MbuiAppearance.success),
                        ],
                      ),
                      _Sub('${l['course'] ?? '—'} · ${_dur(l['watched_seconds'])} of ${_dur(l['duration_seconds'])} · ${_when(l['last_watched_at'])}'),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Live classes'),
              const SizedBox(height: 8),
              if (attendance.isEmpty) const _Sub('No live classes yet.'),
              for (final a in attendance)
                _card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _Bold('${a['room_title']}'),
                      _Sub('${_when(a['joined_at'])} · ${_dur(a['total_seconds'])} · joined ${a['join_count']}x${a['role'] != 'participant' ? ' · ${a['role']}' : ''}'),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

// ============================================================== instructors

class LearningInstructorsTab extends ConsumerStatefulWidget {
  const LearningInstructorsTab({super.key});

  @override
  ConsumerState<LearningInstructorsTab> createState() => _LearningInstructorsTabState();
}

class _LearningInstructorsTabState extends ConsumerState<LearningInstructorsTab> {
  final _listKey = GlobalKey<_PagedListState>();

  LearningExtrasRepository get _repo => ref.read(learningExtrasRepositoryProvider);

  Future<void> _grant() async {
    final user = await showModalBottomSheet<LearningRow>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _CandidatePicker(repo: _repo),
    );
    if (user == null || !mounted) return;
    final ok = await confirmAdminAction(
      context,
      title: 'Give instructor access',
      message: '${user['name']} (${user['email']}) will be able to host live rooms and upload lessons.',
      actionLabel: 'Give access',
    );
    if (!ok) return;
    try {
      final msg = await _repo.grantInstructor((user['id'] as num).toInt());
      if (mounted) _toast(context, msg);
      _listKey.currentState?.reload();
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    return _PagedList(
      key: _listKey,
      searchHint: 'Instructor name or email',
      emptyText: 'No instructors yet.',
      load: (q, page) => _repo.instructors(q: q, page: page),
      header: (first, _) => first?.meta['can_manage'] == true
          ? Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiButton(
                label: 'Give instructor access',
                icon: Icons.person_add_alt,
                variant: MbuiVariant.secondary,
                fullWidth: true,
                onPressed: _grant,
              ),
            )
          : const SizedBox.shrink(),
      itemBuilder: (context, u, reload) {
        final canManage = _listKey.currentState?._first?.meta['can_manage'] == true;
        return _card(
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _Bold('${u['name']}'),
                    _Sub('${u['email']}'),
                    _Sub('${u['rooms']} rooms · ${u['lessons']} lessons · ${u['courses']} courses'),
                  ],
                ),
              ),
              if (canManage)
                TextButton(
                  style: TextButton.styleFrom(foregroundColor: AppColors.red600),
                  onPressed: () async {
                    final reason = await promptReason(
                      context,
                      title: 'Remove instructor access',
                      actionLabel: 'Remove',
                      hint: 'Why? Their rooms, lessons and courses stay in place.',
                    );
                    if (reason == null || reason.length < 3) return;
                    try {
                      final msg = await _repo.revokeInstructor((u['id'] as num).toInt(), reason);
                      if (context.mounted) _toast(context, msg);
                      reload();
                    } on ApiException catch (e) {
                      if (context.mounted) _toast(context, _err(e));
                    }
                  },
                  child: const Text('Remove'),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _CandidatePicker extends StatefulWidget {
  const _CandidatePicker({required this.repo});
  final LearningExtrasRepository repo;

  @override
  State<_CandidatePicker> createState() => _CandidatePickerState();
}

class _CandidatePickerState extends State<_CandidatePicker> {
  final _q = TextEditingController();
  List<LearningRow> _results = const [];
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _q.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    if (_q.text.trim().length < 2) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final r = await widget.repo.candidates(_q.text.trim());
      if (mounted) setState(() => _results = r);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = _err(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
            left: 20, right: 20, bottom: MediaQuery.viewInsetsOf(context).bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Find the member',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            TextField(
              controller: _q,
              autofocus: true,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _search(),
              decoration: InputDecoration(
                hintText: 'Name or email (2+ letters)',
                suffixIcon: IconButton(icon: const Icon(Icons.search), onPressed: _search),
              ),
            ),
            if (_busy) const LinearProgressIndicator(),
            if (_error != null) _Sub(_error!, color: AppColors.red700),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 320),
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final u in _results)
                    ListTile(
                      title: Text('${u['name']}'),
                      subtitle: Text('${u['email']}'),
                      onTap: () => Navigator.pop(context, u),
                    ),
                  if (!_busy && _results.isEmpty && _q.text.trim().length >= 2)
                    const Padding(
                      padding: EdgeInsets.all(12),
                      child: _Sub('No active member found (instructors are not listed).'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
