import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_research_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

const _tabs = {
  'review': 'To review',
  'published': 'Published',
  'drafts': 'Drafts',
  'archived': 'Archived',
  'all': 'All',
};

MbuiAppearance _appearance(String status) => switch (status) {
      'published' || 'approved' => MbuiAppearance.success,
      'submitted' || 'under_review' => MbuiAppearance.info,
      'changes_requested' => MbuiAppearance.warning,
      'archived' => MbuiAppearance.danger,
      _ => MbuiAppearance.neutral,
    };

/// Research review queue: open a paper, read it, then publish, ask for
/// changes or reject (same workflow and author emails as the web).
class AdminResearchScreen extends ConsumerStatefulWidget {
  const AdminResearchScreen({super.key});

  @override
  ConsumerState<AdminResearchScreen> createState() => _AdminResearchScreenState();
}

class _AdminResearchScreenState extends ConsumerState<AdminResearchScreen> {
  String _status = 'review';
  String _search = '';
  final _searchCtl = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtl.dispose();
    super.dispose();
  }

  ({String status, String search}) get _filter => (status: _status, search: _search);

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminResearchQueueProvider(_filter));
    final reviewCount = async.valueOrNull?.reviewCount ?? 0;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Research review'),
        actions: [
          IconButton(
            tooltip: 'Categories',
            icon: const Icon(Icons.category_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AdminResearchCategoriesScreen()),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchCtl,
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 350), () => setState(() => _search = v.trim()));
              },
              decoration: const InputDecoration(
                hintText: 'Search title or author',
                prefixIcon: Icon(Icons.search, size: 20),
              ),
            ),
          ),
          AdminFilterBar(
            options: {
              for (final e in _tabs.entries)
                e.key: e.key == 'review' && reviewCount > 0 ? '${e.value} ($reviewCount)' : e.value,
            },
            selected: _status,
            onSelected: (v) => setState(() => _status = v ?? 'review'),
          ),
          Expanded(
            child: AsyncValueView<ReviewQueue>(
              value: async,
              onRefresh: () async => ref.refresh(adminResearchQueueProvider(_filter).future),
              data: (queue) {
                if (queue.items.isEmpty) {
                  return ListView(children: const [
                    SizedBox(height: 80),
                    Center(child: Text('Nothing here.', style: TextStyle(color: AppColors.gray500))),
                  ]);
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: queue.items.length,
                  itemBuilder: (context, i) => _ResearchTile(
                    r: queue.items[i],
                    onOpen: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => AdminResearchDetailScreen(id: queue.items[i].id),
                      ));
                      ref.invalidate(adminResearchQueueProvider);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ResearchTile extends StatelessWidget {
  const _ResearchTile({required this.r, required this.onOpen});
  final ReviewResearch r;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final meta = [
      if (r.author != null) r.author!,
      if (r.category != null) r.category!,
      '${r.chaptersCount} chapter${r.chaptersCount == 1 ? '' : 's'}',
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(
        padding: const EdgeInsets.all(14),
        onTap: onOpen,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(r.title,
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                ),
                const SizedBox(width: 8),
                MbuiBadge(r.statusLabel, appearance: _appearance(r.status)),
              ],
            ),
            const SizedBox(height: 4),
            Text(meta, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
            if (r.updatedAt != null)
              Text('Updated ${DateFormat('d MMM yyyy, HH:mm').format(r.updatedAt!)}',
                  style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
          ],
        ),
      ),
    );
  }
}

class AdminResearchDetailScreen extends ConsumerStatefulWidget {
  const AdminResearchDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<AdminResearchDetailScreen> createState() => _AdminResearchDetailScreenState();
}

class _AdminResearchDetailScreenState extends ConsumerState<AdminResearchDetailScreen> {
  bool _busy = false;

  AdminResearchRepository get _repo => ref.read(adminResearchRepositoryProvider);

  Future<void> _run(Future<String> Function() action) async {
    setState(() => _busy = true);
    try {
      final message = await action();
      ref.invalidate(adminResearchDetailProvider(widget.id));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _approve() async {
    final comment = await promptReason(context,
        title: 'Approve and publish',
        actionLabel: 'Publish',
        hint: 'Note to the author (optional)',
        required: false,
        variant: MbuiVariant.success);
    if (comment == null) return;
    await _run(() => _repo.decide(widget.id, 'approve', comment: comment));
  }

  Future<void> _requestChanges() async {
    final comment = await promptReason(context,
        title: 'Request changes',
        actionLabel: 'Send to author',
        hint: 'What should the author change?',
        variant: MbuiVariant.primary);
    if (comment == null) return;
    await _run(() => _repo.decide(widget.id, 'request-changes', comment: comment));
  }

  Future<void> _reject() async {
    final comment = await promptReason(context,
        title: 'Reject research', actionLabel: 'Reject', hint: 'Reason (optional)', required: false);
    if (comment == null) return;
    await _run(() => _repo.decide(widget.id, 'reject', comment: comment));
  }

  Future<void> _unpublish() async {
    final ok = await confirmAdminAction(context,
        title: 'Unpublish?',
        message: 'Readers will no longer see this research. You can publish it again later.',
        actionLabel: 'Unpublish',
        variant: MbuiVariant.danger);
    if (ok) await _run(() => _repo.decide(widget.id, 'unpublish'));
  }

  Future<void> _delete() async {
    final reason = await promptReason(context,
        title: 'Delete research', actionLabel: 'Delete', hint: 'Why is it being deleted?');
    if (reason == null || reason.isEmpty) return;
    setState(() => _busy = true);
    try {
      await _repo.delete(widget.id, reason);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminResearchDetailProvider(widget.id));
    final d = async.valueOrNull;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Review'),
        actions: [
          if (d?.canManage ?? false)
            IconButton(
              tooltip: 'Delete',
              icon: const Icon(Icons.delete_outline),
              onPressed: _busy ? null : _delete,
            ),
        ],
      ),
      bottomNavigationBar: d == null || !d.canManage ? null : _actions(d),
      body: AsyncValueView<ReviewResearchDetail>(
        value: async,
        onRefresh: () async => ref.refresh(adminResearchDetailProvider(widget.id).future),
        data: (d) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(d.row.title,
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.gray900)),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                MbuiBadge(d.row.statusLabel, appearance: _appearance(d.row.status)),
                if (d.row.category != null) MbuiBadge(d.row.category!),
              ],
            ),
            const SizedBox(height: 12),
            MbuiCard(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  AdminRow('Author', d.row.author ?? '—'),
                  if (d.authorEmail != null) AdminRow('Email', d.authorEmail!),
                  if (d.row.reviewer != null) AdminRow('Reviewer', d.row.reviewer!),
                  AdminRow('Chapters', '${d.chapters.length}'),
                ],
              ),
            ),
            if ((d.summary ?? '').isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Summary'),
              const SizedBox(height: 6),
              Text(d.summary!, style: const TextStyle(fontSize: 14, color: AppColors.gray700, height: 1.5)),
            ],
            if ((d.reviewNote ?? '').isNotEmpty) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.amber50,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Text('Last review note: ${d.reviewNote}',
                    style: const TextStyle(fontSize: 13, color: AppColors.amber700)),
              ),
            ],
            const SizedBox(height: 20),
            const MbuiSectionLabel('Content'),
            const SizedBox(height: 8),
            if (d.chapters.isEmpty)
              const Text('No chapters yet.', style: TextStyle(color: AppColors.gray500)),
            for (final c in d.chapters)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: EdgeInsets.zero,
                  clip: true,
                  child: ExpansionTile(
                    title: Text(c.title, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text('${c.sections.length} section${c.sections.length == 1 ? '' : 's'}',
                        style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    expandedCrossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final s in c.sections) ...[
                        const SizedBox(height: 8),
                        Text(s.heading,
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                        const SizedBox(height: 4),
                        MarkdownBody(data: s.body.isEmpty ? '_Empty section_' : s.body, selectable: true),
                      ],
                    ],
                  ),
                ),
              ),
            if (d.reviews.isNotEmpty) ...[
              const SizedBox(height: 20),
              const MbuiSectionLabel('Review history'),
              const SizedBox(height: 8),
              for (final h in d.reviews)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.history, size: 20, color: AppColors.gray400),
                  title: Text(
                    '${h.action.replaceAll('_', ' ')}${h.reviewer != null ? ' · ${h.reviewer}' : ''}',
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  subtitle: Text(
                    [
                      if ((h.comment ?? '').isNotEmpty) h.comment!,
                      if (h.at != null) DateFormat('d MMM yyyy, HH:mm').format(h.at!),
                    ].join('\n'),
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _actions(ReviewResearchDetail d) {
    final s = d.row.status;
    final inReview = const ['submitted', 'under_review', 'changes_requested'].contains(s);
    final buttons = <Widget>[
      if (inReview) ...[
        Expanded(child: MbuiButton(label: 'Changes', variant: MbuiVariant.secondary, onPressed: _busy ? null : _requestChanges)),
        const SizedBox(width: 8),
        Expanded(child: MbuiButton(label: 'Reject', variant: MbuiVariant.danger, onPressed: _busy ? null : _reject)),
        const SizedBox(width: 8),
        Expanded(child: MbuiButton(label: 'Publish', variant: MbuiVariant.success, loading: _busy, onPressed: _busy ? null : _approve)),
      ] else if (s == 'published')
        Expanded(child: MbuiButton(label: 'Unpublish', variant: MbuiVariant.secondary, loading: _busy, onPressed: _busy ? null : _unpublish))
      else
        Expanded(child: MbuiButton(label: 'Publish', variant: MbuiVariant.success, loading: _busy, onPressed: _busy ? null : _approve)),
    ];
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: AppColors.gray200)),
        ),
        child: Row(children: buttons),
      ),
    );
  }
}

class AdminResearchCategoriesScreen extends ConsumerWidget {
  const AdminResearchCategoriesScreen({super.key});

  Future<void> _edit(BuildContext context, WidgetRef ref, [ResearchCategoryRow? c]) async {
    final name = TextEditingController(text: c?.name);
    final description = TextEditingController(text: c?.description);
    final position = TextEditingController(text: c == null ? '' : '${c.position}');
    final save = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(ctx).bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(c == null ? 'New category' : 'Edit category',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            TextField(controller: name, autofocus: true, decoration: const InputDecoration(labelText: 'Name')),
            const SizedBox(height: 10),
            TextField(
              controller: description,
              maxLines: 2,
              decoration: const InputDecoration(labelText: 'Description (optional)'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: position,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Order (optional)'),
            ),
            const SizedBox(height: 16),
            MbuiButton(label: 'Save', fullWidth: true, onPressed: () => Navigator.pop(ctx, true)),
          ],
        ),
      ),
    );
    if (save != true || name.text.trim().isEmpty) return;
    try {
      await ref.read(adminResearchRepositoryProvider).saveCategory(
            id: c?.id,
            name: name.text.trim(),
            description: description.text.trim().isEmpty ? null : description.text.trim(),
            position: int.tryParse(position.text.trim()),
          );
      ref.invalidate(adminResearchCategoriesProvider);
    } on ApiException catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, ResearchCategoryRow c) async {
    final ok = await confirmAdminAction(context,
        title: 'Delete "${c.name}"?',
        message: c.count > 0
            ? '${c.count} research paper${c.count == 1 ? '' : 's'} will have no category.'
            : 'This category is not used.',
        actionLabel: 'Delete',
        variant: MbuiVariant.danger);
    if (!ok) return;
    try {
      await ref.read(adminResearchRepositoryProvider).deleteCategory(c.id);
      ref.invalidate(adminResearchCategoriesProvider);
    } on ApiException catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminResearchCategoriesProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research categories')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _edit(context, ref),
        icon: const Icon(Icons.add),
        label: const Text('Category'),
      ),
      body: AsyncValueView<List<ResearchCategoryRow>>(
        value: async,
        onRefresh: () async => ref.refresh(adminResearchCategoriesProvider.future),
        data: (rows) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            if (rows.isEmpty)
              const Center(child: Text('No categories yet.', style: TextStyle(color: AppColors.gray500))),
            for (final c in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.fromLTRB(14, 6, 4, 6),
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(c.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text('${c.count} research · /${c.slug}', style: const TextStyle(fontSize: 12)),
                    onTap: () => _edit(context, ref, c),
                    trailing: IconButton(
                      tooltip: 'Delete',
                      icon: const Icon(Icons.delete_outline, color: AppColors.red600),
                      onPressed: () => _delete(context, ref, c),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
