import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../models/tool.dart' show ChatMessage;
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class AdminChatScreen extends ConsumerWidget {
  const AdminChatScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminConversationsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Messages')),
      body: AsyncValueView<List<AdminConversation>>(
        value: async,
        onRefresh: () async => ref.refresh(adminConversationsProvider.future),
        data: (list) {
          if (list.isEmpty) {
            return const Center(
              child: Text('No conversations yet.',
                  style: TextStyle(color: AppColors.gray500)),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            itemBuilder: (context, i) {
              final c = list[i];
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => AdminChatThreadScreen(
                        conversationId: c.id,
                        userName: c.userName,
                      ),
                    ),
                  ),
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c.userName,
                                style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray900)),
                            const SizedBox(height: 2),
                            Text(
                              c.lastMessage == null
                                  ? c.userEmail
                                  : '${c.lastFromAdmin ? "You: " : ""}${c.lastMessage}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          if (c.updatedAgo != null)
                            Text(c.updatedAgo!,
                                style: const TextStyle(
                                    fontSize: 11, color: AppColors.gray400)),
                          const SizedBox(height: 4),
                          if (c.unread > 0)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppColors.indigo600,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text('${c.unread}',
                                  style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700)),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class AdminChatThreadScreen extends ConsumerStatefulWidget {
  const AdminChatThreadScreen({
    super.key,
    required this.conversationId,
    required this.userName,
  });

  final int conversationId;
  final String userName;

  @override
  ConsumerState<AdminChatThreadScreen> createState() =>
      _AdminChatThreadScreenState();
}

class _AdminChatThreadScreenState extends ConsumerState<AdminChatThreadScreen> {
  final _controller = TextEditingController();
  final _scroll = ScrollController();
  List<ChatMessage> _messages = [];
  bool _loading = true;
  bool _sending = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _poll?.cancel();
    _controller.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    try {
      final msgs = await ref
          .read(adminRepositoryProvider)
          .conversation(widget.conversationId);
      if (!mounted) return;
      setState(() {
        _messages = msgs;
        _loading = false;
      });
      ref.invalidate(adminConversationsProvider);
      _jumpToEnd();
    } catch (_) {
      if (!silent && mounted) setState(() => _loading = false);
    }
  }

  void _jumpToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.jumpTo(_scroll.position.maxScrollExtent);
      }
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    _controller.clear();
    try {
      final msg = await ref
          .read(adminRepositoryProvider)
          .replyToConversation(widget.conversationId, text);
      setState(() {
        _messages = [..._messages, msg];
        _sending = false;
      });
      _jumpToEnd();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _sending = false);
        _controller.text = text;
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(widget.userName)),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
                : _messages.isEmpty
                    ? const Center(
                        child: Text('No messages yet.',
                            style: TextStyle(color: AppColors.gray500)))
                    : ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.all(16),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) =>
                            _Bubble(msg: _messages[i]),
                      ),
          ),
          _Composer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.msg});
  final ChatMessage msg;

  @override
  Widget build(BuildContext context) {
    // Admin view: the admin's own messages sit on the right.
    final mine = msg.fromAdmin;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        constraints:
            BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.75),
        decoration: BoxDecoration(
          color: mine ? AppColors.indigo600 : Colors.white,
          border: mine ? null : Border.all(color: AppColors.gray200),
          borderRadius: BorderRadius.circular(12).copyWith(
            bottomRight: mine ? const Radius.circular(2) : null,
            bottomLeft: mine ? null : const Radius.circular(2),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(msg.body ?? '',
                style:
                    TextStyle(color: mine ? Colors.white : AppColors.gray900)),
            if (msg.time != null) ...[
              const SizedBox(height: 2),
              Text(msg.time!,
                  style: TextStyle(
                      fontSize: 10,
                      color: mine ? Colors.white70 : AppColors.gray400)),
            ],
          ],
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer(
      {required this.controller, required this.sending, required this.onSend});
  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(
          12, 8, 12, 8 + MediaQuery.viewInsetsOf(context).bottom),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.gray200)),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 4,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => onSend(),
              decoration: const InputDecoration(hintText: 'Reply to customer…'),
            ),
          ),
          const SizedBox(width: 8),
          IconButton.filled(
            onPressed: sending ? null : onSend,
            icon: sending
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.send),
          ),
        ],
      ),
    );
  }
}
