import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

// ============================================================== data

class TeamNote {
  const TeamNote({
    required this.id,
    required this.body,
    required this.color,
    required this.background,
    required this.border,
    this.pinned = false,
    this.creatorName,
    this.editorName,
    this.edited = false,
    this.canChange = false,
    this.updatedAgo,
  });

  final int id;
  final String body;
  final String color;
  final Color background;
  final Color border;
  final bool pinned;
  final String? creatorName;
  final String? editorName;
  final bool edited;
  final bool canChange;
  final String? updatedAgo;

  factory TeamNote.fromJson(Map<String, dynamic> j) => TeamNote(
        id: (j['id'] as num).toInt(),
        body: j['body'] as String? ?? '',
        color: j['color'] as String? ?? 'yellow',
        background: _hex(j['background'] as String?, const Color(0xFFFEF9C3)),
        border: _hex(j['border'] as String?, const Color(0xFFFDE047)),
        pinned: j['pinned'] == true,
        creatorName: j['creator_name'] as String?,
        editorName: j['editor_name'] as String?,
        edited: j['edited'] == true,
        canChange: j['can_change'] == true,
        updatedAgo: j['updated_ago'] as String?,
      );
}

Color _hex(String? hex, Color fallback) {
  final h = (hex ?? '').replaceFirst('#', '');
  final v = int.tryParse(h, radix: 16);
  return h.length == 6 && v != null ? Color(0xFF000000 | v) : fallback;
}

/// Same palette as the web board (background colours).
const _noteColors = {
  'yellow': Color(0xFFFEF9C3),
  'green': Color(0xFFDCFCE7),
  'blue': Color(0xFFDBEAFE),
  'pink': Color(0xFFFCE7F3),
  'purple': Color(0xFFEDE9FE),
};

class TeamNotesRepository {
  TeamNotesRepository(this._api);
  final ApiClient _api;

  Future<List<TeamNote>> notes() async {
    final j = await _api.get('/admin/sticky-notes') as Map<String, dynamic>;
    return (j['data'] as List)
        .map((e) => TeamNote.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> add(String body, String color) =>
      _api.post('/admin/sticky-notes', data: {'body': body, 'color': color});

  Future<void> save(int id, String body, String color) =>
      _api.put('/admin/sticky-notes/$id', data: {'body': body, 'color': color});

  Future<void> togglePin(int id) => _api.post('/admin/sticky-notes/$id/pin');

  Future<void> remove(int id) => _api.delete('/admin/sticky-notes/$id');
}

final teamNotesRepositoryProvider =
    Provider((ref) => TeamNotesRepository(ref.watch(apiClientProvider)));

final teamNotesProvider = FutureProvider.autoDispose<List<TeamNote>>(
    (ref) => ref.watch(teamNotesRepositoryProvider).notes());

// ============================================================== screen

class AdminNotesScreen extends ConsumerWidget {
  const AdminNotesScreen({super.key});

  Future<void> _edit(BuildContext context, WidgetRef ref, [TeamNote? note]) async {
    final result = await showModalBottomSheet<(String, String)>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _NoteEditor(note: note),
    );
    if (result == null) return;
    final repo = ref.read(teamNotesRepositoryProvider);
    try {
      if (note == null) {
        await repo.add(result.$1, result.$2);
      } else {
        await repo.save(note.id, result.$1, result.$2);
      }
      ref.invalidate(teamNotesProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(note == null ? 'Note added.' : 'Note saved.')));
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(teamNotesProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Team notes')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _edit(context, ref),
        icon: const Icon(Icons.add),
        label: const Text('New note'),
      ),
      body: AsyncValueView<List<TeamNote>>(
        value: async,
        onRefresh: () async => ref.refresh(teamNotesProvider.future),
        data: (notes) {
          final pinned = notes.where((n) => n.pinned).toList();
          final others = notes.where((n) => !n.pinned).toList();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            children: [
              const Text(
                'Notes for the whole team. Not for passwords: those go in the vault.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
              const SizedBox(height: 12),
              if (notes.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 80),
                  child: Center(
                    child: Text('No notes yet.',
                        style: TextStyle(color: AppColors.gray500)),
                  ),
                ),
              if (pinned.isNotEmpty) ...[
                const MbuiSectionLabel('Pinned'),
                const SizedBox(height: 8),
                for (final n in pinned)
                  _NoteCard(note: n, onEdit: () => _edit(context, ref, n)),
                const SizedBox(height: 8),
              ],
              if (others.isNotEmpty && pinned.isNotEmpty) ...[
                const MbuiSectionLabel('Other notes'),
                const SizedBox(height: 8),
              ],
              for (final n in others)
                _NoteCard(note: n, onEdit: () => _edit(context, ref, n)),
            ],
          );
        },
      ),
    );
  }
}

class _NoteCard extends ConsumerStatefulWidget {
  const _NoteCard({required this.note, required this.onEdit});
  final TeamNote note;
  final VoidCallback onEdit;

  @override
  ConsumerState<_NoteCard> createState() => _NoteCardState();
}

class _NoteCardState extends ConsumerState<_NoteCard> {
  bool _expanded = false;

  Future<void> _act(Future<void> Function() call, String done) async {
    try {
      await call();
      ref.invalidate(teamNotesProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.note;
    final repo = ref.read(teamNotesRepositoryProvider);
    final long = n.body.length > 400 || '\n'.allMatches(n.body).length > 8;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 6, 6),
        decoration: BoxDecoration(
          color: n.background,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(color: n.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: SelectableText(
                n.body,
                maxLines: long && !_expanded ? 8 : null,
                style: const TextStyle(fontSize: 14, color: AppColors.gray900, height: 1.4),
              ),
            ),
            if (long)
              TextButton(
                style: TextButton.styleFrom(
                    padding: EdgeInsets.zero, visualDensity: VisualDensity.compact),
                onPressed: () => setState(() => _expanded = !_expanded),
                child: Text(_expanded ? 'Show less' : 'Show more'),
              ),
            const SizedBox(height: 6),
            Row(
              children: [
                Expanded(
                  child: Text(
                    [
                      n.creatorName ?? 'Someone',
                      if (n.edited && n.editorName != null) 'edited by ${n.editorName}',
                      if (n.updatedAgo != null) n.updatedAgo!,
                    ].join(' · '),
                    style: const TextStyle(fontSize: 11, color: AppColors.gray600),
                  ),
                ),
                IconButton(
                  tooltip: 'Copy note',
                  visualDensity: VisualDensity.compact,
                  icon: const Icon(Icons.copy, size: 18),
                  onPressed: () async {
                    await Clipboard.setData(ClipboardData(text: n.body));
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Note copied.')));
                    }
                  },
                ),
                if (n.canChange) ...[
                  IconButton(
                    tooltip: n.pinned ? 'Unpin' : 'Pin to the top',
                    visualDensity: VisualDensity.compact,
                    icon: Icon(n.pinned ? Icons.push_pin : Icons.push_pin_outlined, size: 18),
                    onPressed: () => _act(() => repo.togglePin(n.id),
                        n.pinned ? 'Note unpinned.' : 'Note pinned.'),
                  ),
                  IconButton(
                    tooltip: 'Edit note',
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    onPressed: widget.onEdit,
                  ),
                  IconButton(
                    tooltip: 'Remove note',
                    visualDensity: VisualDensity.compact,
                    icon: const Icon(Icons.delete_outline, size: 18),
                    onPressed: () async {
                      final ok = await confirmAdminAction(
                        context,
                        title: 'Remove note',
                        message: 'Remove this note for everyone?',
                        actionLabel: 'Remove',
                        variant: MbuiVariant.danger,
                      );
                      if (ok) await _act(() => repo.remove(n.id), 'Note removed.');
                    },
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _NoteEditor extends StatefulWidget {
  const _NoteEditor({this.note});
  final TeamNote? note;

  @override
  State<_NoteEditor> createState() => _NoteEditorState();
}

class _NoteEditorState extends State<_NoteEditor> {
  late final _body = TextEditingController(text: widget.note?.body ?? '');
  late String _color = widget.note?.color ?? 'yellow';

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.note == null ? 'New note' : 'Edit note',
                style: const TextStyle(
                    fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.gray900)),
            const SizedBox(height: 12),
            TextField(
              controller: _body,
              autofocus: true,
              minLines: 4,
              maxLines: 12,
              decoration: InputDecoration(
                hintText: 'Write the note (no passwords)',
                filled: true,
                fillColor: _noteColors[_color],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                for (final e in _noteColors.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () => setState(() => _color = e.key),
                      child: Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(
                          color: e.value,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: _color == e.key ? AppColors.indigo600 : AppColors.gray300,
                            width: _color == e.key ? 2.5 : 1,
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  final text = _body.text.trim();
                  if (text.isEmpty) return;
                  Navigator.pop(context, (text, _color));
                },
                child: Text(widget.note == null ? 'Add note' : 'Save note'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
