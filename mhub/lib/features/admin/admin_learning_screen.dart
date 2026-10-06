import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_learning_api.dart';
import '../../data/studio_extras_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../studio/studio_room_extras_screen.dart';
import 'admin_common.dart';

void _toast(BuildContext context, String text) {
  if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
}

String _error(ApiException e) => e.errors?.values.firstOrNull?.firstOrNull ?? e.message;

/// Learning management: courses, categories, enrolments, trash and the
/// platform guest-link switch (same as Admin → Learning on the web).
class AdminLearningScreen extends ConsumerWidget {
  const AdminLearningScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final overview = ref.watch(adminLearningOverviewProvider).valueOrNull;
    final tabs = ['Courses', 'Categories', if (overview?.canTrash ?? false) 'Trash'];

    return DefaultTabController(
      key: ValueKey(tabs.length),
      length: tabs.length,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Learning admin'),
          actions: [
            if (overview?.canManageRooms ?? false)
              PopupMenuButton<bool>(
                tooltip: 'Settings',
                icon: const Icon(Icons.tune),
                onSelected: (v) async {
                  try {
                    final message = await ref.read(adminLearningRepositoryProvider).setGuestLinks(v);
                    if (context.mounted) _toast(context, message);
                    ref.invalidate(adminLearningOverviewProvider);
                  } on ApiException catch (e) {
                    if (context.mounted) _toast(context, _error(e));
                  }
                },
                itemBuilder: (_) => [
                  CheckedPopupMenuItem(
                    value: !overview!.guestLinks,
                    checked: overview.guestLinks,
                    child: const Text('Guest links for live classes'),
                  ),
                ],
              ),
          ],
          bottom: TabBar(tabs: [for (final t in tabs) Tab(text: t)]),
        ),
        body: TabBarView(
          children: [
            const _CoursesTab(),
            const _CategoriesTab(),
            if (overview?.canTrash ?? false) const _TrashTab(),
          ],
        ),
      ),
    );
  }
}

// --- Courses ---------------------------------------------------------------

class _CoursesTab extends ConsumerStatefulWidget {
  const _CoursesTab();

  @override
  ConsumerState<_CoursesTab> createState() => _CoursesTabState();
}

class _CoursesTabState extends ConsumerState<_CoursesTab> {
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _open({int? id}) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => AdminCourseScreen(courseId: id)));
    ref.invalidate(adminCoursesProvider);
    ref.invalidate(adminLearningOverviewProvider);
  }

  @override
  Widget build(BuildContext context) {
    final canManage = ref.watch(adminLearningOverviewProvider).valueOrNull?.canManage ?? false;
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: canManage
          ? FloatingActionButton.extended(onPressed: () => _open(), icon: const Icon(Icons.add), label: const Text('Course'))
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 350), () => setState(() => _search = v.trim()));
              },
              decoration: const InputDecoration(hintText: 'Search courses', prefixIcon: Icon(Icons.search, size: 20)),
            ),
          ),
          Expanded(
            child: AsyncValueView<List<AdminCourse>>(
              value: ref.watch(adminCoursesProvider(_search)),
              onRefresh: () async => ref.refresh(adminCoursesProvider(_search).future),
              data: (courses) => ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                children: [
                  if (courses.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('No courses yet.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500)),
                    ),
                  for (final c in courses)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        padding: const EdgeInsets.all(14),
                        onTap: () => _open(id: c.id),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(child: Text(c.title, style: const TextStyle(fontWeight: FontWeight.w700))),
                                MbuiBadge(
                                  c.status == 'published' ? 'Published' : 'Draft',
                                  appearance: c.status == 'published' ? MbuiAppearance.success : MbuiAppearance.neutral,
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [?c.category, ?c.instructor, '${c.lessons} lessons', '${c.enrolments} enrolled'].join(' · '),
                              style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                            ),
                          ],
                        ),
                      ),
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

/// Create or edit a course, and manage who is enrolled.
class AdminCourseScreen extends ConsumerStatefulWidget {
  const AdminCourseScreen({super.key, this.courseId});
  final int? courseId;

  @override
  ConsumerState<AdminCourseScreen> createState() => _AdminCourseScreenState();
}

class _AdminCourseScreenState extends ConsumerState<AdminCourseScreen> {
  final _title = TextEditingController();
  final _summary = TextEditingController();
  final _description = TextEditingController();
  int? _categoryId;
  int? _instructorId;
  String? _level;
  String _access = 'open';
  String _status = 'draft';
  bool _notify = false;
  bool _loaded = false;
  bool _busy = false;

  AdminLearningRepository get _repo => ref.read(adminLearningRepositoryProvider);
  bool get _isNew => widget.courseId == null;

  @override
  void dispose() {
    _title.dispose();
    _summary.dispose();
    _description.dispose();
    super.dispose();
  }

  void _fill(AdminCourseDetail d) {
    if (_loaded) return;
    _loaded = true;
    _title.text = d.card.title;
    _summary.text = d.summary ?? '';
    _description.text = d.description ?? '';
    _categoryId = d.categoryId;
    _instructorId = d.instructorId;
    _level = d.level;
    _access = d.access;
    _status = d.card.status;
  }

  Future<void> _save() async {
    if (_categoryId == null || _title.text.trim().isEmpty) {
      _toast(context, 'Choose a category and enter a title.');
      return;
    }
    setState(() => _busy = true);
    try {
      final message = await _repo.saveCourse(widget.courseId, {
        'learning_category_id': _categoryId,
        'title': _title.text.trim(),
        'summary': _summary.text.trim(),
        'description': _description.text,
        'level': ?_level,
        'instructor_id': ?_instructorId,
        'access': _access,
        'status': _status,
        'notify': _notify,
      });
      if (!mounted) return;
      _toast(context, message);
      if (_isNew) {
        Navigator.of(context).pop();
      } else {
        ref.invalidate(adminCourseProvider(widget.courseId!));
      }
    } on ApiException catch (e) {
      if (mounted) _toast(context, _error(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    final reason = await promptReason(context, title: 'Move course to trash', actionLabel: 'Move to trash');
    if (reason == null || reason.isEmpty) return;
    try {
      final message = await _repo.deleteCourse(widget.courseId!, reason);
      if (!mounted) return;
      _toast(context, message);
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) _toast(context, _error(e));
    }
  }

  Future<void> _enrol() async {
    final picked = await showModalBottomSheet<MemberHit>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => MemberPickerSheet(repo: ref.read(studioExtrasRepositoryProvider)),
    );
    if (picked == null) return;
    try {
      final message = await _repo.enrol(widget.courseId!, [picked.id]);
      if (mounted) _toast(context, message);
      ref.invalidate(adminCourseProvider(widget.courseId!));
    } on ApiException catch (e) {
      if (mounted) _toast(context, _error(e));
    }
  }

  Future<void> _unenrol(AdminEnrolment e) async {
    final reason = await promptReason(context, title: 'Remove ${e.name}?', actionLabel: 'Remove');
    if (reason == null || reason.isEmpty) return;
    try {
      final message = await _repo.unenrol(widget.courseId!, e.id, reason);
      if (mounted) _toast(context, message);
      ref.invalidate(adminCourseProvider(widget.courseId!));
    } on ApiException catch (err) {
      if (mounted) _toast(context, _error(err));
    }
  }

  @override
  Widget build(BuildContext context) {
    final overview = ref.watch(adminLearningOverviewProvider).valueOrNull;
    final categories = ref.watch(adminLearningCategoriesProvider).valueOrNull ?? const [];
    final detail = _isNew ? null : ref.watch(adminCourseProvider(widget.courseId!));
    final d = detail?.valueOrNull;
    if (d != null) _fill(d);
    final canManage = overview?.canManage ?? false;

    if (!_isNew && d == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Course')),
        body: AsyncValueView<AdminCourseDetail>(value: detail!, data: (_) => const SizedBox.shrink()),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(_isNew ? 'New course' : 'Course'),
        actions: [
          if (!_isNew && canManage)
            IconButton(tooltip: 'Move to trash', icon: const Icon(Icons.delete_outline), onPressed: _delete),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(controller: _title, enabled: canManage, decoration: const InputDecoration(labelText: 'Title')),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: categories.any((c) => c.id == _categoryId) ? _categoryId : null,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: [for (final c in categories) DropdownMenuItem(value: c.id, child: Text(c.name))],
                  onChanged: canManage ? (v) => setState(() => _categoryId = v) : null,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int?>(
                  initialValue: (overview?.instructors ?? const []).any((u) => u.id == _instructorId) ? _instructorId : null,
                  decoration: const InputDecoration(labelText: 'Instructor'),
                  items: [
                    const DropdownMenuItem<int?>(value: null, child: Text('None')),
                    for (final u in overview?.instructors ?? const <({int id, String name})>[])
                      DropdownMenuItem<int?>(value: u.id, child: Text(u.name)),
                  ],
                  onChanged: canManage ? (v) => setState(() => _instructorId = v) : null,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String?>(
                  initialValue: _level,
                  decoration: const InputDecoration(labelText: 'Level'),
                  items: [
                    const DropdownMenuItem<String?>(value: null, child: Text('All levels')),
                    for (final e in (overview?.levels ?? const {}).entries)
                      DropdownMenuItem<String?>(value: e.key, child: Text(e.value)),
                  ],
                  onChanged: canManage ? (v) => setState(() => _level = v) : null,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _access,
                  decoration: const InputDecoration(labelText: 'Who can watch'),
                  items: [
                    for (final e in (overview?.access ?? const {'open': 'Open to all members'}).entries)
                      DropdownMenuItem(value: e.key, child: Text(e.value)),
                  ],
                  onChanged: canManage ? (v) => setState(() => _access = v ?? 'open') : null,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _summary,
                  enabled: canManage,
                  maxLines: 2,
                  decoration: const InputDecoration(labelText: 'Summary'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _description,
                  enabled: canManage,
                  minLines: 3,
                  maxLines: 8,
                  decoration: const InputDecoration(labelText: 'Description (Markdown)'),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Published'),
                  value: _status == 'published',
                  onChanged: canManage ? (v) => setState(() => _status = v ? 'published' : 'draft') : null,
                ),
                if (_status == 'published' && (_isNew || d?.card.status != 'published'))
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Tell members about the new course'),
                    value: _notify,
                    onChanged: (v) => setState(() => _notify = v ?? false),
                  ),
                if (canManage) ...[
                  const SizedBox(height: 8),
                  MbuiButton(label: _isNew ? 'Create course' : 'Save', loading: _busy, fullWidth: true, onPressed: _busy ? null : _save),
                ],
              ],
            ),
          ),
          if (d != null) ...[
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(child: MbuiSectionLabel('Enrolled (${d.enrolments.length})')),
                if (canManage) TextButton.icon(onPressed: _enrol, icon: const Icon(Icons.person_add_alt, size: 18), label: const Text('Enrol')),
              ],
            ),
            if (d.enrolments.isEmpty)
              const Text('Nobody is enrolled yet.', style: TextStyle(color: AppColors.gray500)),
            for (final e in d.enrolments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(e.name),
                subtitle: Text([
                  ?e.email,
                  if (e.source != null) e.source!,
                  if (e.enrolledAt != null) DateFormat('d MMM yyyy').format(e.enrolledAt!),
                ].join(' · ')),
                trailing: canManage
                    ? IconButton(icon: const Icon(Icons.close), tooltip: 'Remove', onPressed: () => _unenrol(e))
                    : null,
              ),
          ],
        ],
      ),
    );
  }
}

// --- Categories --------------------------------------------------------------

class _CategoriesTab extends ConsumerWidget {
  const _CategoriesTab();

  Future<void> _edit(BuildContext context, WidgetRef ref, [AdminLearningCategory? c]) async {
    final name = TextEditingController(text: c?.name);
    final description = TextEditingController(text: c?.description);
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(ctx).bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(c == null ? 'New category' : 'Edit category', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            TextField(controller: name, autofocus: true, decoration: const InputDecoration(labelText: 'Name')),
            const SizedBox(height: 10),
            TextField(controller: description, maxLines: 2, decoration: const InputDecoration(labelText: 'Description')),
            const SizedBox(height: 16),
            MbuiButton(label: 'Save', fullWidth: true, onPressed: () => Navigator.pop(ctx, true)),
          ],
        ),
      ),
    );
    if (ok != true || name.text.trim().isEmpty) return;
    try {
      final message = await ref.read(adminLearningRepositoryProvider).saveCategory(
            id: c?.id,
            name: name.text.trim(),
            description: description.text.trim().isEmpty ? null : description.text.trim(),
            position: c?.position,
          );
      if (context.mounted) _toast(context, message);
      ref.invalidate(adminLearningCategoriesProvider);
    } on ApiException catch (e) {
      if (context.mounted) _toast(context, _error(e));
    }
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, AdminLearningCategory c) async {
    final reason = await promptReason(context, title: 'Move "${c.name}" to trash', actionLabel: 'Move to trash');
    if (reason == null || reason.isEmpty) return;
    try {
      final message = await ref.read(adminLearningRepositoryProvider).deleteCategory(c.id, reason);
      if (context.mounted) _toast(context, message);
      ref.invalidate(adminLearningCategoriesProvider);
    } on ApiException catch (e) {
      if (context.mounted) _toast(context, _error(e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canManage = ref.watch(adminLearningOverviewProvider).valueOrNull?.canManage ?? false;
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: canManage
          ? FloatingActionButton.extended(onPressed: () => _edit(context, ref), icon: const Icon(Icons.add), label: const Text('Category'))
          : null,
      body: AsyncValueView<List<AdminLearningCategory>>(
        value: ref.watch(adminLearningCategoriesProvider),
        onRefresh: () async => ref.refresh(adminLearningCategoriesProvider.future),
        data: (rows) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            if (rows.isEmpty) const Text('No categories yet.', style: TextStyle(color: AppColors.gray500)),
            for (final c in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.fromLTRB(14, 4, 4, 4),
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(c.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text('${c.courses} courses · ${c.lessons} lessons · ${c.rooms} classes'),
                    onTap: canManage ? () => _edit(context, ref, c) : null,
                    trailing: canManage
                        ? IconButton(
                            icon: const Icon(Icons.delete_outline, color: AppColors.red600),
                            onPressed: () => _delete(context, ref, c),
                          )
                        : null,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// --- Trash -------------------------------------------------------------------

class _TrashTab extends ConsumerStatefulWidget {
  const _TrashTab();

  @override
  ConsumerState<_TrashTab> createState() => _TrashTabState();
}

class _TrashTabState extends ConsumerState<_TrashTab> {
  String? _type;

  Future<void> _act(Future<String> Function() action) async {
    try {
      final message = await action();
      if (mounted) _toast(context, message);
      ref.invalidate(adminLearningTrashProvider);
      ref.invalidate(adminCoursesProvider);
      ref.invalidate(adminLearningCategoriesProvider);
    } on ApiException catch (e) {
      if (mounted) _toast(context, _error(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = ref.read(adminLearningRepositoryProvider);
    return AsyncValueView<TrashPage>(
      value: ref.watch(adminLearningTrashProvider(_type)),
      onRefresh: () async => ref.refresh(adminLearningTrashProvider(_type).future),
      data: (page) => Column(
        children: [
          AdminFilterBar(
            options: {for (final e in page.types.entries) e.key: '${e.value.label} (${e.value.count})'},
            selected: page.type,
            onSelected: (v) => setState(() => _type = v),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text('Items are deleted for good after ${page.retentionDays} days.',
                style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (page.items.isEmpty) const Text('The trash is empty.', style: TextStyle(color: AppColors.gray500)),
                for (final item in page.items)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(item.label),
                    subtitle: item.deletedAt == null ? null : Text('Deleted ${DateFormat('d MMM yyyy').format(item.deletedAt!)}'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          tooltip: 'Restore',
                          icon: const Icon(Icons.restore, color: AppColors.emerald600),
                          onPressed: () => _act(() => repo.restore(page.type, item.id)),
                        ),
                        IconButton(
                          tooltip: 'Delete for good',
                          icon: const Icon(Icons.delete_forever, color: AppColors.red600),
                          onPressed: () async {
                            final reason = await promptReason(context, title: 'Delete "${item.label}" for good?', actionLabel: 'Delete');
                            if (reason == null || reason.isEmpty) return;
                            await _act(() => repo.purge(page.type, item.id, reason));
                          },
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
