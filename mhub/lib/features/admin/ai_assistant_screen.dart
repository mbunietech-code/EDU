import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';

/// The admin AI assistant: quick questions and free text about orders,
/// payments, users, accounts, errors... Each admin sees only their own chats.
class AiAssistantScreen extends ConsumerStatefulWidget {
  const AiAssistantScreen({super.key});

  @override
  ConsumerState<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiMessage {
  _AiMessage(this.role, this.content, this.time);
  final String role;
  final String content;
  final String? time;
}

class _AiAssistantScreenState extends ConsumerState<AiAssistantScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  int? _conversationId;
  String _title = 'AI Assistant';
  List<_AiMessage> _messages = [];
  List<({String id, String label})> _quick = [];
  List<({int id, String title, String? ago})> _history = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;

  ApiClient get _api => ref.read(apiClientProvider);

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    try {
      await _loadIndex();
      if (_history.isNotEmpty) {
        await _open(_history.first.id);
      } else {
        await _newConversation();
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadIndex() async {
    final data = (await _api.get('/admin/ai-assistant') as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    _quick = ((data['quick_questions'] as List?) ?? const [])
        .map((e) => (id: (e as Map)['id'] as String, label: e['label'] as String))
        .toList();
    _history = ((data['conversations'] as List?) ?? const [])
        .map((e) => (id: ((e as Map)['id'] as num).toInt(), title: e['title'] as String? ?? 'Chat', ago: e['updated_ago'] as String?))
        .toList();
  }

  Future<void> _open(int id) async {
    final data = (await _api.get('/admin/ai-assistant/$id') as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    setState(() {
      _conversationId = id;
      _title = data['title'] as String? ?? 'AI Assistant';
      _messages = ((data['messages'] as List?) ?? const [])
          .map((e) => _AiMessage((e as Map)['role'] as String, e['content'] as String? ?? '', e['time'] as String?))
          .toList();
    });
    _toBottom();
  }

  Future<void> _newConversation() async {
    final data = (await _api.post('/admin/ai-assistant') as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    setState(() {
      _conversationId = (data['id'] as num).toInt();
      _title = data['title'] as String? ?? 'New chat';
      _messages = [];
    });
  }

  Future<void> _send(String text, {String? questionId}) async {
    final id = _conversationId;
    if (id == null || text.trim().isEmpty || _sending) return;
    setState(() {
      _sending = true;
      _messages = [..._messages, _AiMessage('user', text.trim(), null)];
    });
    _input.clear();
    _toBottom();

    try {
      final data = (await _api.post('/admin/ai-assistant/$id', data: {
        'message': text.trim(),
        'question_id': ?questionId,
      }) as Map<String, dynamic>)['data'] as Map<String, dynamic>;
      setState(() {
        _messages = [..._messages, _AiMessage('assistant', data['content'] as String? ?? '', data['time'] as String?)];
        _title = data['conversation_title'] as String? ?? _title;
      });
    } on ApiException catch (e) {
      setState(() => _messages = [..._messages, _AiMessage('assistant', 'Error: ${e.message}', null)]);
    }
    if (mounted) setState(() => _sending = false);
    _toBottom();
  }

  void _toBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
    });
  }

  Future<void> _showHistory() async {
    await _loadIndex();
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheet) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            for (final h in _history)
              ListTile(
                selected: h.id == _conversationId,
                title: Text(h.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: h.ago == null ? null : Text(h.ago!),
                onTap: () {
                  Navigator.pop(sheet);
                  _open(h.id);
                },
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () async {
                    Navigator.pop(sheet);
                    await _api.delete('/admin/ai-assistant/${h.id}');
                    if (h.id == _conversationId) await _boot();
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(_title, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(tooltip: 'History', icon: const Icon(Icons.history), onPressed: _showHistory),
          IconButton(tooltip: 'New chat', icon: const Icon(Icons.add_comment_outlined), onPressed: _newConversation),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!)))
              : Column(
                  children: [
                    Expanded(
                      child: ListView(
                        controller: _scroll,
                        padding: const EdgeInsets.all(16),
                        children: [
                          if (_messages.isEmpty)
                            const Padding(
                              padding: EdgeInsets.only(bottom: 12),
                              child: Text('Ask about orders, payments, users, accounts, subscriptions, errors or the database. '
                                  'Swahili or English both work.',
                                  style: TextStyle(color: AppColors.gray500)),
                            ),
                          for (final m in _messages) _Bubble(message: m),
                          if (_sending)
                            const Padding(
                              padding: EdgeInsets.all(8),
                              child: Align(alignment: Alignment.centerLeft, child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))),
                            ),
                        ],
                      ),
                    ),
                    SizedBox(
                      height: 44,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        children: [
                          for (final q in _quick)
                            Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: ActionChip(label: Text(q.label), onPressed: _sending ? null : () => _send(q.label, questionId: q.id)),
                            ),
                        ],
                      ),
                    ),
                    SafeArea(
                      top: false,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
                        child: Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _input,
                                minLines: 1,
                                maxLines: 4,
                                textInputAction: TextInputAction.send,
                                onSubmitted: (v) => _send(v),
                                decoration: const InputDecoration(hintText: 'Ask anything...'),
                              ),
                            ),
                            const SizedBox(width: 8),
                            IconButton.filled(
                              icon: const Icon(Icons.send),
                              onPressed: _sending ? null : () => _send(_input.text),
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

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});
  final _AiMessage message;

  @override
  Widget build(BuildContext context) {
    final mine = message.role == 'user';

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.85),
        decoration: BoxDecoration(
          color: mine ? AppColors.indigo600 : Colors.white,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: mine ? null : Border.all(color: AppColors.gray200),
        ),
        child: mine
            ? Text(message.content, style: const TextStyle(color: Colors.white))
            : MarkdownBody(data: message.content, selectable: true),
      ),
    );
  }
}
