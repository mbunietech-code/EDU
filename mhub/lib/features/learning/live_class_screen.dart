import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livekit_client/livekit_client.dart';

import '../../core/api_client.dart';
import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';

/// A live class inside the app (self-hosted LiveKit). Joins with mic and
/// camera off; the student can turn them on only when the class allows it.
class LiveClassScreen extends ConsumerStatefulWidget {
  const LiveClassScreen({super.key, required this.slug, required this.title});
  final String slug;
  final String title;

  @override
  ConsumerState<LiveClassScreen> createState() => _LiveClassScreenState();
}

class _LiveClassScreenState extends ConsumerState<LiveClassScreen> {
  late final LearningRepository _repo;
  Room? _room;
  EventsListener<RoomEvent>? _events;
  LiveJoinConfig? _config;
  String? _error;
  bool _ended = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _repo = ref.read(learningRepositoryProvider);
    _join();
  }

  Future<void> _join() async {
    setState(() {
      _error = null;
      _ended = false;
    });

    try {
      final config = await _repo.join(widget.slug);
      final room = Room(roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true));
      final events = room.createListener()
        ..on<RoomDisconnectedEvent>((_) {
          if (mounted) setState(() => _ended = true);
        });

      await room.connect(
        config.serverUrl,
        config.token,
        connectOptions: ConnectOptions(
          rtcConfiguration: RTCConfiguration(
            iceServers: [
              for (final s in config.iceServers)
                RTCIceServer(urls: s.urls, username: s.username, credential: s.credential),
            ],
            iceTransportPolicy: config.relayOnly ? RTCIceTransportPolicy.relay : RTCIceTransportPolicy.all,
          ),
        ),
      );

      room.addListener(_onRoomChanged);
      if (!mounted) {
        await room.disconnect();
        return;
      }
      setState(() {
        _room = room;
        _events = events;
        _config = config;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Could not connect to the live class. Check your internet connection and try again.');
      }
    }
  }

  void _onRoomChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _toggleMic() async {
    final me = _room?.localParticipant;
    if (me == null) return;
    setState(() => _busy = true);
    try {
      await me.setMicrophoneEnabled(!me.isMicrophoneEnabled());
    } catch (_) {
      _snack('Microphone could not be turned on. Allow microphone access for MHub.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _toggleCamera() async {
    final me = _room?.localParticipant;
    if (me == null) return;
    setState(() => _busy = true);
    try {
      await me.setCameraEnabled(!me.isCameraEnabled());
    } catch (_) {
      _snack('Camera could not be turned on. Allow camera access for MHub.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String text) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _leave() async {
    await _hangUp();
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _hangUp() async {
    final room = _room;
    _room = null;
    room?.removeListener(_onRoomChanged);
    await _events?.dispose();
    await room?.disconnect();
    await room?.dispose();
    _repo.leave(widget.slug).catchError((_) {});
  }

  @override
  void dispose() {
    _hangUp();
    super.dispose();
  }

  /// Main stage: a shared screen first, then whoever is speaking, then any video.
  VideoTrack? _stageTrack(List<Participant> people) {
    for (final p in people) {
      for (final pub in p.videoTrackPublications) {
        if (pub.source == TrackSource.screenShareVideo && pub.track != null && !pub.muted) {
          return pub.track as VideoTrack;
        }
      }
    }
    final ordered = [...people]..sort((a, b) => (b.isSpeaking ? 1 : 0) - (a.isSpeaking ? 1 : 0));
    for (final p in ordered) {
      for (final pub in p.videoTrackPublications) {
        if (pub.track != null && !pub.muted) return pub.track as VideoTrack;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final room = _room;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(widget.title, overflow: TextOverflow.ellipsis),
      ),
      body: SafeArea(
        child: _error != null || _ended
            ? _Message(
                text: _ended ? 'The class has ended or you were disconnected.' : _error!,
                action: 'Try again',
                onAction: () async {
                  await _hangUp();
                  _join();
                },
              )
            : room == null
                ? const _Message(text: 'Joining the class...', loading: true)
                : _buildRoom(room),
      ),
    );
  }

  Widget _buildRoom(Room room) {
    final remotes = room.remoteParticipants.values.toList();
    final me = room.localParticipant;
    final everyone = <Participant>[...remotes, ?me];
    final stage = _stageTrack(remotes) ?? _stageTrack(everyone);
    final config = _config!;

    return Column(
      children: [
        Expanded(
          child: Container(
            color: Colors.black,
            alignment: Alignment.center,
            child: stage == null
                ? const Text('Waiting for the host to share video...', style: TextStyle(color: Colors.white54))
                : VideoTrackRenderer(stage, fit: VideoViewFit.contain),
          ),
        ),
        SizedBox(
          height: 92,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            children: [for (final p in everyone) _ParticipantTile(participant: p, isMe: p == me)],
          ),
        ),
        Container(
          color: AppColors.gray900,
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              if (config.canSpeak)
                _RoundButton(
                  icon: (me?.isMicrophoneEnabled() ?? false) ? Icons.mic : Icons.mic_off,
                  label: (me?.isMicrophoneEnabled() ?? false) ? 'Mute' : 'Unmute',
                  onTap: _busy ? null : _toggleMic,
                ),
              if (config.canVideo)
                _RoundButton(
                  icon: (me?.isCameraEnabled() ?? false) ? Icons.videocam : Icons.videocam_off,
                  label: (me?.isCameraEnabled() ?? false) ? 'Stop video' : 'Start video',
                  onTap: _busy ? null : _toggleCamera,
                ),
              _RoundButton(
                icon: Icons.people_alt_outlined,
                label: '${everyone.length} here',
                onTap: null,
              ),
              _RoundButton(icon: Icons.call_end, label: 'Leave', color: AppColors.red600, onTap: _leave),
            ],
          ),
        ),
      ],
    );
  }
}

class _ParticipantTile extends StatelessWidget {
  const _ParticipantTile({required this.participant, required this.isMe});
  final Participant participant;
  final bool isMe;

  @override
  Widget build(BuildContext context) {
    VideoTrack? camera;
    for (final pub in participant.videoTrackPublications) {
      if (pub.source == TrackSource.camera && pub.track != null && !pub.muted) camera = pub.track as VideoTrack;
    }
    final name = isMe ? 'You' : (participant.name.isNotEmpty ? participant.name : participant.identity);

    return Container(
      width: 110,
      margin: const EdgeInsets.symmetric(horizontal: 4),
      decoration: BoxDecoration(
        color: AppColors.gray800,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: participant.isSpeaking ? AppColors.emerald600 : Colors.transparent, width: 2),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned.fill(
            child: camera != null
                ? VideoTrackRenderer(camera, fit: VideoViewFit.cover)
                : Center(
                    child: CircleAvatar(
                      backgroundColor: AppColors.indigo600,
                      child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    ),
                  ),
          ),
          Positioned(
            left: 4,
            right: 4,
            bottom: 3,
            child: Row(
              children: [
                if (participant.isMuted) const Icon(Icons.mic_off, size: 12, color: Colors.white70),
                Expanded(
                  child: Text(name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Colors.white, fontSize: 11, shadows: [Shadow(blurRadius: 3)])),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({required this.icon, required this.label, required this.onTap, this.color});
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Material(
            color: color ?? AppColors.gray700,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onTap,
              child: Padding(padding: const EdgeInsets.all(12), child: Icon(icon, color: Colors.white)),
            ),
          ),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
        ],
      );
}

class _Message extends StatelessWidget {
  const _Message({required this.text, this.action, this.onAction, this.loading = false});
  final String text;
  final String? action;
  final VoidCallback? onAction;
  final bool loading;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (loading) const CircularProgressIndicator(color: Colors.white),
              if (loading) const SizedBox(height: 16),
              Text(text, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70, fontSize: 14)),
              if (action != null) ...[
                const SizedBox(height: 16),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white),
                  onPressed: onAction,
                  child: Text(action!),
                ),
              ],
            ],
          ),
        ),
      );
}
