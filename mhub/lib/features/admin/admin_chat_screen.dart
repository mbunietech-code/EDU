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

const _chatTabs = {
  'all': 'All',
  'unread': 'Unread',
  'awaiting': 'Awaiting reply',
  'replied': 'Replied',
  'online': 'Online now',
};

const _chatPeriods = {
  null: 'Any time',
  'today': 'Active today',
  '7d': 'Last 7 days',
  '30d': 'Last 30 days',
};

class AdminChatScreen extends ConsumerStatefulWidget {
  const AdminChatScreen({super.key});

  @override
  ConsumerState<AdminChatScreen> createState() => _AdminChatScreenState();
}

class _AdminChatScreenState extends ConsumerState<AdminChatScreen> {
  final _search = TextEditingController();

  /// Pages 2, 3... loaded with "Load more"; page 1 comes from the provider.
  final List<AdminConversation> _more = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _search.text = ref.read(adminChatSearchProvider);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final next = await ref.read(adminRepositoryProvider).conversations(
            status: ref.read(adminChatStatusProvider),
            period: ref.read(adminChatPeriodProvider),
            search: ref.read(adminChatSearchProvider),
            page: _page + 1,
          );
      if (!mounted) return;
      setState(() {
        _more.addAll(next.items);
        _page = next.currentPage;
        _lastPage = next.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _applySearch(String value) {
    ref.read(adminChatSearchProvider.notifier).state = value.trim();
  }

  Future<void> _pickPeriod() async {
    final current = ref.read(adminChatPeriodProvider);
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final e in _chatPeriods.entries)
              ListTile(
                title: Text(e.value),
                trailing: current == e.key
                    ? const Icon(Icons.check, color: AppColors.indigo600)
                    : null,
                onTap: () => Navigator.of(context).pop(e.key ?? ''),
              ),
          ],
        ),
      ),
    );
    if (picked == null) return;
    ref.read(adminChatPeriodProvider.notifier).state =
        picked.isEmpty ? null : picked;
  }

  @override
  Widget build(BuildContext context) {
    // A new first page (filter change, refresh, reply) starts the list over.
    ref.listen<AsyncValue<AdminInbox>>(adminConversationsProvider, (_, next) {
      final inbox = next.valueOrNull;
      if (inbox == null) return;
      setState(() {
        _more.clear();
        _page = inbox.currentPage;
        _lastPage = inbox.lastPage;
      });
    });

    final async = ref.watch(adminConversationsProvider);
    final status = ref.watch(adminChatStatusProvider);
    final period = ref.watch(adminChatPeriodProvider);
    final search = ref.watch(adminChatSearchProvider);
    final counts = async.valueOrNull?.counts ?? const <String, int>{};
    final filtered = status != 'all' || period != null || search.isNotEmpty;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Messages')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _search,
                    textInputAction: TextInputAction.search,
                    onSubmitted: _applySearch,
                    decoration: InputDecoration(
                      isDense: true,
                      hintText: 'Name, email or message text',
                      prefixIcon: const Icon(Icons.search, size: 20),
                      suffixIcon: search.isEmpty
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.close, size: 18),
                              tooltip: 'Clear search',
                              onPressed: () {
                                _search.clear();
                                _applySearch('');
                              },
                            ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                ActionChip(
                  avatar: const Icon(Icons.schedule, size: 16),
                  label: Text(_chatPeriods[period] ?? 'Any time'),
                  onPressed: _pickPeriod,
                ),
              ],
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final e in _chatTabs.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(counts.containsKey(e.key)
                          ? '${e.value} ${counts[e.key]}'
                          : e.value),
                      selected: status == e.key,
                      onSelected: (_) => ref
                          .read(adminChatStatusProvider.notifier)
                          .state = e.key,
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: AsyncValueView<AdminInbox>(
              value: async,
              onRefresh: () async =>
                  ref.refresh(adminConversationsProvider.future),
              data: (inbox) {
                final list = [...inbox.items, ..._more];
                if (list.isEmpty) {
                  return ListView(
                    children: [
                      const SizedBox(height: 120),
                      Center(
                        child: Text(
                          filtered
                              ? 'No conversations match.\nTry another tab, period or search.'
                              : 'No conversations yet.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: AppColors.gray500),
                        ),
                      ),
                    ],
                  );
                }
                final hasMore = _page < _lastPage;
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: list.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, i) {
                    if (i == list.length) {
                      return Padding(
                        padding: const EdgeInsets.only(top: 4, bottom: 16),
                        child: MbuiButton(
                          label: 'Load more',
                          variant: MbuiVariant.secondary,
                          fullWidth: true,
                          loading: _loadingMore,
                          onPressed: _loadMore,
                        ),
                      );
                    }
                    return _ConversationTile(conversation: list[i]);
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

class _ConversationTile extends StatelessWidget {
  const _ConversationTile({required this.conversation});
  final AdminConversation conversation;

  @override
  Widget build(BuildContext context) {
    final c = conversation;
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
                  Row(
                    children: [
                      Flexible(
                        child: Text(c.userName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.gray900)),
                      ),
                      const SizedBox(width: 6),
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: c.online
                              ? AppColors.emerald600
                              : AppColors.gray300,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    c.lastMessage == null
                        ? c.userEmail
                        : '${c.lastFromAdmin ? "You: " : ""}${c.lastMessage}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 12,
                        fontWeight:
                            c.unread > 0 ? FontWeight.w600 : FontWeight.w400,
                        color:
                            c.unread > 0 ? AppColors.gray900 : AppColors.gray500),
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
                    padding:
                        const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.indigo600,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(c.unread > 99 ? '99+' : '${c.unread}',
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
      // Refresh the inbox once (unread badge), not on every poll: that would
      // throw away the pages the admin had scrolled to.
      if (!silent) ref.invalidate(adminConversationsProvider);
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
