import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/tool.dart' show ChatMessage;
import '../../theme/tokens.dart';
import 'chat_attachment.dart';
import 'chat_repository.dart';

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key});

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  final _controller = TextEditingController();
  final _scroll = ScrollController();
  List<ChatMessage> _messages = [];
  bool _online = false;
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
      final state = await ref.read(chatRepositoryProvider).load();
      if (!mounted) return;
      setState(() {
        _messages = state.messages;
        _online = state.supportOnline;
        _loading = false;
      });
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
      final msg = await ref.read(chatRepositoryProvider).send(text);
      setState(() {
        _messages = [..._messages, msg];
        _sending = false;
      });
      _jumpToEnd();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _sending = false);
        _controller.text = text;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  /// Pick a photo or video and send it; any typed text goes as the caption.
  Future<void> _attach() async {
    if (_sending) return;
    final type = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_outlined),
              title: const Text('Photo'),
              subtitle: const Text('JPG, PNG, GIF or WebP · up to 12 MB'),
              onTap: () => Navigator.pop(context, 'image'),
            ),
            ListTile(
              leading: const Icon(Icons.videocam_outlined),
              title: const Text('Video'),
              subtitle: const Text('MP4, MOV, WebM · up to 60 MB'),
              onTap: () => Navigator.pop(context, 'video'),
            ),
            ListTile(
              leading: const Icon(Icons.mic_none),
              title: const Text('Audio / voice note'),
              subtitle: const Text('MP3, M4A, WAV, OGG · up to 15 MB'),
              onTap: () => Navigator.pop(context, 'audio'),
            ),
          ],
        ),
      ),
    );
    if (type == null) return;

    final picked = await FilePicker.pickFiles(
      type: switch (type) { 'image' => FileType.image, 'video' => FileType.video, _ => FileType.audio },
    );
    final path = picked?.files.single.path;
    if (path == null || !mounted) return;

    final caption = _controller.text.trim();
    setState(() => _sending = true);
    try {
      final msg = await ref.read(chatRepositoryProvider).sendFile(path, type, caption: caption);
      _controller.clear();
      setState(() {
        _messages = [..._messages, msg];
        _sending = false;
      });
      _jumpToEnd();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _sending = false);
        final detail = e.errors?['file']?.first ?? e.message;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(detail)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Messages'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(24),
          child: Padding(
            padding: const EdgeInsets.only(left: 16, bottom: 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: _online ? const Color(0xFF22C55E) : AppColors.gray400,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(_online ? 'Support online' : 'Support offline',
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                ],
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
                : _messages.isEmpty
                    ? const Center(
                        child: Text('Start a conversation with support.',
                            style: TextStyle(color: AppColors.gray500)))
                    : ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.all(16),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) => _Bubble(msg: _messages[i]),
                      ),
          ),
          _Composer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
            onAttach: _attach,
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
    final mine = !msg.fromAdmin;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.75),
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
            if (msg.hasFile) ChatAttachmentView(msg: msg, mine: mine),
            if (msg.hasFile && (msg.body ?? '').isNotEmpty) const SizedBox(height: 6),
            if (!msg.hasFile || (msg.body ?? '').isNotEmpty)
              Text(msg.body ?? '',
                  style: TextStyle(color: mine ? Colors.white : AppColors.gray900)),
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
  const _Composer({
    required this.controller,
    required this.sending,
    required this.onSend,
    required this.onAttach,
  });
  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;
  final VoidCallback onAttach;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(12, 8, 12, 8 + MediaQuery.viewInsetsOf(context).bottom),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColors.gray200)),
      ),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Send a photo, video or voice note',
            onPressed: sending ? null : onAttach,
            icon: const Icon(Icons.attach_file),
          ),
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 4,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => onSend(),
              decoration: const InputDecoration(hintText: 'Type a message…'),
            ),
          ),
          const SizedBox(width: 8),
          IconButton.filled(
            onPressed: sending ? null : onSend,
            icon: sending
                ? const SizedBox(
                    height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.send),
          ),
        ],
      ),
    );
  }
}
