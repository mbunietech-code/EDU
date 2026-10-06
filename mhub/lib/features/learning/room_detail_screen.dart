import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'live_class_screen.dart';

class RoomDetailScreen extends ConsumerWidget {
  const RoomDetailScreen({super.key, required this.slug, required this.title});
  final String slug;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final room = ref.watch(roomDetailProvider(slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(title, overflow: TextOverflow.ellipsis)),
      body: AsyncValueView<RoomDetail>(
        value: room,
        onRefresh: () async => ref.refresh(roomDetailProvider(slug).future),
        data: (r) {
          final c = r.card;
          final when = c.scheduledAt == null
              ? null
              : DateFormat('EEEE d MMMM yyyy, HH:mm').format(c.scheduledAt!);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiCard(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        MbuiBadge(
                          c.isLive ? 'Live now' : c.statusLabel,
                          appearance: c.isLive
                              ? MbuiAppearance.danger
                              : MbuiAppearance.info,
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      c.title,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: AppColors.gray900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    if (when != null)
                      _Info(icon: Icons.event_outlined, text: when),
                    if (c.durationMinutes > 0)
                      _Info(
                        icon: Icons.schedule,
                        text: '${c.durationMinutes} minutes',
                      ),
                    if (c.host != null)
                      _Info(
                        icon: Icons.person_outline,
                        text: 'Host: ${c.host!.name}',
                      ),
                    if (c.course != null)
                      _Info(icon: Icons.school_outlined, text: c.course!.name),
                    if (r.description != null &&
                        r.description!.trim().isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(
                        r.description!,
                        style: const TextStyle(
                          fontSize: 14,
                          height: 1.5,
                          color: AppColors.gray700,
                        ),
                      ),
                    ],
                    if (r.cancelReason != null) ...[
                      const SizedBox(height: 10),
                      Text(
                        'Cancelled: ${r.cancelReason}',
                        style: const TextStyle(color: AppColors.red600),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              if (r.canJoin) ...[
                MbuiButton(
                  label: 'Join live class',
                  icon: Icons.sensors,
                  fullWidth: true,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) =>
                          LiveClassScreen(slug: c.slug, title: c.title),
                    ),
                  ),
                ),
              ] else if (c.status == 'scheduled')
                const Text(
                  'You can join here as soon as the host starts the class.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: AppColors.gray500),
                ),
              if (r.calendarUrl != null && c.status == 'scheduled') ...[
                const SizedBox(height: 12),
                MbuiButton(
                  label: 'Add to my calendar',
                  icon: Icons.event_available_outlined,
                  variant: MbuiVariant.secondary,
                  fullWidth: true,
                  onPressed: () async {
                    final opened = await launchUrl(
                      Uri.parse(r.calendarUrl!),
                      mode: LaunchMode.externalApplication,
                    );
                    if (!opened && context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Could not open your calendar app.')),
                      );
                    }
                  },
                ),
              ],
              if (r.recordings.isNotEmpty) ...[
                const SizedBox(height: 20),
                const MbuiSectionLabel('Recordings'),
                const SizedBox(height: 8),
                for (final recording in r.recordings)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: MbuiCard(
                      padding: const EdgeInsets.all(12),
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => _RecordingPlayerScreen(
                            recording: recording,
                            title: c.title,
                          ),
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.play_circle_outline,
                            color: AppColors.indigo600,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  recording.title,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray900,
                                  ),
                                ),
                                Text(
                                  [
                                    if (recording.startedAt != null)
                                      DateFormat(
                                        'd MMM yyyy, HH:mm',
                                      ).format(recording.startedAt!),
                                    if (recording.sizeLabel != null)
                                      recording.sizeLabel!,
                                  ].join(' · '),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray500,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Icon(
                            Icons.chevron_right,
                            color: AppColors.gray400,
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _RecordingPlayerScreen extends StatefulWidget {
  const _RecordingPlayerScreen({required this.recording, required this.title});
  final RoomRecording recording;
  final String title;

  @override
  State<_RecordingPlayerScreen> createState() => _RecordingPlayerScreenState();
}

class _RecordingPlayerScreenState extends State<_RecordingPlayerScreen> {
  VideoPlayerController? _controller;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final controller = VideoPlayerController.networkUrl(
      Uri.parse(widget.recording.streamUrl),
    );
    setState(() => _controller = controller);
    try {
      await controller.initialize();
      if (mounted) setState(() {});
    } catch (_) {
      if (mounted) setState(() => _failed = true);
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

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(widget.title, overflow: TextOverflow.ellipsis),
      ),
      body: Center(
        child: _failed
            ? OutlinedButton.icon(
                style: OutlinedButton.styleFrom(foregroundColor: Colors.white),
                onPressed: () => launchUrl(
                  Uri.parse(widget.recording.streamUrl),
                  mode: LaunchMode.externalApplication,
                ),
                icon: const Icon(Icons.open_in_new),
                label: const Text('Open recording'),
              )
            : c == null || !c.value.isInitialized
            ? const CircularProgressIndicator(color: Colors.white)
            : Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  AspectRatio(
                    aspectRatio: c.value.aspectRatio > 0
                        ? c.value.aspectRatio
                        : 16 / 9,
                    child: GestureDetector(
                      onTap: () => setState(
                        () => c.value.isPlaying ? c.pause() : c.play(),
                      ),
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          VideoPlayer(c),
                          if (!c.value.isPlaying)
                            const Icon(
                              Icons.play_circle_fill,
                              color: Colors.white,
                              size: 64,
                            ),
                        ],
                      ),
                    ),
                  ),
                  VideoProgressIndicator(
                    c,
                    allowScrubbing: true,
                    colors: const VideoProgressColors(
                      playedColor: AppColors.indigo500,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}

class _Info extends StatelessWidget {
  const _Info({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(
      children: [
        Icon(icon, size: 16, color: AppColors.gray400),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 13, color: AppColors.gray600),
          ),
        ),
      ],
    ),
  );
}
