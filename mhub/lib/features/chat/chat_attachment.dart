import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../core/api_client.dart';
import '../../models/tool.dart' show ChatMessage;
import '../../theme/tokens.dart';

/// Attachments are private, so they are fetched with the user's token and
/// kept in memory while the app is open (the chat polls every few seconds).
final _bytesCache = <int, Uint8List>{};

final chatFileProvider = FutureProvider.family<Uint8List, int>((ref, id) async {
  final cached = _bytesCache[id];
  if (cached != null) return cached;
  final bytes = Uint8List.fromList(await ref.read(apiClientProvider).getBytes('/chat/messages/$id/file'));
  return _bytesCache[id] = bytes;
});

/// An image, video or voice note inside a chat bubble.
class ChatAttachmentView extends ConsumerWidget {
  const ChatAttachmentView({super.key, required this.msg, required this.mine});
  final ChatMessage msg;
  final bool mine;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (msg.type == 'image') {
      final file = ref.watch(chatFileProvider(msg.id));
      return ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: file.when(
          data: (bytes) => GestureDetector(
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => _ImageViewer(bytes: bytes, title: msg.fileName),
            )),
            child: Image.memory(bytes, fit: BoxFit.cover, height: 200, width: double.infinity),
          ),
          loading: () => Container(
            height: 160,
            color: mine ? Colors.white24 : AppColors.gray100,
            alignment: Alignment.center,
            child: const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)),
          ),
          error: (e, _) => _tile(context, Icons.broken_image_outlined, 'Image not available', null),
        ),
      );
    }

    final isVideo = msg.type == 'video';
    return _tile(
      context,
      isVideo ? Icons.play_circle_outline : Icons.graphic_eq,
      msg.fileName ?? (isVideo ? 'Video' : 'Voice note'),
      () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => _MediaPlayerScreen(msg: msg),
      )),
    );
  }

  Widget _tile(BuildContext context, IconData icon, String label, VoidCallback? onTap) {
    final color = mine ? Colors.white : AppColors.gray700;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 28),
            const SizedBox(width: 8),
            Flexible(
              child: Text(label,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: color, fontWeight: FontWeight.w500)),
            ),
          ],
        ),
      ),
    );
  }
}

class _ImageViewer extends StatelessWidget {
  const _ImageViewer({required this.bytes, this.title});
  final Uint8List bytes;
  final String? title;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
          title: Text(title ?? 'Image', style: const TextStyle(fontSize: 15)),
        ),
        body: Center(child: InteractiveViewer(maxScale: 5, child: Image.memory(bytes))),
      );
}

/// Plays a chat video or voice note. The file is private, so it is downloaded
/// with the token to a temporary file first.
class _MediaPlayerScreen extends ConsumerStatefulWidget {
  const _MediaPlayerScreen({required this.msg});
  final ChatMessage msg;

  @override
  ConsumerState<_MediaPlayerScreen> createState() => _MediaPlayerScreenState();
}

class _MediaPlayerScreenState extends ConsumerState<_MediaPlayerScreen> {
  VideoPlayerController? _controller;
  String? _error;

  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    try {
      final bytes = await ref.read(chatFileProvider(widget.msg.id).future);
      final name = widget.msg.fileName ?? 'chat-${widget.msg.id}';
      final dir = await Directory.systemTemp.createTemp('chat');
      final file = await File('${dir.path}/${name.replaceAll(RegExp(r'[^\w.\-]'), '_')}').writeAsBytes(bytes);
      final controller = VideoPlayerController.file(file);
      await controller.initialize();
      if (!mounted) {
        controller.dispose();
        return;
      }
      setState(() => _controller = controller);
      controller.play();
    } catch (e) {
      if (mounted) setState(() => _error = e is ApiException ? e.message : 'This file cannot be played on this phone.');
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;
    final isVideo = widget.msg.type == 'video';
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(widget.msg.fileName ?? (isVideo ? 'Video' : 'Voice note'), style: const TextStyle(fontSize: 15)),
      ),
      body: Center(
        child: _error != null
            ? Padding(
                padding: const EdgeInsets.all(24),
                child: Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70)),
              )
            : c == null
                ? const CircularProgressIndicator(strokeWidth: 2, color: Colors.white)
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (isVideo)
                        AspectRatio(aspectRatio: c.value.aspectRatio, child: VideoPlayer(c))
                      else
                        const Icon(Icons.graphic_eq, color: Colors.white, size: 96),
                      const SizedBox(height: 12),
                      VideoProgressIndicator(c, allowScrubbing: true, padding: const EdgeInsets.symmetric(horizontal: 16)),
                      ValueListenableBuilder(
                        valueListenable: c,
                        builder: (_, v, _) => IconButton(
                          iconSize: 48,
                          color: Colors.white,
                          icon: Icon(v.isPlaying ? Icons.pause_circle : Icons.play_circle),
                          onPressed: () => v.isPlaying ? c.pause() : c.play(),
                        ),
                      ),
                    ],
                  ),
      ),
    );
  }
}
