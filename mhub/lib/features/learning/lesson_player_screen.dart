import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../core/api_client.dart';
import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'learning_widgets.dart';

class LessonPlayerScreen extends ConsumerWidget {
  const LessonPlayerScreen({
    super.key,
    required this.slug,
    required this.title,
  });
  final String slug;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lesson = ref.watch(lessonDetailProvider(slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(title, overflow: TextOverflow.ellipsis)),
      body: AsyncValueView<LessonDetail>(
        value: lesson,
        onRefresh: () async => ref.refresh(lessonDetailProvider(slug).future),
        data: (l) => _LessonBody(lesson: l),
      ),
    );
  }
}

class _LessonBody extends ConsumerStatefulWidget {
  const _LessonBody({required this.lesson});
  final LessonDetail lesson;

  @override
  ConsumerState<_LessonBody> createState() => _LessonBodyState();
}

class _LessonBodyState extends ConsumerState<_LessonBody> {
  late bool _completed = widget.lesson.card.progress?.completed ?? false;
  late List<LessonComment> _comments = [...widget.lesson.comments];
  final _commentController = TextEditingController();
  bool _savingComplete = false;
  bool _postingComment = false;

  LessonDetail get lesson => widget.lesson;

  Future<void> _toggleComplete() async {
    setState(() => _savingComplete = true);
    try {
      await ref
          .read(learningRepositoryProvider)
          .setCompleted(lesson.card.slug, !_completed);
      setState(() => _completed = !_completed);
      ref.invalidate(learningHomeProvider);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _savingComplete = false);
    }
  }

  Future<void> _postComment({int? parentId}) async {
    final text = _commentController.text.trim();
    if (text.isEmpty || _postingComment) return;
    setState(() => _postingComment = true);
    try {
      await ref
          .read(learningRepositoryProvider)
          .comment(lesson.card.slug, text, parentId: parentId);
      _commentController.clear();
      final fresh = await ref.refresh(
        lessonDetailProvider(lesson.card.slug).future,
      );
      if (mounted) setState(() => _comments = [...fresh.comments]);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _postingComment = false);
    }
  }

  Future<void> _deleteComment(int id) async {
    try {
      await ref
          .read(learningRepositoryProvider)
          .deleteComment(lesson.card.slug, id);
      final fresh = await ref.refresh(
        lessonDetailProvider(lesson.card.slug).future,
      );
      if (mounted) setState(() => _comments = [...fresh.comments]);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        if (lesson.canPlay && lesson.sources.isNotEmpty)
          LessonVideo(
            lesson: lesson,
            onEnded: () => setState(() => _completed = true),
          )
        else
          Container(
            height: 200,
            color: AppColors.gray900,
            alignment: Alignment.center,
            padding: const EdgeInsets.all(24),
            child: const Text(
              'This lesson video is not available yet.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70),
            ),
          ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                lesson.card.title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: AppColors.gray900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                [
                  if (lesson.card.course != null) lesson.card.course!.name,
                  if (lesson.topic != null) lesson.topic!,
                  if (lesson.card.durationLabel != null)
                    lesson.card.durationLabel!,
                ].join(' · '),
                style: const TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
              const SizedBox(height: 14),
              MbuiButton(
                label: _completed
                    ? 'Completed. Mark as not done'
                    : 'Mark as completed',
                icon: _completed
                    ? Icons.check_circle
                    : Icons.check_circle_outline,
                variant: _completed
                    ? MbuiVariant.secondary
                    : MbuiVariant.success,
                loading: _savingComplete,
                fullWidth: true,
                onPressed: _toggleComplete,
              ),
              if (lesson.description != null &&
                  lesson.description!.trim().isNotEmpty) ...[
                const SizedBox(height: 18),
                const MbuiSectionLabel('About this lesson'),
                const SizedBox(height: 6),
                Text(
                  lesson.description!,
                  style: const TextStyle(
                    fontSize: 14,
                    height: 1.5,
                    color: AppColors.gray700,
                  ),
                ),
              ],
              if (lesson.resources.isNotEmpty) ...[
                const SizedBox(height: 18),
                const MbuiSectionLabel('Resources'),
                const SizedBox(height: 6),
                for (final r in lesson.resources)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      r.type == 'link' ? Icons.link : Icons.attach_file,
                      color: AppColors.indigo600,
                    ),
                    title: Text(r.title),
                    subtitle: r.sizeLabel == null ? null : Text(r.sizeLabel!),
                    trailing: r.url == null
                        ? null
                        : const Icon(Icons.open_in_new, size: 18),
                    onTap: r.url == null
                        ? null
                        : () => launchUrl(
                            Uri.parse(r.url!),
                            mode: LaunchMode.externalApplication,
                          ),
                  ),
              ],
              const SizedBox(height: 18),
              const MbuiSectionLabel('Comments'),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: TextField(
                      controller: _commentController,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(
                        hintText: 'Write a comment',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _postingComment ? null : _postComment,
                    icon: _postingComment
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.send),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              if (_comments.isEmpty)
                const EmptyNote('No comments yet.')
              else
                for (final comment in _comments)
                  _CommentTile(comment: comment, onDelete: _deleteComment),
            ],
          ),
        ),
      ],
    );
  }
}

class _CommentTile extends StatelessWidget {
  const _CommentTile({required this.comment, required this.onDelete});
  final LessonComment comment;
  final void Function(int id) onDelete;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: MbuiCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  comment.user.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.gray900,
                  ),
                ),
              ),
              if (comment.canDelete)
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: () => onDelete(comment.id),
                  icon: const Icon(Icons.delete_outline, size: 18),
                ),
            ],
          ),
          Text(
            comment.body,
            style: const TextStyle(color: AppColors.gray700, height: 1.4),
          ),
          if (comment.replies.isNotEmpty) ...[
            const SizedBox(height: 8),
            for (final reply in comment.replies)
              Padding(
                padding: const EdgeInsets.only(left: 12, top: 6),
                child: Text(
                  '${reply.user.name}: ${reply.body}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray600,
                  ),
                ),
              ),
          ],
        ],
      ),
    ),
  );
}

/// Video with simple controls, resume, quality choice and progress beacons
/// (start / tick every 15s / pause / seek / ended) like the web player.
class LessonVideo extends ConsumerStatefulWidget {
  const LessonVideo({super.key, required this.lesson, this.onEnded});
  final LessonDetail lesson;
  final VoidCallback? onEnded;

  @override
  ConsumerState<LessonVideo> createState() => _LessonVideoState();
}

class _LessonVideoState extends ConsumerState<LessonVideo> {
  VideoPlayerController? _controller;
  late VideoSource _source = widget.lesson.sources.first;
  bool _failed = false;
  bool _wasPlaying = false;
  bool _started = false;
  bool _ended = false;
  DateTime _lastBeaconAt = DateTime.now();

  // Kept from initState: `ref` may not be used once the widget is disposing.
  late final LearningRepository _repo;
  Timer? _ticker;

  String get _slug => widget.lesson.card.slug;

  @override
  void initState() {
    super.initState();
    _repo = ref.read(learningRepositoryProvider);
    _load(_source, startAt: widget.lesson.resumeAt);
  }

  Future<void> _load(VideoSource source, {int startAt = 0}) async {
    final old = _controller;
    final controller = VideoPlayerController.networkUrl(Uri.parse(source.url));
    setState(() {
      _controller = controller;
      _source = source;
      _failed = false;
    });
    await old?.dispose();

    try {
      await controller.initialize();
      if (startAt > 0) await controller.seekTo(Duration(seconds: startAt));
      controller.addListener(_onTick);
      if (mounted) setState(() {});
    } catch (_) {
      // No player on this platform / format: offer the browser instead.
      if (mounted) setState(() => _failed = true);
    }
  }

  void _onTick() {
    final c = _controller;
    if (c == null || !mounted) return;
    final playing = c.value.isPlaying;

    if (playing && !_wasPlaying) {
      _beacon(_started ? 'seek' : 'start');
      _started = true;
      _ticker ??= Timer.periodic(const Duration(seconds: 15), (_) {
        if (_controller?.value.isPlaying ?? false) _beacon('tick');
      });
    } else if (!playing && _wasPlaying) {
      _beacon(_isAtEnd(c) ? 'ended' : 'pause');
    }
    _wasPlaying = playing;

    if (_isAtEnd(c) && !_ended) {
      _ended = true;
      widget.onEnded?.call();
    }
    setState(() {});
  }

  bool _isAtEnd(VideoPlayerController c) =>
      c.value.duration > Duration.zero &&
      c.value.position >= c.value.duration - const Duration(seconds: 1);

  void _beacon(String event) {
    final c = _controller;
    if (c == null) return;
    final position = c.value.position.inSeconds;
    final now = DateTime.now();
    final watched = event == 'start'
        ? 0
        : now.difference(_lastBeaconAt).inSeconds.clamp(0, 60);
    _lastBeaconAt = now;

    _repo
        .reportProgress(
          _slug,
          position: position,
          duration: c.value.duration.inSeconds > 0
              ? c.value.duration.inSeconds
              : null,
          event: event,
          watched: watched,
        )
        .catchError(
          (_) {},
        ); // Progress is best effort; never interrupt playback.
  }

  @override
  void dispose() {
    _ticker?.cancel();
    final c = _controller;
    if (c != null && c.value.isPlaying) _beacon('pause');
    c?.removeListener(_onTick);
    c?.dispose();
    super.dispose();
  }

  String _fmt(Duration d) {
    final h = d.inHours,
        m = d.inMinutes.remainder(60),
        s = d.inSeconds.remainder(60);
    String two(int n) => n.toString().padLeft(2, '0');
    return h > 0 ? '$h:${two(m)}:${two(s)}' : '${two(m)}:${two(s)}';
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;

    if (_failed) {
      return Container(
        height: 200,
        color: AppColors.gray900,
        alignment: Alignment.center,
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'The video cannot play inside the app on this device.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(foregroundColor: Colors.white),
              icon: const Icon(Icons.open_in_new),
              label: const Text('Watch in browser'),
              onPressed: () => launchUrl(
                Uri.parse(_source.url),
                mode: LaunchMode.externalApplication,
              ),
            ),
          ],
        ),
      );
    }

    if (c == null || !c.value.isInitialized) {
      return Container(
        height: 200,
        color: Colors.black,
        alignment: Alignment.center,
        child: const CircularProgressIndicator(color: Colors.white),
      );
    }

    return Container(
      color: Colors.black,
      child: Column(
        children: [
          AspectRatio(
            aspectRatio: c.value.aspectRatio > 0 ? c.value.aspectRatio : 16 / 9,
            child: GestureDetector(
              onTap: () => c.value.isPlaying ? c.pause() : c.play(),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  VideoPlayer(c),
                  if (!c.value.isPlaying)
                    Container(
                      decoration: const BoxDecoration(
                        color: Colors.black45,
                        shape: BoxShape.circle,
                      ),
                      padding: const EdgeInsets.all(10),
                      child: const Icon(
                        Icons.play_arrow_rounded,
                        color: Colors.white,
                        size: 44,
                      ),
                    ),
                  if (c.value.isBuffering)
                    const CircularProgressIndicator(color: Colors.white),
                ],
              ),
            ),
          ),
          VideoProgressIndicator(
            c,
            allowScrubbing: true,
            padding: const EdgeInsets.symmetric(vertical: 6),
            colors: const VideoProgressColors(
              playedColor: AppColors.indigo500,
              bufferedColor: Colors.white24,
            ),
          ),
          Row(
            children: [
              IconButton(
                color: Colors.white,
                icon: Icon(
                  c.value.isPlaying
                      ? Icons.pause_rounded
                      : Icons.play_arrow_rounded,
                ),
                onPressed: () => c.value.isPlaying ? c.pause() : c.play(),
              ),
              IconButton(
                color: Colors.white,
                icon: const Icon(Icons.replay_10_rounded),
                onPressed: () =>
                    c.seekTo(c.value.position - const Duration(seconds: 10)),
              ),
              IconButton(
                color: Colors.white,
                icon: const Icon(Icons.forward_10_rounded),
                onPressed: () =>
                    c.seekTo(c.value.position + const Duration(seconds: 10)),
              ),
              Text(
                '${_fmt(c.value.position)} / ${_fmt(c.value.duration)}',
                style: const TextStyle(color: Colors.white70, fontSize: 12),
              ),
              const Spacer(),
              if (widget.lesson.sources.length > 1)
                PopupMenuButton<VideoSource>(
                  tooltip: 'Quality',
                  icon: const Icon(
                    Icons.high_quality_outlined,
                    color: Colors.white,
                  ),
                  onSelected: (s) =>
                      _load(s, startAt: c.value.position.inSeconds),
                  itemBuilder: (_) => [
                    for (final s in widget.lesson.sources)
                      CheckedPopupMenuItem(
                        value: s,
                        checked: s.url == _source.url,
                        child: Text(s.label),
                      ),
                  ],
                ),
            ],
          ),
        ],
      ),
    );
  }
}
