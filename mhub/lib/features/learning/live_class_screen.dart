import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livekit_client/livekit_client.dart';
import 'package:url_launcher/url_launcher.dart';

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
  LiveRoomFeed? _feed;
  String? _error;
  bool _ended = false;
  bool _busy = false;
  bool _panelOpen = false;
  bool _sending = false;
  _LivePanelTab _panelTab = _LivePanelTab.chat;
  Timer? _feedTimer;
  final _composeController = TextEditingController();

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
      final room = Room(
        roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
      );
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
                RTCIceServer(
                  urls: s.urls,
                  username: s.username,
                  credential: s.credential,
                ),
            ],
            iceTransportPolicy: config.relayOnly
                ? RTCIceTransportPolicy.relay
                : RTCIceTransportPolicy.all,
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
      _startFeedPolling();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Could not connect to the live class. Check your internet connection and try again.',
        );
      }
    }
  }

  void _onRoomChanged() {
    if (mounted) setState(() {});
  }

  void _startFeedPolling() {
    _feedTimer?.cancel();
    _loadFeed();
    _feedTimer = Timer.periodic(
      const Duration(seconds: 4),
      (_) => _loadFeed(silent: true),
    );
  }

  Future<void> _loadFeed({bool silent = false}) async {
    try {
      final feed = await _repo.liveFeed(widget.slug);
      if (!mounted) return;
      setState(() => _feed = feed);
    } on ApiException catch (e) {
      if (!silent) _snack(e.message);
    } catch (_) {
      if (!silent) _snack('Could not load live class updates.');
    }
  }

  Future<void> _sendPanelMessage() async {
    final text = _composeController.text.trim();
    if (text.isEmpty || _sending) return;

    final type = _panelTab == _LivePanelTab.questions ? 'question' : 'chat';
    setState(() => _sending = true);
    try {
      await _repo.sendLiveMessage(widget.slug, type: type, body: text);
      _composeController.clear();
      await _loadFeed(silent: true);
    } on ApiException catch (e) {
      _snack(e.message);
    } catch (_) {
      _snack('Could not send your message.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _toggleHand() async {
    final next = !(_feed?.handRaised ?? false);
    setState(() => _busy = true);
    try {
      await _repo.setHand(widget.slug, next);
      await _loadFeed(silent: true);
    } on ApiException catch (e) {
      _snack(e.message);
    } catch (_) {
      _snack('Could not update your hand status.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _votePoll(LivePoll poll, int option) async {
    setState(() => _busy = true);
    try {
      await _repo.votePoll(widget.slug, poll.id, option);
      await _loadFeed(silent: true);
    } on ApiException catch (e) {
      _snack(e.message);
    } catch (_) {
      _snack('Could not submit your poll answer.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openMaterial(LiveMaterial material) async {
    final url = material.downloadUrl;
    if (url == null || url.isEmpty) {
      _snack('This material is not ready to open.');
      return;
    }
    final opened = await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.externalApplication,
    );
    if (!opened) _snack('Could not open the material.');
  }

  Future<void> _toggleMic() async {
    final me = _room?.localParticipant;
    if (me == null) return;
    setState(() => _busy = true);
    try {
      await me.setMicrophoneEnabled(!me.isMicrophoneEnabled());
    } catch (_) {
      _snack(
        'Microphone could not be turned on. Allow microphone access for MHub.',
      );
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
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  Future<void> _leave() async {
    await _hangUp();
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _hangUp() async {
    _feedTimer?.cancel();
    _feedTimer = null;
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
    _composeController.dispose();
    super.dispose();
  }

  /// Main stage: a shared screen first, then whoever is speaking, then any video.
  VideoTrack? _stageTrack(List<Participant> people) {
    for (final p in people) {
      for (final pub in p.videoTrackPublications) {
        if (pub.source == TrackSource.screenShareVideo &&
            pub.track != null &&
            !pub.muted) {
          return pub.track as VideoTrack;
        }
      }
    }
    final ordered = [...people]
      ..sort((a, b) => (b.isSpeaking ? 1 : 0) - (a.isSpeaking ? 1 : 0));
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
                text: _ended
                    ? 'The class has ended or you were disconnected.'
                    : _error!,
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
                ? const Text(
                    'Waiting for the host to share video...',
                    style: TextStyle(color: Colors.white54),
                  )
                : VideoTrackRenderer(stage, fit: VideoViewFit.contain),
          ),
        ),
        SizedBox(
          height: 92,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            children: [
              for (final p in everyone)
                _ParticipantTile(participant: p, isMe: p == me),
            ],
          ),
        ),
        if (_panelOpen) _buildLivePanel(),
        Container(
          color: AppColors.gray900,
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                if (config.canSpeak)
                  _RoundButton(
                    icon: (me?.isMicrophoneEnabled() ?? false)
                        ? Icons.mic
                        : Icons.mic_off,
                    label: (me?.isMicrophoneEnabled() ?? false)
                        ? 'Mute'
                        : 'Unmute',
                    onTap: _busy ? null : _toggleMic,
                  ),
                if (config.canVideo)
                  _RoundButton(
                    icon: (me?.isCameraEnabled() ?? false)
                        ? Icons.videocam
                        : Icons.videocam_off,
                    label: (me?.isCameraEnabled() ?? false)
                        ? 'Stop video'
                        : 'Start video',
                    onTap: _busy ? null : _toggleCamera,
                  ),
                _RoundButton(
                  icon: Icons.chat_bubble_outline,
                  label: 'Chat',
                  selected: _panelOpen && _panelTab == _LivePanelTab.chat,
                  onTap: () => _openPanel(_LivePanelTab.chat),
                ),
                _RoundButton(
                  icon: Icons.help_outline,
                  label: 'Q&A',
                  selected: _panelOpen && _panelTab == _LivePanelTab.questions,
                  onTap: () => _openPanel(_LivePanelTab.questions),
                ),
                _RoundButton(
                  icon: (_feed?.handRaised ?? false)
                      ? Icons.front_hand
                      : Icons.back_hand_outlined,
                  label: (_feed?.handRaised ?? false) ? 'Lower' : 'Raise',
                  color: (_feed?.handRaised ?? false)
                      ? AppColors.amber700
                      : null,
                  onTap: _busy ? null : _toggleHand,
                ),
                _RoundButton(
                  icon: Icons.poll_outlined,
                  label: 'Polls',
                  selected: _panelOpen && _panelTab == _LivePanelTab.polls,
                  onTap: () => _openPanel(_LivePanelTab.polls),
                ),
                _RoundButton(
                  icon: Icons.folder_open_outlined,
                  label: 'Files',
                  selected: _panelOpen && _panelTab == _LivePanelTab.materials,
                  onTap: () => _openPanel(_LivePanelTab.materials),
                ),
                _RoundButton(
                  icon: Icons.people_alt_outlined,
                  label: '${_feed?.participantCount ?? everyone.length} here',
                  selected: _panelOpen && _panelTab == _LivePanelTab.people,
                  onTap: () => _openPanel(_LivePanelTab.people),
                ),
                _RoundButton(
                  icon: Icons.call_end,
                  label: 'Leave',
                  color: AppColors.red600,
                  onTap: _leave,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  void _openPanel(_LivePanelTab tab) {
    setState(() {
      if (_panelOpen && _panelTab == tab) {
        _panelOpen = false;
      } else {
        _panelOpen = true;
        _panelTab = tab;
      }
    });
    _loadFeed(silent: true);
  }

  Widget _buildLivePanel() {
    final feed = _feed;
    final title = switch (_panelTab) {
      _LivePanelTab.chat => 'Class chat',
      _LivePanelTab.questions => 'Questions',
      _LivePanelTab.polls => 'Polls',
      _LivePanelTab.materials => 'Materials',
      _LivePanelTab.people => 'People',
    };

    return Container(
      height: 260,
      decoration: const BoxDecoration(
        color: Colors.black,
        border: Border(top: BorderSide(color: AppColors.gray800)),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 8, 6),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                ),
                if (feed != null)
                  Text(
                    '${feed.handsCount} hands · ${feed.openQuestionsCount} open',
                    style: const TextStyle(color: Colors.white54, fontSize: 12),
                  ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: () => setState(() => _panelOpen = false),
                  icon: const Icon(
                    Icons.close,
                    color: Colors.white70,
                    size: 20,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: feed == null
                ? const Center(
                    child: CircularProgressIndicator(color: Colors.white),
                  )
                : switch (_panelTab) {
                    _LivePanelTab.chat => _MessagePanel(
                      messages: feed.chatMessages,
                      hint: feed.chatEnabled
                          ? 'Message the class'
                          : 'Chat is disabled',
                      controller: _composeController,
                      enabled: feed.chatEnabled && !_sending,
                      onSend: _sendPanelMessage,
                    ),
                    _LivePanelTab.questions => _MessagePanel(
                      messages: feed.questions,
                      hint: feed.questionsEnabled
                          ? 'Ask a question'
                          : 'Questions are disabled',
                      controller: _composeController,
                      enabled: feed.questionsEnabled && !_sending,
                      onSend: _sendPanelMessage,
                    ),
                    _LivePanelTab.polls => _PollPanel(
                      polls: feed.polls,
                      busy: _busy,
                      onVote: _votePoll,
                    ),
                    _LivePanelTab.materials => _MaterialsPanel(
                      materials: feed.materials,
                      onOpen: _openMaterial,
                    ),
                    _LivePanelTab.people => _PeoplePanel(
                      participants: feed.participants,
                    ),
                  },
          ),
        ],
      ),
    );
  }
}

enum _LivePanelTab { chat, questions, polls, materials, people }

class _MessagePanel extends StatelessWidget {
  const _MessagePanel({
    required this.messages,
    required this.hint,
    required this.controller,
    required this.enabled,
    required this.onSend,
  });

  final List<LiveRoomMessage> messages;
  final String hint;
  final TextEditingController controller;
  final bool enabled;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Expanded(
        child: messages.isEmpty
            ? const Center(
                child: Text(
                  'No messages yet.',
                  style: TextStyle(color: Colors.white54),
                ),
              )
            : ListView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                reverse: true,
                itemCount: messages.length,
                itemBuilder: (context, index) {
                  final message = messages[messages.length - 1 - index];
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(
                                message.user.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            if (message.isHost) ...[
                              const SizedBox(width: 6),
                              const Icon(
                                Icons.verified,
                                size: 13,
                                color: AppColors.emerald600,
                              ),
                            ],
                            if (message.isAnswered) ...[
                              const SizedBox(width: 6),
                              const Text(
                                'Answered',
                                style: TextStyle(
                                  color: AppColors.emerald600,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          message.body,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                enabled: enabled,
                minLines: 1,
                maxLines: 3,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: hint,
                  hintStyle: const TextStyle(color: Colors.white38),
                  isDense: true,
                  filled: true,
                  fillColor: AppColors.gray900,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    borderSide: const BorderSide(color: AppColors.gray700),
                  ),
                ),
                onSubmitted: (_) => enabled ? onSend() : null,
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              style: IconButton.styleFrom(
                backgroundColor: enabled
                    ? AppColors.indigo600
                    : AppColors.gray700,
              ),
              onPressed: enabled ? onSend : null,
              icon: const Icon(Icons.send, size: 18, color: Colors.white),
            ),
          ],
        ),
      ),
    ],
  );
}

class _PollPanel extends StatelessWidget {
  const _PollPanel({
    required this.polls,
    required this.busy,
    required this.onVote,
  });
  final List<LivePoll> polls;
  final bool busy;
  final void Function(LivePoll poll, int option) onVote;

  @override
  Widget build(BuildContext context) {
    if (polls.isEmpty) {
      return const Center(
        child: Text('No polls yet.', style: TextStyle(color: Colors.white54)),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      itemCount: polls.length,
      separatorBuilder: (_, _) => const Divider(color: AppColors.gray800),
      itemBuilder: (context, index) {
        final poll = polls[index];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              poll.question,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            for (var i = 0; i < poll.options.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: _PollOption(
                  label: poll.options[i],
                  count: poll.results[i] ?? 0,
                  total: poll.total,
                  selected: poll.myVote == i,
                  enabled: poll.isOpen && !busy,
                  onTap: () => onVote(poll, i),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _PollOption extends StatelessWidget {
  const _PollOption({
    required this.label,
    required this.count,
    required this.total,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final String label;
  final int count;
  final int total;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final pct = total <= 0 ? 0.0 : count / total;
    return InkWell(
      borderRadius: BorderRadius.circular(AppRadius.md),
      onTap: enabled ? onTap : null,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.gray900,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(
            color: selected ? AppColors.indigo500 : AppColors.gray800,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Positioned.fill(
              child: FractionallySizedBox(
                alignment: Alignment.centerLeft,
                widthFactor: pct.clamp(0, 1),
                child: ColoredBox(
                  color: selected ? AppColors.indigo800 : AppColors.gray800,
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
              child: Row(
                children: [
                  Icon(
                    selected
                        ? Icons.radio_button_checked
                        : Icons.radio_button_off,
                    color: selected ? Colors.white : Colors.white54,
                    size: 17,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      label,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                  Text(
                    '$count',
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MaterialsPanel extends StatelessWidget {
  const _MaterialsPanel({required this.materials, required this.onOpen});
  final List<LiveMaterial> materials;
  final void Function(LiveMaterial material) onOpen;

  @override
  Widget build(BuildContext context) {
    if (materials.isEmpty) {
      return const Center(
        child: Text(
          'No materials shared yet.',
          style: TextStyle(color: Colors.white54),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      itemCount: materials.length,
      separatorBuilder: (_, _) => const Divider(color: AppColors.gray800),
      itemBuilder: (context, index) {
        final material = materials[index];
        return ListTile(
          dense: true,
          contentPadding: EdgeInsets.zero,
          leading: const Icon(
            Icons.insert_drive_file_outlined,
            color: Colors.white70,
          ),
          title: Text(
            material.displayName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white),
          ),
          subtitle: Text(
            material.originalName ?? material.mime ?? 'Class material',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white54),
          ),
          trailing: const Icon(
            Icons.open_in_new,
            color: Colors.white70,
            size: 18,
          ),
          onTap: () => onOpen(material),
        );
      },
    );
  }
}

class _PeoplePanel extends StatelessWidget {
  const _PeoplePanel({required this.participants});
  final List<LiveParticipant> participants;

  @override
  Widget build(BuildContext context) {
    if (participants.isEmpty) {
      return const Center(
        child: Text(
          'No participant list yet.',
          style: TextStyle(color: Colors.white54),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      itemCount: participants.length,
      itemBuilder: (context, index) {
        final person = participants[index];
        return ListTile(
          dense: true,
          contentPadding: EdgeInsets.zero,
          leading: CircleAvatar(
            radius: 15,
            backgroundColor: person.isMe
                ? AppColors.indigo600
                : AppColors.gray700,
            child: Text(
              person.name.isNotEmpty ? person.name[0].toUpperCase() : '?',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          title: Text(
            person.isMe ? '${person.name} (you)' : person.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white),
          ),
          subtitle: Text(
            person.role,
            style: const TextStyle(color: Colors.white54),
          ),
          trailing: person.handRaised
              ? const Icon(
                  Icons.front_hand,
                  color: AppColors.amber700,
                  size: 18,
                )
              : null,
        );
      },
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
      if (pub.source == TrackSource.camera && pub.track != null && !pub.muted) {
        camera = pub.track as VideoTrack;
      }
    }
    final name = isMe
        ? 'You'
        : (participant.name.isNotEmpty
              ? participant.name
              : participant.identity);

    return Container(
      width: 110,
      margin: const EdgeInsets.symmetric(horizontal: 4),
      decoration: BoxDecoration(
        color: AppColors.gray800,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(
          color: participant.isSpeaking
              ? AppColors.emerald600
              : Colors.transparent,
          width: 2,
        ),
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
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : '?',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
          ),
          Positioned(
            left: 4,
            right: 4,
            bottom: 3,
            child: Row(
              children: [
                if (participant.isMuted)
                  const Icon(Icons.mic_off, size: 12, color: Colors.white70),
                Expanded(
                  child: Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      shadows: [Shadow(blurRadius: 3)],
                    ),
                  ),
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
  const _RoundButton({
    required this.icon,
    required this.label,
    required this.onTap,
    this.color,
    this.selected = false,
  });
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final Color? color;
  final bool selected;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      Material(
        color: color ?? (selected ? AppColors.indigo600 : AppColors.gray700),
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Icon(icon, color: Colors.white),
          ),
        ),
      ),
      const SizedBox(height: 4),
      SizedBox(
        width: 64,
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: selected ? Colors.white : Colors.white70,
            fontSize: 11,
          ),
        ),
      ),
    ],
  );
}

class _Message extends StatelessWidget {
  const _Message({
    required this.text,
    this.action,
    this.onAction,
    this.loading = false,
  });
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
          Text(
            text,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white70, fontSize: 14),
          ),
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
