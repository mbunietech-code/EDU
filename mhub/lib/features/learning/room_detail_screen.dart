import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

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
          final when = c.scheduledAt == null ? null : DateFormat('EEEE d MMMM yyyy, HH:mm').format(c.scheduledAt!);

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
                        MbuiBadge(c.isLive ? 'Live now' : c.statusLabel,
                            appearance: c.isLive ? MbuiAppearance.danger : MbuiAppearance.info),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(c.title,
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.gray900)),
                    const SizedBox(height: 10),
                    if (when != null) _Info(icon: Icons.event_outlined, text: when),
                    if (c.durationMinutes > 0) _Info(icon: Icons.schedule, text: '${c.durationMinutes} minutes'),
                    if (c.host != null) _Info(icon: Icons.person_outline, text: 'Host: ${c.host!.name}'),
                    if (c.course != null) _Info(icon: Icons.school_outlined, text: c.course!.name),
                    if (r.description != null && r.description!.trim().isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(r.description!, style: const TextStyle(fontSize: 14, height: 1.5, color: AppColors.gray700)),
                    ],
                    if (r.cancelReason != null) ...[
                      const SizedBox(height: 10),
                      Text('Cancelled: ${r.cancelReason}', style: const TextStyle(color: AppColors.red600)),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              if (r.canJoin && r.webUrl != null)
                MbuiButton(
                  label: 'Join live class',
                  icon: Icons.sensors,
                  fullWidth: true,
                  onPressed: () => launchUrl(Uri.parse(r.webUrl!), mode: LaunchMode.externalApplication),
                )
              else if (c.status == 'scheduled')
                const Text('You can join here as soon as the host starts the class.',
                    textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: AppColors.gray500)),
            ],
          );
        },
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
            Expanded(child: Text(text, style: const TextStyle(fontSize: 13, color: AppColors.gray600))),
          ],
        ),
      );
}
