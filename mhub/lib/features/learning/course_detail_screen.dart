import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'learning_widgets.dart';
import 'lesson_player_screen.dart';

class CourseDetailScreen extends ConsumerStatefulWidget {
  const CourseDetailScreen({super.key, required this.slug, required this.title});
  final String slug;
  final String title;

  @override
  ConsumerState<CourseDetailScreen> createState() => _CourseDetailScreenState();
}

class _CourseDetailScreenState extends ConsumerState<CourseDetailScreen> {
  bool _enrolling = false;

  Future<void> _enroll() async {
    setState(() => _enrolling = true);
    try {
      final message = await ref.read(learningRepositoryProvider).enroll(widget.slug);
      ref.invalidate(courseDetailProvider(widget.slug));
      ref.invalidate(learningHomeProvider);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _enrolling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final course = ref.watch(courseDetailProvider(widget.slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(widget.title, overflow: TextOverflow.ellipsis)),
      body: AsyncValueView<CourseDetail>(
        value: course,
        onRefresh: () async => ref.refresh(courseDetailProvider(widget.slug).future),
        data: (c) {
          var number = 0;
          final p = c.card.progress;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiCard(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (c.card.thumbnailUrl != null) ...[
                      LearningThumb(url: c.card.thumbnailUrl, width: double.infinity, height: 170),
                      const SizedBox(height: 14),
                    ],
                    Text(c.card.title,
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.gray900)),
                    const SizedBox(height: 6),
                    Text(
                      [
                        if (c.card.instructor != null) 'By ${c.card.instructor!.name}',
                        if (c.card.levelLabel != null) c.card.levelLabel!,
                        if (c.card.lessonsCount != null) '${c.card.lessonsCount} lessons',
                      ].join(' · '),
                      style: const TextStyle(fontSize: 13, color: AppColors.gray500),
                    ),
                    if ((c.description ?? c.card.summary) != null) ...[
                      const SizedBox(height: 12),
                      Text((c.description ?? c.card.summary)!,
                          style: const TextStyle(fontSize: 14, height: 1.45, color: AppColors.gray700)),
                    ],
                    if (c.enrolled && p != null) ...[
                      const SizedBox(height: 14),
                      ProgressLine(percent: p.percent),
                      const SizedBox(height: 4),
                      Text('${p.completed} of ${p.total} lessons done · ${p.percent}%',
                          style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                    ],
                    const SizedBox(height: 16),
                    if (c.canEnroll)
                      MbuiButton(
                        label: 'Join this course',
                        icon: Icons.add_task,
                        fullWidth: true,
                        loading: _enrolling,
                        onPressed: _enroll,
                      )
                    else if (c.nextLessonSlug != null)
                      MbuiButton(
                        label: (p?.started ?? false) ? 'Continue: ${c.nextLessonTitle}' : 'Start: ${c.nextLessonTitle}',
                        icon: Icons.play_arrow_rounded,
                        fullWidth: true,
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => LessonPlayerScreen(slug: c.nextLessonSlug!, title: c.nextLessonTitle ?? ''),
                        )),
                      ),
                  ],
                ),
              ),
              if (c.rooms.isNotEmpty) ...[
                const SectionHeader('Live classes'),
                for (final r in c.rooms) RoomTile(room: r),
              ],
              for (final s in c.sections) ...[
                SectionHeader(s.title),
                for (final l in s.lessons) LessonTile(lesson: l, index: ++number),
              ],
              if (c.sections.isEmpty) ...[
                const SectionHeader('Lessons'),
                const EmptyNote('No lessons published yet.'),
              ],
            ],
          );
        },
      ),
    );
  }
}
