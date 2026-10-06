import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

/// Team chat between admins: private 1:1 chats and groups (like the web).

class TeamChatList {
  TeamChatList(Map<String, dynamic> j)
      : chats = ((j['chats'] as List?) ?? const []).cast<Map<String, dynamic>>(),
        others = ((j['others'] as List?) ?? const []).cast<Map<String, dynamic>>(),
        admins = ((j['admins'] as List?) ?? const []).cast<Map<String, dynamic>>(),
        canManageGroups = j['can_manage_groups'] as bool? ?? false;

  final List<Map<String, dynamic>> chats;
  final List<Map<String, dynamic>> others;
  final List<Map<String, dynamic>> admins;
  final bool canManageGroups;
}

final teamChatListProvider = FutureProvider.autoDispose<TeamChatList>((ref) async =>
    TeamChatList((await ref.watch(apiClientProvider).get('/admin/team-chat') as Map<String, dynamic>)['data']
        as Map<String, dynamic>));

class TeamChatScreen extends ConsumerWidget {
  const TeamChatScreen({super.key});

  Future<void> _open(BuildContext context, WidgetRef ref, int id) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => TeamConversationScreen(groupId: id)));
    ref.invalidate(teamChatListProvider);
  }

  Future<void> _newGroup(BuildContext context, WidgetRef ref, TeamChatList list) async {
    final name = TextEditingController();
    final picked = <int>{};
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => StatefulBuilder(
        builder: (context, setLocal) => AlertDialog(
          title: const Text('New group'),
          content: SizedBox(
            width: 400,
            child: ListView(
              shrinkWrap: true,
              children: [
                TextField(controller: name, decoration: const InputDecoration(labelText: 'Group name')),
                const SizedBox(height: 8),
                for (final a in list.admins)
                  CheckboxListTile(
                    dense: true,
                    value: picked.contains((a['id'] as num).toInt()),
                    title: Text(a['name'] as String),
                    onChanged: (v) => setLocal(() => v == true ? picked.add((a['id'] as num).toInt()) : picked.remove((a['id'] as num).toInt())),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Create')),
          ],
        ),
      ),
    );
    if (ok != true || name.text.trim().isEmpty || picked.isEmpty) return;

    try {
      final res = await ref.read(apiClientProvider).post('/admin/team-chat/groups', data: {
        'name': name.text.trim(),
        'members': picked.toList(),
      }) as Map<String, dynamic>;
      if (context.mounted) await _open(context, ref, (((res['data'] as Map)['id']) as num).toInt());
    } on ApiException catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(teamChatListProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Team chat')),
      floatingActionButton: (async.valueOrNull?.canManageGroups ?? false)
          ? FloatingActionButton.extended(
              icon: const Icon(Icons.group_add),
              label: const Text('Group'),
              onPressed: () => _newGroup(context, ref, async.requireValue),
            )
          : null,
      body: AsyncValueView<TeamChatList>(
        value: async,
        onRefresh: () async => ref.refresh(teamChatListProvider.future),
        data: (list) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          children: [
            if (list.chats.isEmpty) const MbuiCard(child: Text('No chats yet. Start one with a colleague below.')),
            for (final c in list.chats)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.all(12),
                  onTap: () => _open(context, ref, (c['id'] as num).toInt()),
                  child: Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: AppColors.indigo50,
                        child: Icon(c['is_direct'] == true ? Icons.person : Icons.groups, color: AppColors.indigo600),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c['name'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                            Text(c['last_message'] as String? ?? 'No messages yet',
                                maxLines: 1, overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(c['last_at'] as String? ?? '', style: const TextStyle(fontSize: 10, color: AppColors.gray400)),
                          if (((c['unread'] as num?) ?? 0) > 0)
                            Container(
                              margin: const EdgeInsets.only(top: 4),
                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(color: AppColors.indigo600, borderRadius: BorderRadius.circular(99)),
                              child: Text('${c['unread']}', style: const TextStyle(color: Colors.white, fontSize: 11)),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            if (list.others.isNotEmpty) ...[
              const SizedBox(height: 12),
              const MbuiSectionLabel('Start a private chat'),
              const SizedBox(height: 6),
              for (final o in list.others)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.chat_bubble_outline),
                  title: Text(o['name'] as String),
                  onTap: () async {
                    try {
                      final res = await ref.read(apiClientProvider).post('/admin/team-chat/start/${o['id']}') as Map<String, dynamic>;
                      if (context.mounted) await _open(context, ref, (((res['data'] as Map)['id']) as num).toInt());
                    } on ApiException catch (e) {
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
                    }
                  },
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class TeamConversationScreen extends ConsumerStatefulWidget {
  const TeamConversationScreen({super.key, required this.groupId});
  final int groupId;

  @override
  ConsumerState<TeamConversationScreen> createState() => _TeamConversationScreenState();
}

class _TeamConversationScreenState extends ConsumerState<TeamConversationScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<Map<String, dynamic>> _messages = [];
  String _name = 'Chat';
  bool _sending = false;
  Timer? _poll;
  late final ApiClient _api;

  String get _base => '/admin/team-chat/${widget.groupId}/messages';

  @override
  void initState() {
    super.initState();
    _api = ref.read(apiClientProvider);
    _fetch(initial: true);
    _poll = Timer.periodic(const Duration(seconds: 5), (_) => _fetch());
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  int get _lastId => _messages.isEmpty ? 0 : (_messages.last['id'] as num).toInt();

  Future<void> _fetch({bool initial = false}) async {
    try {
      final data = (await _api.get(_base, query: {if (!initial) 'after': _lastId}) as Map<String, dynamic>)['data']
          as Map<String, dynamic>;
      final fresh = ((data['messages'] as List?) ?? const []).cast<Map<String, dynamic>>();
      if (!mounted) return;
      setState(() {
        _name = data['name'] as String? ?? _name;
        final known = _messages.map((m) => m['id']).toSet();
        _messages.addAll(fresh.where((m) => !known.contains(m['id'])));
      });
      if (fresh.isNotEmpty) _toBottom();
    } on ApiException {
      // Network hiccup: the next poll tries again.
    }
  }

  void _toBottom() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) _scroll.jumpTo(_scroll.position.maxScrollExtent);
      });

  void _replace(Map<String, dynamic> updated) {
    final i = _messages.indexWhere((m) => m['id'] == updated['id']);
    setState(() => i >= 0 ? _messages[i] = updated : _messages.add(updated));
  }

  Future<void> _sendText() async {
    final text = _input.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      final res = await _api.post(_base, data: {'type': 'text', 'body': text}) as Map<String, dynamic>;
      _input.clear();
      _replace(res['data'] as Map<String, dynamic>);
      _toBottom();
    } on ApiException catch (e) {
      _snack(e.message);
    }
    if (mounted) setState(() => _sending = false);
  }

  Future<void> _sendImage() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, maxWidth: 1800, imageQuality: 85);
    if (picked == null) return;
    setState(() => _sending = true);
    try {
      final res = await _api.post(_base, data: FormData.fromMap({
        'type': 'image',
        'file': await MultipartFile.fromFile(picked.path, filename: picked.name),
      })) as Map<String, dynamic>;
      _replace(res['data'] as Map<String, dynamic>);
      _toBottom();
    } on ApiException catch (e) {
      _snack(e.message);
    }
    if (mounted) setState(() => _sending = false);
  }

  Future<void> _actions(Map<String, dynamic> m) async {
    if (m['mine'] != true || m['deleted'] == true) return;
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (sheet) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (m['editable'] == true)
              ListTile(leading: const Icon(Icons.edit), title: const Text('Edit'), onTap: () => Navigator.pop(sheet, 'edit')),
            ListTile(leading: const Icon(Icons.delete_outline), title: const Text('Delete'), onTap: () => Navigator.pop(sheet, 'delete')),
          ],
        ),
      ),
    );
    if (!mounted || choice == null) return;

    try {
      if (choice == 'delete') {
        final res = await _api.delete('$_base/${m['id']}') as Map<String, dynamic>;
        _replace(res['data'] as Map<String, dynamic>);
      } else {
        final c = TextEditingController(text: m['body'] as String? ?? '');
        final ok = await showDialog<bool>(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Edit message'),
            content: TextField(controller: c, maxLines: 5, minLines: 1),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
              FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
            ],
          ),
        );
        if (ok == true && c.text.trim().isNotEmpty) {
          final res = await _api.put('$_base/${m['id']}', data: {'body': c.text.trim()}) as Map<String, dynamic>;
          _replace(res['data'] as Map<String, dynamic>);
        }
      }
    } on ApiException catch (e) {
      _snack(e.message);
    }
  }

  void _snack(String text) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(_name)),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scroll,
              padding: const EdgeInsets.all(12),
              itemCount: _messages.length,
              itemBuilder: (_, i) => _TeamBubble(
                message: _messages[i],
                fileLoader: () => _api.getBytes('$_base/${_messages[i]['id']}/file'),
                onLongPress: () => _actions(_messages[i]),
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 6, 8, 10),
              child: Row(
                children: [
                  IconButton(icon: const Icon(Icons.image_outlined), onPressed: _sending ? null : _sendImage),
                  Expanded(
                    child: TextField(
                      controller: _input,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(hintText: 'Message'),
                    ),
                  ),
                  const SizedBox(width: 6),
                  IconButton.filled(icon: const Icon(Icons.send), onPressed: _sending ? null : _sendText),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamBubble extends StatelessWidget {
  const _TeamBubble({required this.message, required this.fileLoader, required this.onLongPress});
  final Map<String, dynamic> message;
  final Future<List<int>> Function() fileLoader;
  final VoidCallback onLongPress;

  @override
  Widget build(BuildContext context) {
    final m = message;
    final mine = m['mine'] == true;
    final deleted = m['deleted'] == true;
    final fg = mine ? Colors.white : AppColors.gray900;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onLongPress: onLongPress,
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(10),
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
          decoration: BoxDecoration(
            color: mine ? AppColors.indigo600 : Colors.white,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: mine ? null : Border.all(color: AppColors.gray200),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (m['sender'] != null)
                Text(m['sender'] as String, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.indigo700)),
              if (m['has_file'] == true && m['type'] == 'image')
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: FutureBuilder<List<int>>(
                    future: fileLoader(),
                    builder: (_, snap) => snap.hasData
                        ? ClipRRect(
                            borderRadius: BorderRadius.circular(AppRadius.md),
                            child: Image.memory(Uint8List.fromList(snap.data!), width: 220, fit: BoxFit.cover),
                          )
                        : const SizedBox(width: 220, height: 120, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
                  ),
                )
              else if (m['has_file'] == true)
                Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.attach_file, size: 16, color: fg),
                  Flexible(child: Text(m['file_name'] as String? ?? 'File', style: TextStyle(color: fg))),
                ]),
              if ((m['body'] as String?)?.isNotEmpty ?? false)
                Text(m['body'] as String,
                    style: TextStyle(color: deleted ? (mine ? Colors.white70 : AppColors.gray400) : fg, fontStyle: deleted ? FontStyle.italic : null)),
              const SizedBox(height: 2),
              Text('${m['time'] ?? ''}${m['edited'] == true ? ' · edited' : ''}',
                  style: TextStyle(fontSize: 10, color: mine ? Colors.white70 : AppColors.gray400)),
            ],
          ),
        ),
      ),
    );
  }
}
