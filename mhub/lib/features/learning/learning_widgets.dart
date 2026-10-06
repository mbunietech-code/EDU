import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import 'course_detail_screen.dart';
import 'lesson_player_screen.dart';
import 'room_detail_screen.dart';

/// Square-ish thumbnail with a fallback icon.
class LearningThumb extends StatelessWidget {
  const LearningThumb({super.key, this.url, this.icon = Icons.play_circle_outline, this.width = 96, this.height = 60});

  final String? url;
  final IconData icon;
  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      width: width,
      height: height,
      color: AppColors.indigo50,
      alignment: Alignment.center,
      child: Icon(icon, color: AppColors.indigo600, size: 26),
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: url == null
          ? fallback
          : Image.network(url!, width: width, height: height, fit: BoxFit.cover, errorBuilder: (_, _, _) => fallback),
    );
  }
}

class ProgressLine extends StatelessWidget {
  const ProgressLine({super.key, required this.percent});
  final int percent;

  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.full),
        child: LinearProgressIndicator(
          value: (percent.clamp(0, 100)) / 100,
          minHeight: 5,
          backgroundColor: AppColors.gray200,
          color: percent >= 100 ? AppColors.emerald600 : AppColors.indigo600,
        ),
      );
}

class CourseTile extends StatelessWidget {
  const CourseTile({super.key, required this.course});
  final CourseCard course;

  @override
  Widget build(BuildContext context) {
    final p = course.progress;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => CourseDetailScreen(slug: course.slug, title: course.title),
        )),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            LearningThumb(url: course.thumbnailUrl, icon: Icons.school_outlined),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(course.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                  const SizedBox(height: 3),
                  Text(
                    [
                      if (course.instructor != null) course.instructor!.name,
                      if (course.lessonsCount != null) '${course.lessonsCount} lessons',
                      if (course.levelLabel != null) course.levelLabel!,
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                  ),
                  if (p != null && p.started) ...[
                    const SizedBox(height: 8),
                    ProgressLine(percent: p.percent),
                    const SizedBox(height: 3),
                    Text('${p.completed} of ${p.total} lessons done',
                        style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
                  ] else if (course.isEnrolled == true) ...[
                    const SizedBox(height: 6),
                    const MbuiBadge('Enrolled', appearance: MbuiAppearance.success),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class LessonTile extends StatelessWidget {
  const LessonTile({super.key, required this.lesson, this.index});
  final LessonCard lesson;
  final int? index;

  @override
  Widget build(BuildContext context) {
    final p = lesson.progress;
    final done = p?.completed ?? false;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        padding: const EdgeInsets.all(10),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => LessonPlayerScreen(slug: lesson.slug, title: lesson.title),
        )),
        child: Row(
          children: [
            LearningThumb(url: lesson.thumbnailUrl, width: 84, height: 50),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${index != null ? '$index. ' : ''}${lesson.title}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.gray900)),
                  const SizedBox(height: 3),
                  Text(
                    [
                      if (lesson.durationLabel != null) lesson.durationLabel!,
                      if (lesson.course != null && index == null) lesson.course!.name,
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11, color: AppColors.gray500),
                  ),
                  if (p != null && !done && p.percent > 0) ...[
                    const SizedBox(height: 6),
                    ProgressLine(percent: p.percent),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(done ? Icons.check_circle : Icons.play_circle_fill,
                color: done ? AppColors.emerald600 : AppColors.indigo600, size: 24),
          ],
        ),
      ),
    );
  }
}

class RoomTile extends StatelessWidget {
  const RoomTile({super.key, required this.room});
  final RoomCard room;

  @override
  Widget build(BuildContext context) {
    final when = room.scheduledAt == null ? null : DateFormat('EEE d MMM, HH:mm').format(room.scheduledAt!);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => RoomDetailScreen(slug: room.slug, title: room.title),
        )),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: room.isLive ? AppColors.red50 : AppColors.indigo50,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(room.isLive ? Icons.sensors : Icons.event_outlined,
                  color: room.isLive ? AppColors.red600 : AppColors.indigo600),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(room.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                  const SizedBox(height: 3),
                  Text(
                    [
                      if (room.isLive) 'Live now' else ?when,
                      if (room.host != null) room.host!.name,
                    ].join(' · '),
                    style: TextStyle(
                        fontSize: 11,
                        color: room.isLive ? AppColors.red600 : AppColors.gray500,
                        fontWeight: room.isLive ? FontWeight.w700 : FontWeight.w400),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AppColors.gray400),
          ],
        ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.action, this.onAction});
  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 12, bottom: 8),
        child: Row(
          children: [
            Expanded(child: MbuiSectionLabel(title)),
            if (action != null) TextButton(onPressed: onAction, child: Text(action!)),
          ],
        ),
      );
}

class EmptyNote extends StatelessWidget {
  const EmptyNote(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => MbuiCard(
        child: Text(text, style: const TextStyle(fontSize: 13, color: AppColors.gray500)),
      );
}
