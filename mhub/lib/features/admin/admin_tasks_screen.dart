import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_tasks_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

const _taskStatuses = {
  'todo': 'To do',
  'in_progress': 'In progress',
  'done': 'Done',
  'cancelled': 'Cancelled',
};

const _taskPriorities = {
  'low': 'Low',
  'normal': 'Normal',
  'high': 'High',
  'urgent': 'Urgent',
};

MbuiAppearance _priorityAppearance(String p) => switch (p) {
      'urgent' => MbuiAppearance.danger,
      'high' => MbuiAppearance.warning,
      'low' => MbuiAppearance.neutral,
      _ => MbuiAppearance.info,
    };

MbuiAppearance _statusAppearance(String s) => switch (s) {
      'done' => MbuiAppearance.success,
      'in_progress' => MbuiAppearance.info,
      'cancelled' => MbuiAppearance.danger,
      _ => MbuiAppearance.warning,
    };

String _due(String? iso) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso);
  return d == null ? iso : DateFormat('d MMM y').format(d);
}

void _snack(BuildContext context, String msg) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));

// ============================================================== list

class AdminTasksScreen extends ConsumerStatefulWidget {
  const AdminTasksScreen({super.key});

  @override
  ConsumerState<AdminTasksScreen> createState() => _AdminTasksScreenState();
}

class _AdminTasksScreenState extends ConsumerState<AdminTasksScreen> {
  final _search = TextEditingController();
  final List<AdminTaskRow> _more = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _search.text = ref.read(adminTaskSearchProvider);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final next = await ref.read(adminTasksRepositoryProvider).tasks(
            view: ref.read(adminTaskViewProvider),
            status: ref.read(adminTaskStatusProvider),
            q: ref.read(adminTaskSearchProvider),
            page: _page + 1,
          );
      if (!mounted) return;
      setState(() {
        _more.addAll(next.items);
        _page = next.currentPage;
        _lastPage = next.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    ref.invalidate(adminTasksProvider);
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<AdminTaskPage>>(adminTasksProvider, (_, next) {
      final p = next.valueOrNull;
      if (p == null) return;
      setState(() {
        _more.clear();
        _page = p.currentPage;
        _lastPage = p.lastPage;
      });
    });

    final async = ref.watch(adminTasksProvider);
    final view = ref.watch(adminTaskViewProvider);
    final status = ref.watch(adminTaskStatusProvider);
    final search = ref.watch(adminTaskSearchProvider);
    final page = async.valueOrNull;
    final counts = page?.counts ?? const <String, int?>{};

    String viewLabel(String key, String label) =>
        counts[key] == null ? label : '$label ${counts[key]}';

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Team tasks')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _open(const AdminTaskFormScreen()),
        icon: const Icon(Icons.add),
        label: const Text('Give a task'),
      ),
      body: Column(
        children: [
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              children: [
                for (final e in {
                  'mine': 'Given to me',
                  'given': 'I gave',
                  if (page?.canSeeAll ?? false) 'all': 'Everyone',
                }.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(viewLabel(e.key, e.value)),
                      selected: view == e.key,
                      onSelected: (_) =>
                          ref.read(adminTaskViewProvider.notifier).state = e.key,
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (v) =>
                  ref.read(adminTaskSearchProvider.notifier).state = v.trim(),
              decoration: InputDecoration(
                isDense: true,
                hintText: 'Search task titles',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: search.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close, size: 18),
                        tooltip: 'Clear search',
                        onPressed: () {
                          _search.clear();
                          ref.read(adminTaskSearchProvider.notifier).state = '';
                        },
                      ),
              ),
            ),
          ),
          AdminFilterBar(
            options: const {
              'open': 'Open',
              'done': 'Done',
              'cancelled': 'Cancelled',
              'any': 'All',
            },
            selected: status,
            onSelected: (v) =>
                ref.read(adminTaskStatusProvider.notifier).state = v ?? 'open',
          ),
          Expanded(
            child: AsyncValueView<AdminTaskPage>(
              value: async,
              onRefresh: () async => ref.refresh(adminTasksProvider.future),
              data: (p) {
                final list = [...p.items, ..._more];
                if (list.isEmpty) {
                  return ListView(children: const [
                    SizedBox(height: 120),
                    Center(
                      child: Text('No tasks here.',
                          style: TextStyle(color: AppColors.gray500)),
                    ),
                  ]);
                }
                final hasMore = _page < _lastPage;
                return ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                  itemCount: list.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, i) {
                    if (i == list.length) {
                      return MbuiButton(
                        label: 'Load more',
                        variant: MbuiVariant.secondary,
                        fullWidth: true,
                        loading: _loadingMore,
                        onPressed: _loadMore,
                      );
                    }
                    final t = list[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        padding: const EdgeInsets.all(14),
                        onTap: () => _open(AdminTaskDetailScreen(taskId: t.id)),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(t.title,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                MbuiBadge(t.statusLabel,
                                    appearance: _statusAppearance(t.status)),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Wrap(
                              spacing: 6,
                              runSpacing: 4,
                              children: [
                                MbuiBadge(t.priorityLabel,
                                    appearance: _priorityAppearance(t.priority)),
                                if (t.dueAt != null)
                                  MbuiBadge(
                                    '${t.overdue ? 'Overdue · ' : 'Due '}${_due(t.dueAt)}',
                                    appearance: t.overdue
                                        ? MbuiAppearance.danger
                                        : MbuiAppearance.neutral,
                                  ),
                                if (t.commentsCount > 0)
                                  MbuiBadge('${t.commentsCount} comments'),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              view == 'given'
                                  ? 'To ${t.assigneeName ?? 'nobody yet'}'
                                  : 'From ${t.creatorName ?? '—'}${view == 'all' ? ' to ${t.assigneeName ?? 'nobody yet'}' : ''}',
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================== detail

class AdminTaskDetailScreen extends ConsumerStatefulWidget {
  const AdminTaskDetailScreen({super.key, required this.taskId});
  final int taskId;

  @override
  ConsumerState<AdminTaskDetailScreen> createState() =>
      _AdminTaskDetailScreenState();
}

class _AdminTaskDetailScreenState extends ConsumerState<AdminTaskDetailScreen> {
  final _comment = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  AdminTasksRepository get _repo => ref.read(adminTasksRepositoryProvider);

  void _refresh() => ref.invalidate(adminTaskProvider(widget.taskId));

  Future<void> _setStatus(String status) async {
    try {
      await _repo.setStatus(widget.taskId, status);
      _refresh();
      if (mounted) _snack(context, 'Task marked as ${_taskStatuses[status]!.toLowerCase()}.');
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    }
  }

  Future<void> _send() async {
    final body = _comment.text.trim();
    if (body.isEmpty) return;
    setState(() => _sending = true);
    try {
      await _repo.comment(widget.taskId, body);
      _comment.clear();
      _refresh();
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _delete() async {
    final ok = await confirmAdminAction(
      context,
      title: 'Delete task',
      message: 'Delete this task and its comments? This cannot be undone.',
      actionLabel: 'Delete',
      variant: MbuiVariant.danger,
    );
    if (!ok) return;
    try {
      await _repo.delete(widget.taskId);
      if (!mounted) return;
      _snack(context, 'Task deleted.');
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminTaskProvider(widget.taskId));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Task'),
        actions: [
          if (async.valueOrNull?.canEdit ?? false) ...[
            IconButton(
              tooltip: 'Edit task',
              icon: const Icon(Icons.edit_outlined),
              onPressed: () async {
                await Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => AdminTaskFormScreen(task: async.value),
                ));
                _refresh();
              },
            ),
            IconButton(
              tooltip: 'Delete task',
              icon: const Icon(Icons.delete_outline),
              onPressed: _delete,
            ),
          ],
        ],
      ),
      body: AsyncValueView<AdminTaskRow>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminTaskProvider(widget.taskId).future),
        data: (t) => Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  MbuiTitle(t.title),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      MbuiBadge(t.statusLabel,
                          appearance: _statusAppearance(t.status)),
                      MbuiBadge(t.priorityLabel,
                          appearance: _priorityAppearance(t.priority)),
                      if (t.overdue)
                        const MbuiBadge('Overdue',
                            appearance: MbuiAppearance.danger),
                    ],
                  ),
                  const SizedBox(height: 12),
                  MbuiCard(
                    child: Column(
                      children: [
                        AdminRow(
                            'Given to',
                            t.assigneeName == null
                                ? 'Nobody yet'
                                : '${t.assigneeName}${t.assigneeJobTitle != null ? ' (${t.assigneeJobTitle})' : ''}'),
                        AdminRow('Given by', t.creatorName ?? '—'),
                        AdminRow('Due', t.dueAt == null ? 'No due date' : _due(t.dueAt)),
                        if (t.updatedAgo != null)
                          AdminRow('Last change', t.updatedAgo!),
                      ],
                    ),
                  ),
                  if ((t.description ?? '').isNotEmpty) ...[
                    const SizedBox(height: 12),
                    MbuiCard(
                      child: SelectableText(t.description!,
                          style: const TextStyle(
                              fontSize: 14, color: AppColors.gray700)),
                    ),
                  ],
                  if (t.canMove) ...[
                    const SizedBox(height: 12),
                    const MbuiSectionLabel('Move it along'),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final e in _taskStatuses.entries)
                          ChoiceChip(
                            label: Text(e.value),
                            selected: t.status == e.key,
                            onSelected: (_) => _setStatus(e.key),
                          ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 16),
                  MbuiSectionLabel('Comments (${t.comments.length})'),
                  const SizedBox(height: 8),
                  if (t.comments.isEmpty)
                    const Text('No comments yet.',
                        style: TextStyle(fontSize: 12, color: AppColors.gray500)),
                  for (final c in t.comments)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: c.mine ? AppColors.indigo50 : Colors.white,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                          border: Border.all(color: AppColors.gray200),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${c.userName}${c.userJobTitle != null ? ' · ${c.userJobTitle}' : ''}${c.createdAgo != null ? ' · ${c.createdAgo}' : ''}',
                              style: const TextStyle(
                                  fontSize: 11, color: AppColors.gray500),
                            ),
                            const SizedBox(height: 4),
                            SelectableText(c.body,
                                style: const TextStyle(fontSize: 14)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 12, 8),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _comment,
                        minLines: 1,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          isDense: true,
                          hintText: 'Write a comment',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      tooltip: 'Send comment',
                      onPressed: _sending ? null : _send,
                      icon: _sending
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.send),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================== form

class AdminTaskFormScreen extends ConsumerStatefulWidget {
  const AdminTaskFormScreen({super.key, this.task});
  final AdminTaskRow? task;

  @override
  ConsumerState<AdminTaskFormScreen> createState() =>
      _AdminTaskFormScreenState();
}

class _AdminTaskFormScreenState extends ConsumerState<AdminTaskFormScreen> {
  late final _title = TextEditingController(text: widget.task?.title ?? '');
  late final _description =
      TextEditingController(text: widget.task?.description ?? '');
  late String _priority = widget.task?.priority ?? 'normal';
  late int? _assignee = widget.task?.assignedTo;
  late DateTime? _dueAt =
      widget.task?.dueAt == null ? null : DateTime.tryParse(widget.task!.dueAt!);
  bool _saving = false;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickDue() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dueAt ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 5),
    );
    if (picked != null) setState(() => _dueAt = picked);
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty) {
      _snack(context, 'Give the task a title.');
      return;
    }
    final body = {
      'title': _title.text.trim(),
      'description': _description.text.trim().isEmpty ? null : _description.text.trim(),
      'priority': _priority,
      'due_at': _dueAt == null ? null : DateFormat('yyyy-MM-dd').format(_dueAt!),
      'assigned_to': _assignee,
    };
    setState(() => _saving = true);
    final repo = ref.read(adminTasksRepositoryProvider);
    try {
      if (widget.task == null) {
        final id = await repo.create(body);
        if (!mounted) return;
        Navigator.of(context).pushReplacement(MaterialPageRoute(
          builder: (_) => AdminTaskDetailScreen(taskId: id),
        ));
        _snack(context, 'Task created.');
      } else {
        await repo.update(widget.task!.id, body);
        if (!mounted) return;
        Navigator.of(context).pop();
        _snack(context, 'Task updated.');
      }
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final people = ref.watch(adminTaskPeopleProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
          title: Text(widget.task == null ? 'Give a task' : 'Edit task')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _title,
            maxLength: 200,
            decoration: const InputDecoration(labelText: 'What needs doing?'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _description,
            minLines: 3,
            maxLines: 8,
            decoration: const InputDecoration(
                labelText: 'Details (optional)', alignLabelWithHint: true),
          ),
          const SizedBox(height: 16),
          people.when(
            data: (list) => DropdownButtonFormField<int?>(
              initialValue: list.any((p) => p.id == _assignee) ? _assignee : null,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Give it to'),
              items: [
                const DropdownMenuItem<int?>(
                    value: null, child: Text('Nobody yet')),
                for (final p in list)
                  DropdownMenuItem<int?>(value: p.id, child: Text(p.label)),
              ],
              onChanged: (v) => setState(() => _assignee = v),
            ),
            loading: () => const LinearProgressIndicator(),
            error: (e, _) => Text('Could not load the team: $e',
                style: const TextStyle(color: AppColors.red700)),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _priority,
            decoration: const InputDecoration(labelText: 'Priority'),
            items: [
              for (final e in _taskPriorities.entries)
                DropdownMenuItem(value: e.key, child: Text(e.value)),
            ],
            onChanged: (v) => setState(() => _priority = v ?? 'normal'),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.event_outlined, size: 18),
                  label: Text(_dueAt == null
                      ? 'No due date'
                      : 'Due ${DateFormat('d MMM y').format(_dueAt!)}'),
                  onPressed: _pickDue,
                ),
              ),
              if (_dueAt != null)
                IconButton(
                  tooltip: 'Remove due date',
                  icon: const Icon(Icons.close),
                  onPressed: () => setState(() => _dueAt = null),
                ),
            ],
          ),
          const SizedBox(height: 24),
          MbuiButton(
            label: widget.task == null ? 'Give task' : 'Save changes',
            fullWidth: true,
            loading: _saving,
            onPressed: _save,
          ),
        ],
      ),
    );
  }
}
