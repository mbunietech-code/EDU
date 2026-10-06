import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/studio_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../learning/live_class_screen.dart';
import 'studio_room_extras_screen.dart';

void _toast(BuildContext context, String text) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

String _errorText(Object e) {
  if (e is! ApiException) return 'Something went wrong. Try again.';
  final all = e.errors?.values.expand((v) => v).toList() ?? const <String>[];
  return all.isNotEmpty ? all.first : e.message;
}

/// Teaching Studio: schedule live classes and publish video lessons.
class StudioScreen extends ConsumerWidget {
  const StudioScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(studioHomeProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Teaching Studio'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Classes'),
              Tab(text: 'Lessons'),
            ],
          ),
        ),
        body: AsyncValueView<StudioHome>(
          value: async,
          onRefresh: () async => ref.refresh(studioHomeProvider.future),
          data: (h) => TabBarView(
            children: [
              _ClassesTab(home: h),
              _LessonsTab(home: h),
            ],
          ),
        ),
      ),
    );
  }
}

// --- Classes -----------------------------------------------------------------

class _ClassesTab extends ConsumerWidget {
  const _ClassesTab({required this.home});
  final StudioHome home;

  Future<void> _act(
    BuildContext context,
    WidgetRef ref,
    Future<void> Function() action, [
    String? done,
  ]) async {
    try {
      await action();
      ref.invalidate(studioHomeProvider);
      if (done != null && context.mounted) _toast(context, done);
    } catch (e) {
      if (context.mounted) _toast(context, _errorText(e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.read(studioRepositoryProvider);

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: home.canHostRooms
          ? FloatingActionButton.extended(
              heroTag: 'class',
              icon: const Icon(Icons.add),
              label: const Text('New class'),
              onPressed: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => RoomFormScreen(home: home)),
                );
                ref.invalidate(studioHomeProvider);
              },
            )
          : null,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
        children: [
          if (home.rooms.isEmpty)
            const MbuiCard(
              child: Text('No classes yet. Tap New class to schedule one.'),
            ),
          for (final r in home.rooms)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            r.title,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                        MbuiBadge(
                          r.status == 'live' ? 'Live' : r.statusLabel,
                          appearance: switch (r.status) {
                            'live' => MbuiAppearance.danger,
                            'scheduled' => MbuiAppearance.info,
                            'completed' => MbuiAppearance.success,
                            _ => MbuiAppearance.neutral,
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        if (r.scheduledAt != null)
                          DateFormat('EEE d MMM, HH:mm').format(r.scheduledAt!),
                        '${r.durationMinutes} min',
                        ?r.course,
                        ?r.host,
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.gray500,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: [
                        if (r.status == 'live')
                          FilledButton.icon(
                            icon: const Icon(Icons.sensors, size: 18),
                            label: const Text('Join as host'),
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => LiveClassScreen(
                                  slug: r.slug,
                                  title: r.title,
                                ),
                              ),
                            ),
                          )
                        else if (r.canStart && r.status == 'scheduled')
                          FilledButton.icon(
                            icon: const Icon(Icons.play_arrow, size: 18),
                            label: const Text('Go live'),
                            onPressed: () => _act(context, ref, () async {
                              await repo.startRoom(r.slug);
                              if (context.mounted) {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => LiveClassScreen(
                                      slug: r.slug,
                                      title: r.title,
                                    ),
                                  ),
                                );
                              }
                            }),
                          ),
                        if (r.status == 'live' && r.canStart)
                          OutlinedButton(
                            onPressed: () => _act(
                              context,
                              ref,
                              () => repo.endRoom(r.slug),
                              'Class ended.',
                            ),
                            child: const Text('End class'),
                          ),
                        if (r.canEdit &&
                            r.status != 'live' &&
                            r.status != 'completed')
                          OutlinedButton(
                            onPressed: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) =>
                                      RoomFormScreen(home: home, roomId: r.id),
                                ),
                              );
                              ref.invalidate(studioHomeProvider);
                            },
                            child: const Text('Edit'),
                          ),
                        OutlinedButton.icon(
                          icon: const Icon(Icons.folder_open_outlined, size: 18),
                          label: const Text('Recordings & more'),
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => StudioRoomExtrasScreen(
                                roomId: r.id,
                                title: r.title,
                              ),
                            ),
                          ),
                        ),
                        if (r.canCancel && r.status == 'scheduled')
                          TextButton(
                            onPressed: () => _act(
                              context,
                              ref,
                              () => repo.cancelRoom(r.id, null),
                              'Class cancelled.',
                            ),
                            child: const Text(
                              'Cancel',
                              style: TextStyle(color: AppColors.red600),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class RoomFormScreen extends ConsumerStatefulWidget {
  const RoomFormScreen({super.key, required this.home, this.roomId});
  final StudioHome home;
  final int? roomId;

  @override
  ConsumerState<RoomFormScreen> createState() => _RoomFormScreenState();
}

class _RoomFormScreenState extends ConsumerState<RoomFormScreen> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _duration = TextEditingController(text: '60');
  int? _category;
  int? _course;
  String _access = 'public';
  DateTime? _date;
  TimeOfDay? _time;
  bool _chat = true;
  bool _questions = true;
  bool _participantMedia = true;
  bool _loading = false;
  bool _busy = false;
  String? _error;
  String _status = 'draft';

  @override
  void initState() {
    super.initState();
    if (widget.roomId != null) _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final r =
          (await ref.read(studioRepositoryProvider).room(widget.roomId!)).raw;
      _title.text = r['title'] as String? ?? '';
      _description.text = r['description'] as String? ?? '';
      _duration.text = '${r['duration_minutes'] ?? 60}';
      _category = (r['learning_category_id'] as num?)?.toInt();
      _course = (r['learning_course_id'] as num?)?.toInt();
      _access = r['access'] as String? ?? 'public';
      _status = r['status'] as String? ?? 'draft';
      if (r['scheduled_date'] != null) {
        _date = DateTime.tryParse(r['scheduled_date'] as String);
      }
      if (r['scheduled_time'] != null) {
        final p = (r['scheduled_time'] as String).split(':');
        _time = TimeOfDay(hour: int.parse(p[0]), minute: int.parse(p[1]));
      }
      _chat = r['chat_enabled'] as bool? ?? true;
      _questions = r['questions_enabled'] as bool? ?? true;
      _participantMedia = r['allow_participant_media'] as bool? ?? true;
    } catch (e) {
      _error = _errorText(e);
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _save(String action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await ref.read(studioRepositoryProvider).saveRoom(
        widget.roomId,
        {
          'title': _title.text.trim(),
          'description': _description.text.trim(),
          'learning_category_id': _category,
          'learning_course_id': _course,
          'access': _access,
          'duration_minutes': int.tryParse(_duration.text.trim()) ?? 60,
          if (_date != null)
            'scheduled_date': DateFormat('yyyy-MM-dd').format(_date!),
          if (_time != null)
            'scheduled_time':
                '${_time!.hour.toString().padLeft(2, '0')}:${_time!.minute.toString().padLeft(2, '0')}',
          'chat_enabled': _chat,
          'questions_enabled': _questions,
          'allow_participant_media': _participantMedia,
          'action': action,
          'notify': action == 'schedule',
        },
      );
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop();
      }
    } catch (e) {
      setState(() => _error = _errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final h = widget.home;
    final courses = h.courses
        .where((c) => _category == null || c.parent == _category)
        .toList();

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.roomId == null ? 'New class' : 'Edit class'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  controller: _title,
                  decoration: const InputDecoration(labelText: 'Title'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _description,
                  minLines: 2,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _category,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('None'),
                    ),
                    for (final c in h.categories)
                      DropdownMenuItem(
                        value: c.value as int,
                        child: Text(c.label),
                      ),
                  ],
                  onChanged: (v) => setState(() {
                    _category = v;
                    if (_course != null &&
                        !h.courses.any(
                          (c) =>
                              c.value == _course &&
                              (v == null || c.parent == v),
                        )) {
                      _course = null;
                    }
                  }),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: courses.any((c) => c.value == _course)
                      ? _course
                      : null,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Course (optional)',
                  ),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('None'),
                    ),
                    for (final c in courses)
                      DropdownMenuItem(
                        value: c.value as int,
                        child: Text(c.label),
                      ),
                  ],
                  onChanged: (v) => setState(() => _course = v),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: h.roomAccess.any((a) => a.value == _access)
                      ? _access
                      : 'public',
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Who can join'),
                  items: [
                    for (final a in h.roomAccess)
                      DropdownMenuItem(
                        value: a.value as String,
                        child: Text(a.label),
                      ),
                  ],
                  onChanged: (v) => setState(() => _access = v ?? 'public'),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.event),
                        label: Text(
                          _date == null
                              ? 'Pick date'
                              : DateFormat('d MMM yyyy').format(_date!),
                        ),
                        onPressed: () async {
                          final d = await showDatePicker(
                            context: context,
                            initialDate: _date ?? DateTime.now(),
                            firstDate: DateTime.now().subtract(
                              const Duration(days: 1),
                            ),
                            lastDate: DateTime.now().add(
                              const Duration(days: 365),
                            ),
                          );
                          if (d != null) setState(() => _date = d);
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.schedule),
                        label: Text(
                          _time == null ? 'Pick time' : _time!.format(context),
                        ),
                        onPressed: () async {
                          final t = await showTimePicker(
                            context: context,
                            initialTime:
                                _time ?? const TimeOfDay(hour: 10, minute: 0),
                          );
                          if (t != null) setState(() => _time = t);
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _duration,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText:
                        'Duration (minutes, ${h.minDuration}–${h.maxDuration})',
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Chat'),
                  value: _chat,
                  onChanged: (v) => setState(() => _chat = v),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Questions'),
                  value: _questions,
                  onChanged: (v) => setState(() => _questions = v),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Learners may use mic / camera'),
                  value: _participantMedia,
                  onChanged: (v) => setState(() => _participantMedia = v),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: AppColors.red600),
                    ),
                  ),
                const SizedBox(height: 8),
                if (_status == 'draft' || _status == 'cancelled') ...[
                  MbuiButton(
                    label: 'Schedule & notify learners',
                    fullWidth: true,
                    loading: _busy,
                    onPressed: () => _save('schedule'),
                  ),
                  const SizedBox(height: 8),
                  MbuiButton(
                    label: 'Save as draft',
                    variant: MbuiVariant.secondary,
                    fullWidth: true,
                    onPressed: _busy
                        ? null
                        : () => _save(widget.roomId == null ? 'draft' : 'save'),
                  ),
                ] else
                  MbuiButton(
                    label: 'Save changes',
                    fullWidth: true,
                    loading: _busy,
                    onPressed: () => _save('save'),
                  ),
              ],
            ),
    );
  }
}

// --- Lessons -----------------------------------------------------------------

class _LessonsTab extends ConsumerWidget {
  const _LessonsTab({required this.home});
  final StudioHome home;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Future<void> open({int? id}) async {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => LessonFormScreen(home: home, videoId: id),
        ),
      );
      ref.invalidate(studioHomeProvider);
    }

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: home.canUploadLessons
          ? FloatingActionButton.extended(
              heroTag: 'lesson',
              icon: const Icon(Icons.video_call),
              label: const Text('Upload lesson'),
              onPressed: () => open(),
            )
          : null,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
        children: [
          if (home.videos.isEmpty)
            const MbuiCard(
              child: Text('No lessons yet. Tap Upload lesson to add a video.'),
            ),
          for (final v in home.videos)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(12),
                onTap: () => open(id: v.id),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      child: v.thumbnailUrl == null
                          ? Container(
                              width: 80,
                              height: 48,
                              color: AppColors.indigo50,
                              child: const Icon(
                                Icons.movie,
                                color: AppColors.indigo600,
                              ),
                            )
                          : Image.network(
                              v.thumbnailUrl!,
                              width: 80,
                              height: 48,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => Container(
                                width: 80,
                                height: 48,
                                color: AppColors.indigo50,
                              ),
                            ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            v.title,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          Text(
                            [
                              ?v.durationLabel,
                              ?(v.course ?? v.category),
                              if (!v.hasFile) 'no video yet',
                            ].join(' · '),
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.gray500,
                            ),
                          ),
                        ],
                      ),
                    ),
                    MbuiBadge(
                      v.status == 'published' ? 'Published' : 'Draft',
                      appearance: v.status == 'published'
                          ? MbuiAppearance.success
                          : MbuiAppearance.neutral,
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class LessonFormScreen extends ConsumerStatefulWidget {
  const LessonFormScreen({super.key, required this.home, this.videoId});
  final StudioHome home;
  final int? videoId;

  @override
  ConsumerState<LessonFormScreen> createState() => _LessonFormScreenState();
}

class _LessonFormScreenState extends ConsumerState<LessonFormScreen> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _tags = TextEditingController();
  int? _category;
  int? _course;
  String _visibility = 'course';
  File? _file;
  double? _progress;
  bool _busy = false;
  bool _loading = false;
  String? _error;
  String _status = 'draft';
  bool _canPublish = false;

  StudioRepository get _repo => ref.read(studioRepositoryProvider);

  @override
  void initState() {
    super.initState();
    if (widget.videoId != null) _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final v = await _repo.video(widget.videoId!);
      _title.text = v.title;
      _description.text = v.raw['description'] as String? ?? '';
      _tags.text = v.raw['tags'] as String? ?? '';
      _category = (v.raw['learning_category_id'] as num?)?.toInt();
      _course = (v.raw['learning_course_id'] as num?)?.toInt();
      _visibility = v.visibility;
      _status = v.status;
      _canPublish = v.canPublish;
    } catch (e) {
      _error = _errorText(e);
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _pick() async {
    final picked = await FilePicker.pickFiles(type: FileType.video);
    final path = picked?.files.single.path;
    if (path != null) setState(() => _file = File(path));
  }

  Future<void> _save({bool publish = false}) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      String? token;
      if (widget.videoId == null && _file != null) {
        token = await _repo.uploadVideo(
          _file!,
          onProgress: (p) => setState(() => _progress = p),
        );
      }
      final message = await _repo.saveVideo(widget.videoId, {
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'tags': _tags.text.trim(),
        'learning_category_id': _category,
        'learning_course_id': _course,
        'visibility': _visibility,
        'upload_token': ?token,
        if (widget.videoId == null) 'action': publish ? 'publish' : 'draft',
        if (widget.videoId == null) 'notify': publish,
      });
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop();
      }
    } catch (e) {
      setState(() => _error = _errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _togglePublish() async {
    setState(() => _busy = true);
    try {
      final message = await _repo.setPublished(
        widget.videoId!,
        _status != 'published',
      );
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop();
      }
    } catch (e) {
      setState(() => _error = _errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final h = widget.home;
    final courses = h.courses
        .where((c) => _category == null || c.parent == _category)
        .toList();
    final creating = widget.videoId == null;

    return Scaffold(
      appBar: AppBar(title: Text(creating ? 'Upload lesson' : 'Edit lesson')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (creating) ...[
                  MbuiCard(
                    onTap: _busy ? null : _pick,
                    child: Row(
                      children: [
                        const Icon(
                          Icons.video_file_outlined,
                          color: AppColors.indigo600,
                          size: 32,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _file == null
                                ? 'Choose the video file'
                                : _file!.uri.pathSegments.last,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_progress != null) ...[
                    const SizedBox(height: 8),
                    LinearProgressIndicator(value: _progress),
                    Text(
                      'Uploading ${((_progress ?? 0) * 100).round()}%',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.gray500,
                      ),
                    ),
                  ],
                  const SizedBox(height: 16),
                ],
                TextField(
                  controller: _title,
                  decoration: const InputDecoration(labelText: 'Title'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _description,
                  minLines: 2,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _category,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('Choose category'),
                    ),
                    for (final c in h.categories)
                      DropdownMenuItem(
                        value: c.value as int,
                        child: Text(c.label),
                      ),
                  ],
                  onChanged: (v) => setState(() {
                    _category = v;
                    _course = null;
                  }),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: courses.any((c) => c.value == _course)
                      ? _course
                      : null,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Course (optional)',
                  ),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('Standalone lesson'),
                    ),
                    for (final c in courses)
                      DropdownMenuItem(
                        value: c.value as int,
                        child: Text(c.label),
                      ),
                  ],
                  onChanged: (v) => setState(() => _course = v),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue:
                      h.videoVisibility.any((o) => o.value == _visibility)
                      ? _visibility
                      : 'course',
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Who can watch'),
                  items: [
                    for (final o in h.videoVisibility)
                      DropdownMenuItem(
                        value: o.value as String,
                        child: Text(o.label),
                      ),
                  ],
                  onChanged: (v) => setState(() => _visibility = v ?? 'course'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _tags,
                  decoration: const InputDecoration(
                    labelText: 'Tags (comma separated)',
                  ),
                ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: AppColors.red600),
                    ),
                  ),
                const SizedBox(height: 12),
                if (creating) ...[
                  MbuiButton(
                    label: 'Upload & publish',
                    fullWidth: true,
                    loading: _busy,
                    onPressed: () => _save(publish: true),
                  ),
                  const SizedBox(height: 8),
                  MbuiButton(
                    label: 'Upload as draft',
                    variant: MbuiVariant.secondary,
                    fullWidth: true,
                    onPressed: _busy ? null : () => _save(),
                  ),
                ] else ...[
                  MbuiButton(
                    label: 'Save changes',
                    fullWidth: true,
                    loading: _busy,
                    onPressed: () => _save(),
                  ),
                  if (_canPublish) ...[
                    const SizedBox(height: 8),
                    MbuiButton(
                      label: _status == 'published'
                          ? 'Move back to drafts'
                          : 'Publish lesson',
                      variant: _status == 'published'
                          ? MbuiVariant.secondary
                          : MbuiVariant.success,
                      fullWidth: true,
                      onPressed: _busy ? null : _togglePublish,
                    ),
                  ],
                ],
              ],
            ),
    );
  }
}
