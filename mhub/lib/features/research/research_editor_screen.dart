import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/research_api.dart';
import '../../data/research_author_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

void _toast(BuildContext context, String text) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

String _errorText(Object e) {
  if (e is! ApiException) return 'Something went wrong. Try again.';
  final all = e.errors?.values.expand((v) => v).toList() ?? const <String>[];
  return all.isNotEmpty ? all.first : e.message;
}

Future<String?> _askText(BuildContext context, String title, {String initial = '', String label = 'Title'}) async {
  final c = TextEditingController(text: initial);
  final ok = await showDialog<bool>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(title),
      content: TextField(controller: c, autofocus: true, decoration: InputDecoration(labelText: label)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
      ],
    ),
  );
  return ok == true && c.text.trim().isNotEmpty ? c.text.trim() : null;
}

Future<bool> _confirm(BuildContext context, String title, String body) async =>
    await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Yes')),
        ],
      ),
    ) ??
    false;

/// Start a new research from the "My Research" screen.
Future<void> startNewResearch(BuildContext context, WidgetRef ref) async {
  final title = await _askText(context, 'New research', label: 'Research title');
  if (title == null || !context.mounted) return;

  try {
    final draft = await ref.read(researchAuthorRepositoryProvider).create(title: title);
    ref.invalidate(myResearchProvider);
    if (context.mounted) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => ResearchEditorScreen(id: draft.id)));
    }
  } catch (e) {
    if (context.mounted) _toast(context, _errorText(e));
  }
}

/// The research builder: details, chapters and sections, import, submit.
class ResearchEditorScreen extends ConsumerStatefulWidget {
  const ResearchEditorScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<ResearchEditorScreen> createState() => _ResearchEditorScreenState();
}

class _ResearchEditorScreenState extends ConsumerState<ResearchEditorScreen> {
  bool _busy = false;

  ResearchAuthorRepository get _repo => ref.read(researchAuthorRepositoryProvider);

  Future<void> _run(Future<void> Function() action, {String? success}) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(researchDraftProvider(widget.id));
      ref.invalidate(myResearchProvider);
      if (success != null && mounted) _toast(context, success);
    } catch (e) {
      if (mounted) _toast(context, _errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _editDetails(ResearchDraft d) async {
    final saved = await Navigator.of(context)
        .push<bool>(MaterialPageRoute(builder: (_) => _DetailsScreen(draft: d)));
    if (saved == true) ref.invalidate(researchDraftProvider(widget.id));
  }

  Future<void> _import() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheet) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.upload_file),
              title: const Text('Import a document'),
              subtitle: const Text('Word (.docx), Markdown or text'),
              onTap: () => Navigator.pop(sheet, 'file'),
            ),
            ListTile(
              leading: const Icon(Icons.content_paste),
              title: const Text('Paste text'),
              subtitle: const Text('"# Chapter" and "## Section" headings'),
              onTap: () => Navigator.pop(sheet, 'text'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || choice == null) return;

    if (choice == 'file') {
      final picked = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: ['docx', 'md', 'markdown', 'txt']);
      final path = picked?.files.single.path;
      if (path == null) return;
      await _run(() async {
        final message = await _repo.import(widget.id, filePath: path);
        if (mounted) _toast(context, message);
      });
    } else {
      final text = TextEditingController();
      final ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Paste text'),
          content: SizedBox(
            width: 500,
            child: TextField(
              controller: text,
              minLines: 8,
              maxLines: 14,
              decoration: const InputDecoration(hintText: '# Chapter title\n\n## Section title\n\nYour text...'),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Import')),
          ],
        ),
      );
      if (ok == true && text.text.trim().isNotEmpty) {
        await _run(() async {
          final message = await _repo.import(widget.id, text: text.text);
          if (mounted) _toast(context, message);
        });
      }
    }
  }

  Future<void> _move(ResearchDraft d, {int? chapterIndex, DraftChapter? chapter, int? sectionIndex, required int by}) async {
    if (chapter == null) {
      final ids = d.chapters.map((c) => c.id).toList();
      final i = chapterIndex!, j = i + by;
      if (j < 0 || j >= ids.length) return;
      ids.insert(j, ids.removeAt(i));
      await _run(() => _repo.reorder(d.id, 'chapter', ids));
    } else {
      final ids = chapter.sections.map((s) => s.id).toList();
      final i = sectionIndex!, j = i + by;
      if (j < 0 || j >= ids.length) return;
      ids.insert(j, ids.removeAt(i));
      await _run(() => _repo.reorder(d.id, 'section', ids));
    }
  }

  Future<void> _openSection(DraftSection s, bool canEdit) async {
    await Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => SectionEditorScreen(sectionId: s.id, canEdit: canEdit)));
    ref.invalidate(researchDraftProvider(widget.id));
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(researchDraftProvider(widget.id));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Edit research'),
        actions: [
          if (async.valueOrNull?.canEdit ?? false)
            IconButton(tooltip: 'Import', icon: const Icon(Icons.upload_file), onPressed: _busy ? null : _import),
        ],
        bottom: _busy ? const PreferredSize(preferredSize: Size.fromHeight(2), child: LinearProgressIndicator(minHeight: 2)) : null,
      ),
      body: AsyncValueView<ResearchDraft>(
        value: async,
        onRefresh: () async => ref.refresh(researchDraftProvider(widget.id).future),
        data: (d) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
          children: [
            MbuiCard(
              onTap: d.canEdit ? () => _editDetails(d) : null,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Expanded(
                      child: Text(d.title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                    ),
                    MbuiStatusBadge(d.status),
                  ]),
                  const SizedBox(height: 4),
                  Text(d.category ?? 'No category', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                  if (d.summary != null && d.summary!.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(d.summary!, style: const TextStyle(fontSize: 13, color: AppColors.gray700)),
                  ],
                  if (d.canEdit) ...[
                    const SizedBox(height: 8),
                    const Text('Tap to edit title, category and summary',
                        style: TextStyle(fontSize: 11, color: AppColors.indigo600)),
                  ],
                ],
              ),
            ),
            if (d.reviewNote != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: AppColors.amber50, borderRadius: BorderRadius.circular(AppRadius.md)),
                child: Text('Changes requested: ${d.reviewNote}',
                    style: const TextStyle(fontSize: 13, color: AppColors.amber700)),
              ),
            ],
            if (!d.canEdit) ...[
              const SizedBox(height: 12),
              const MbuiCard(child: Text('This research is with the reviewers. You can edit it again once they reply.')),
            ],
            const SizedBox(height: 16),
            Row(children: [
              const Expanded(child: MbuiSectionLabel('Chapters')),
              if (d.canEdit)
                TextButton.icon(
                  icon: const Icon(Icons.add),
                  label: const Text('Chapter'),
                  onPressed: () async {
                    final title = await _askText(context, 'New chapter');
                    if (title != null) await _run(() => _repo.addChapter(d.id, title), success: 'Chapter added.');
                  },
                ),
            ]),
            if (d.chapters.isEmpty)
              const MbuiCard(child: Text('No chapters yet. Add a chapter, or import a document.')),
            for (var ci = 0; ci < d.chapters.length; ci++)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  padding: const EdgeInsets.fromLTRB(14, 8, 4, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Expanded(
                          child: Text('${ci + 1}. ${d.chapters[ci].title}',
                              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                        ),
                        if (d.canEdit)
                          PopupMenuButton<String>(
                            onSelected: (v) async {
                              final c = d.chapters[ci];
                              switch (v) {
                                case 'up':
                                  await _move(d, chapterIndex: ci, by: -1);
                                case 'down':
                                  await _move(d, chapterIndex: ci, by: 1);
                                case 'rename':
                                  final t = await _askText(context, 'Rename chapter', initial: c.title);
                                  if (t != null) await _run(() => _repo.renameChapter(c.id, t));
                                case 'delete':
                                  if (await _confirm(context, 'Remove chapter?', 'Its sections are removed too.')) {
                                    await _run(() => _repo.deleteChapter(c.id), success: 'Chapter removed.');
                                  }
                              }
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(value: 'up', child: Text('Move up')),
                              PopupMenuItem(value: 'down', child: Text('Move down')),
                              PopupMenuItem(value: 'rename', child: Text('Rename')),
                              PopupMenuItem(value: 'delete', child: Text('Remove')),
                            ],
                          ),
                      ]),
                      for (var si = 0; si < d.chapters[ci].sections.length; si++)
                        ListTile(
                          dense: true,
                          contentPadding: const EdgeInsets.only(left: 8),
                          title: Text(d.chapters[ci].sections[si].heading),
                          subtitle: Text('${d.chapters[ci].sections[si].words} words'),
                          onTap: () => _openSection(d.chapters[ci].sections[si], d.canEdit),
                          trailing: d.canEdit
                              ? Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    IconButton(
                                      icon: const Icon(Icons.arrow_upward, size: 18),
                                      onPressed: () => _move(d, chapter: d.chapters[ci], sectionIndex: si, by: -1),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.arrow_downward, size: 18),
                                      onPressed: () => _move(d, chapter: d.chapters[ci], sectionIndex: si, by: 1),
                                    ),
                                  ],
                                )
                              : null,
                        ),
                      if (d.canEdit)
                        TextButton.icon(
                          icon: const Icon(Icons.add, size: 18),
                          label: const Text('Section'),
                          onPressed: () async {
                            final heading = await _askText(context, 'New section', label: 'Section heading');
                            if (heading == null) return;
                            try {
                              final s = await _repo.addSection(d.chapters[ci].id, heading: heading);
                              await _openSection(s, true);
                            } catch (e) {
                              if (context.mounted) _toast(context, _errorText(e));
                            }
                          },
                        ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 16),
            if (d.status == 'draft' || d.status == 'changes_requested')
              MbuiButton(
                label: 'Submit for review',
                icon: Icons.send,
                fullWidth: true,
                onPressed: !d.canSubmit || _busy
                    ? null
                    : () async {
                        if (await _confirm(context, 'Submit for review?',
                            'You cannot edit it while it is being reviewed.')) {
                          await _run(() async {
                            final message = await _repo.submit(d.id);
                            if (mounted) _toast(this.context, message);
                          });
                        }
                      },
              ),
            if (d.status == 'draft' || d.status == 'changes_requested') ...[
              const SizedBox(height: 8),
              TextButton(
                onPressed: () async {
                  if (!await _confirm(context, 'Delete this research?', 'This cannot be undone.')) return;
                  try {
                    await _repo.delete(d.id);
                    ref.invalidate(myResearchProvider);
                    if (context.mounted) Navigator.of(context).pop();
                  } catch (e) {
                    if (context.mounted) _toast(context, _errorText(e));
                  }
                },
                child: const Text('Delete draft', style: TextStyle(color: AppColors.red600)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailsScreen extends ConsumerStatefulWidget {
  const _DetailsScreen({required this.draft});
  final ResearchDraft draft;

  @override
  ConsumerState<_DetailsScreen> createState() => _DetailsScreenState();
}

class _DetailsScreenState extends ConsumerState<_DetailsScreen> {
  late final _title = TextEditingController(text: widget.draft.title);
  late final _summary = TextEditingController(text: widget.draft.summary ?? '');
  late int? _category = widget.draft.categoryId;
  bool _busy = false;

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await ref.read(researchAuthorRepositoryProvider).updateDetails(widget.draft.id,
          title: _title.text.trim(), categoryId: _category, summary: _summary.text.trim());
      ref.invalidate(myResearchProvider);
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _toast(context, _errorText(e));
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = ref.watch(researchCategoriesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Research details')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _title, decoration: const InputDecoration(labelText: 'Title')),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: _category,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Category'),
            items: [
              const DropdownMenuItem<int>(value: null, child: Text('None')),
              for (final c in categories.valueOrNull ?? const <({int id, String name})>[])
                DropdownMenuItem(value: c.id, child: Text(c.name)),
            ],
            onChanged: (v) => setState(() => _category = v),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _summary,
            minLines: 3,
            maxLines: 6,
            maxLength: 1000,
            decoration: const InputDecoration(labelText: 'Summary', alignLabelWithHint: true),
          ),
          const SizedBox(height: 8),
          MbuiButton(label: 'Save details', fullWidth: true, loading: _busy, onPressed: _save),
        ],
      ),
    );
  }
}

/// Write one section (Markdown supported, like the web editor).
class SectionEditorScreen extends ConsumerStatefulWidget {
  const SectionEditorScreen({super.key, required this.sectionId, required this.canEdit});
  final int sectionId;
  final bool canEdit;

  @override
  ConsumerState<SectionEditorScreen> createState() => _SectionEditorScreenState();
}

class _SectionEditorScreenState extends ConsumerState<SectionEditorScreen> {
  final _heading = TextEditingController();
  final _body = TextEditingController();
  bool _loaded = false;
  bool _busy = false;
  bool _dirty = false;
  String? _loadError;

  ResearchAuthorRepository get _repo => ref.read(researchAuthorRepositoryProvider);

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final s = await _repo.section(widget.sectionId);
      _heading.text = s.heading;
      _body.text = s.body ?? '';
      _heading.addListener(() => _dirty = true);
      _body.addListener(() => _dirty = true);
      if (mounted) setState(() => _loaded = true);
    } catch (e) {
      if (mounted) setState(() => _loadError = _errorText(e));
    }
  }

  Future<void> _save({bool close = true}) async {
    setState(() => _busy = true);
    try {
      await _repo.saveSection(widget.sectionId, heading: _heading.text.trim(), body: _body.text);
      _dirty = false;
      if (mounted) {
        _toast(context, 'Section saved.');
        if (close) Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) _toast(context, _errorText(e));
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _delete() async {
    if (!await _confirm(context, 'Remove section?', 'This cannot be undone.')) return;
    try {
      await _repo.deleteSection(widget.sectionId);
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) _toast(context, _errorText(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final words = RegExp(r'\S+').allMatches(_body.text).length;

    return PopScope(
      canPop: !_dirty || !widget.canEdit,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _confirm(context, 'Discard changes?', 'Your edits to this section are not saved.')) {
          _dirty = false;
          if (context.mounted) Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Section'),
          actions: [
            if (widget.canEdit && _loaded) ...[
              IconButton(tooltip: 'Remove', icon: const Icon(Icons.delete_outline), onPressed: _delete),
              IconButton(tooltip: 'Save', icon: const Icon(Icons.check), onPressed: _busy ? null : () => _save()),
            ],
          ],
        ),
        body: _loadError != null
            ? Center(child: Text(_loadError!))
            : !_loaded
                ? const Center(child: CircularProgressIndicator())
                : Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        TextField(
                          controller: _heading,
                          readOnly: !widget.canEdit,
                          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                          decoration: const InputDecoration(labelText: 'Heading'),
                        ),
                        const SizedBox(height: 12),
                        Expanded(
                          child: TextField(
                            controller: _body,
                            readOnly: !widget.canEdit,
                            expands: true,
                            maxLines: null,
                            minLines: null,
                            textAlignVertical: TextAlignVertical.top,
                            keyboardType: TextInputType.multiline,
                            onChanged: (_) => setState(() {}),
                            decoration: const InputDecoration(
                              alignLabelWithHint: true,
                              labelText: 'Text',
                              hintText: 'Write here. Markdown works: **bold**, *italic*, - lists, > quotes.',
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Row(children: [
                          Text('$words words', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                          const Spacer(),
                          if (widget.canEdit)
                            MbuiButton(label: 'Save', loading: _busy, onPressed: () => _save()),
                        ]),
                      ],
                    ),
                  ),
      ),
    );
  }
}
