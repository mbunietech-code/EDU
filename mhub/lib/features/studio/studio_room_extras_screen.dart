import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/studio_extras_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

String _duration(int seconds) {
  final h = seconds ~/ 3600;
  final m = (seconds % 3600) ~/ 60;
  return h > 0 ? '${h}h ${m}m' : '${m}m';
}

final _when = DateFormat('d MMM yyyy, HH:mm');

/// A class in the Teaching Studio: recordings, materials, invited members
/// and the attendance report (the same tools as the web Studio page).
class StudioRoomExtrasScreen extends ConsumerStatefulWidget {
  const StudioRoomExtrasScreen({super.key, required this.roomId, required this.title});
  final int roomId;
  final String title;

  @override
  ConsumerState<StudioRoomExtrasScreen> createState() => _StudioRoomExtrasScreenState();
}

class _StudioRoomExtrasScreenState extends ConsumerState<StudioRoomExtrasScreen> {
  bool _busy = false;
  double? _progress;

  StudioExtrasRepository get _repo => ref.read(studioExtrasRepositoryProvider);

  void _snack(String text) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _run(Future<String> Function() action) async {
    setState(() => _busy = true);
    try {
      _snack(await action());
      ref.invalidate(studioRoomExtrasProvider(widget.roomId));
    } on ApiException catch (e) {
      _snack(e.errors?.values.firstOrNull?.firstOrNull ?? e.message);
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _progress = null;
        });
      }
    }
  }

  Future<void> _uploadRecording() async {
    final picked = await FilePicker.pickFiles(type: FileType.video);
    final path = picked?.files.single.path;
    if (path == null || !mounted) return;
    final shared = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Who can watch it?'),
        content: const Text('Share it with the learners who can see this class, or keep it for room staff only.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Staff only')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Share with learners')),
        ],
      ),
    );
    if (shared == null) return;
    setState(() => _progress = 0);
    await _run(() => _repo.uploadRecording(
          widget.roomId,
          File(path),
          shared: shared,
          onProgress: (p) {
            if (mounted) setState(() => _progress = p);
          },
        ));
  }

  Future<void> _deleteRecording(StudioRecording r) async {
    final reason = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete recording?'),
        content: TextField(
          controller: reason,
          autofocus: true,
          decoration: const InputDecoration(hintText: 'Reason'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok == true) await _run(() => _repo.deleteRecording(widget.roomId, r.id, reason.text.trim()));
    reason.dispose();
  }

  Future<void> _addMaterial() async {
    final picked = await FilePicker.pickFiles();
    final path = picked?.files.single.path;
    if (path == null) return;
    await _run(() => _repo.addMaterial(widget.roomId, path));
  }

  Future<void> _invite() async {
    final picked = await showModalBottomSheet<MemberHit>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => MemberPickerSheet(repo: _repo),
    );
    if (picked != null) await _run(() => _repo.invite(widget.roomId, [picked.id]));
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(studioRoomExtrasProvider(widget.roomId));
    final extras = async.valueOrNull;
    final tabs = [
      'Recordings',
      'Materials',
      if (extras?.isPrivate ?? false) 'Members',
      'Attendance',
    ];

    return DefaultTabController(
      key: ValueKey(tabs.length),
      length: tabs.length,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: Text(widget.title, overflow: TextOverflow.ellipsis),
          bottom: TabBar(isScrollable: true, tabs: [for (final t in tabs) Tab(text: t)]),
        ),
        body: Column(
          children: [
            if (_busy) LinearProgressIndicator(value: _progress),
            Expanded(
              child: AsyncValueView<StudioRoomExtras>(
                value: async,
                onRefresh: () async => ref.refresh(studioRoomExtrasProvider(widget.roomId).future),
                data: (x) => TabBarView(
                  children: [
                    _recordings(x),
                    _materials(x),
                    if (x.isPrivate) _members(x),
                    _AttendanceTab(roomId: widget.roomId, sessions: x.sessions),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _recordings(StudioRoomExtras x) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (x.canManage)
            MbuiButton(
              label: 'Upload a recording',
              icon: Icons.upload,
              fullWidth: true,
              onPressed: _busy ? null : _uploadRecording,
            ),
          const SizedBox(height: 12),
          if (x.recordings.isEmpty)
            const Text('No recordings yet. Recordings made during the class appear here when ready.',
                style: TextStyle(color: AppColors.gray500)),
          for (final r in x.recordings)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(r.name ?? 'Recording', style: const TextStyle(fontWeight: FontWeight.w700)),
                        ),
                        if (r.status != 'ready')
                          MbuiBadge(r.status, appearance: r.status == 'failed' ? MbuiAppearance.danger : MbuiAppearance.info)
                        else if (r.isPublished)
                          const MbuiBadge('Lesson', appearance: MbuiAppearance.success),
                      ],
                    ),
                    Text(
                      [?r.size, if (r.createdAt != null) _when.format(r.createdAt!)].join(' · '),
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                    ),
                    if (x.canManage)
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        dense: true,
                        title: const Text('Learners can watch'),
                        value: r.isShared,
                        onChanged: _busy ? null : (v) => _run(() => _repo.shareRecording(widget.roomId, r.id, v)),
                      ),
                    Wrap(
                      spacing: 8,
                      children: [
                        if (r.streamUrl != null)
                          TextButton.icon(
                            icon: const Icon(Icons.play_arrow, size: 18),
                            label: const Text('Watch'),
                            onPressed: () => launchUrl(Uri.parse(r.streamUrl!), mode: LaunchMode.externalApplication),
                          ),
                        if (x.canManage && !r.isPublished && r.status == 'ready')
                          TextButton.icon(
                            icon: const Icon(Icons.video_library_outlined, size: 18),
                            label: const Text('Make a lesson'),
                            onPressed: _busy ? null : () => _run(() => _repo.publishRecording(widget.roomId, r.id)),
                          ),
                        if (x.canManage)
                          TextButton.icon(
                            icon: const Icon(Icons.delete_outline, size: 18, color: AppColors.red600),
                            label: const Text('Delete', style: TextStyle(color: AppColors.red600)),
                            onPressed: _busy ? null : () => _deleteRecording(r),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      );

  Widget _materials(StudioRoomExtras x) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (x.canManage)
            MbuiButton(
              label: 'Add a file',
              icon: Icons.attach_file,
              fullWidth: true,
              onPressed: _busy ? null : _addMaterial,
            ),
          const SizedBox(height: 12),
          if (x.materials.isEmpty)
            const Text('No materials yet. Learners see them inside the live class.',
                style: TextStyle(color: AppColors.gray500)),
          for (final m in x.materials)
            MbuiCard(
              padding: const EdgeInsets.fromLTRB(14, 4, 4, 4),
              child: ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.description_outlined),
                title: Text(m.title),
                subtitle: Text('${(m.size / 1024).ceil()} KB'),
                onTap: m.url == null ? null : () => launchUrl(Uri.parse(m.url!), mode: LaunchMode.externalApplication),
                trailing: x.canManage
                    ? IconButton(
                        icon: const Icon(Icons.delete_outline, color: AppColors.red600),
                        onPressed: _busy ? null : () => _run(() => _repo.deleteMaterial(widget.roomId, m.id)),
                      )
                    : null,
              ),
            ),
        ],
      );

  Widget _members(StudioRoomExtras x) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (x.canManage)
            MbuiButton(label: 'Invite a member', icon: Icons.person_add_alt, fullWidth: true, onPressed: _busy ? null : _invite),
          const SizedBox(height: 12),
          if (x.members.isEmpty)
            const Text('Nobody is invited yet. Only invited members can join this class.',
                style: TextStyle(color: AppColors.gray500)),
          for (final m in x.members)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(child: Text(m.name.isEmpty ? '?' : m.name[0].toUpperCase())),
              title: Text(m.name),
              subtitle: m.email == null ? null : Text(m.email!),
              trailing: x.canManage
                  ? IconButton(
                      tooltip: 'Remove invitation',
                      icon: const Icon(Icons.close),
                      onPressed: _busy ? null : () => _run(() => _repo.removeMember(widget.roomId, m.id)),
                    )
                  : null,
            ),
        ],
      );
}

class MemberPickerSheet extends StatefulWidget {
  /// Search active members by name (or email for admins) and pick one.
  const MemberPickerSheet({super.key, required this.repo});
  final StudioExtrasRepository repo;

  @override
  State<MemberPickerSheet> createState() => _MemberPickerSheetState();
}

class _MemberPickerSheetState extends State<MemberPickerSheet> {
  List<MemberHit> _hits = const [];
  bool _loading = false;

  Future<void> _search(String q) async {
    if (q.trim().length < 2) return;
    setState(() => _loading = true);
    try {
      final hits = await widget.repo.searchUsers(q.trim());
      if (mounted) setState(() => _hits = hits);
    } on ApiException {
      // keep the last results
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              autofocus: true,
              onSubmitted: _search,
              onChanged: (v) => _search(v),
              decoration: const InputDecoration(hintText: 'Search name or email', prefixIcon: Icon(Icons.search)),
            ),
            if (_loading) const LinearProgressIndicator(),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 320),
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final h in _hits)
                    ListTile(
                      title: Text(h.name),
                      subtitle: h.email == null ? null : Text(h.email!),
                      onTap: () => Navigator.pop(context, h),
                    ),
                ],
              ),
            ),
          ],
        ),
      );
}

class _AttendanceTab extends ConsumerStatefulWidget {
  const _AttendanceTab({required this.roomId, required this.sessions});
  final int roomId;
  final List<StudioSession> sessions;

  @override
  ConsumerState<_AttendanceTab> createState() => _AttendanceTabState();
}

class _AttendanceTabState extends ConsumerState<_AttendanceTab> {
  int? _session;

  @override
  Widget build(BuildContext context) {
    final filter = (roomId: widget.roomId, sessionId: _session);
    return AsyncValueView<AttendanceReport?>(
      value: ref.watch(studioAttendanceProvider(filter)),
      onRefresh: () async => ref.refresh(studioAttendanceProvider(filter).future),
      data: (report) {
        if (report == null) {
          return ListView(children: const [
            Padding(
              padding: EdgeInsets.all(24),
              child: Text('No sessions yet. Attendance appears after the class goes live.',
                  style: TextStyle(color: AppColors.gray500)),
            ),
          ]);
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (widget.sessions.length > 1)
              DropdownButtonFormField<int>(
                initialValue: report.sessionId,
                decoration: const InputDecoration(labelText: 'Session'),
                items: [
                  for (final s in widget.sessions)
                    DropdownMenuItem(
                      value: s.id,
                      child: Text(s.startedAt == null ? 'Session ${s.id}' : _when.format(s.startedAt!)),
                    ),
                ],
                onChanged: (v) => setState(() => _session = v),
              ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _Stat('Attended', '${report.attendees}'),
                _Stat('Peak', '${report.peak}'),
                _Stat('Average', _duration(report.averageSeconds)),
                _Stat('Class length', _duration(report.durationSeconds)),
                if (report.removed > 0) _Stat('Removed', '${report.removed}'),
              ],
            ),
            const SizedBox(height: 16),
            for (final r in report.rows)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(r.name + (r.role == 'host' ? ' (host)' : '')),
                subtitle: Text([
                  ?r.email,
                  if (r.firstJoinedAt != null) 'joined ${DateFormat('HH:mm').format(r.firstJoinedAt!)}',
                  '${r.joins}×',
                  if (r.removed) 'removed',
                ].join(' · ')),
                trailing: Text(_duration(r.seconds), style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
          ],
        );
      },
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
        width: 150,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: AppColors.gray200),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
            Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          ],
        ),
      );
}
