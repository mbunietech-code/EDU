import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'courses_screen.dart';
import 'learning_more_screens.dart';
import 'learning_widgets.dart';
import 'lessons_screen.dart';
import 'rooms_screen.dart';

/// Learning dashboard: live now, continue watching, my courses, upcoming
/// classes — the app version of /learn.
class LearningHomeScreen extends ConsumerWidget {
  const LearningHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final home = ref.watch(learningHomeProvider);

    void open(Widget screen) =>
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Learning')),
      body: AsyncValueView<LearningHome>(
        value: home,
        onRefresh: () async => ref.refresh(learningHomeProvider.future),
        data: (h) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                _Shortcut(
                  icon: Icons.search,
                  label: 'Search',
                  onTap: () => open(const LearningSearchScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.school_outlined,
                  label: 'Courses',
                  onTap: () => open(const CoursesScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.play_lesson_outlined,
                  label: 'Lessons',
                  onTap: () => open(const LessonsScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.sensors,
                  label: 'Live classes',
                  onTap: () => open(const RoomsScreen()),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _Shortcut(
                  icon: Icons.category_outlined,
                  label: 'Categories',
                  onTap: () => open(const CategoriesScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.people_outline,
                  label: 'Instructors',
                  onTap: () => open(const InstructorsScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.calendar_month_outlined,
                  label: 'Calendar',
                  onTap: () => open(const LearningCalendarScreen()),
                ),
                const SizedBox(width: 8),
                _Shortcut(
                  icon: Icons.insights_outlined,
                  label: 'Progress',
                  onTap: () => open(const LearningProgressScreen()),
                ),
              ],
            ),
            if (h.live.isNotEmpty) ...[
              const SectionHeader('Live now'),
              for (final r in h.live) RoomTile(room: r),
            ],
            if (h.continueWatching.isNotEmpty) ...[
              const SectionHeader('Continue watching'),
              for (final l in h.continueWatching) LessonTile(lesson: l),
            ],
            SectionHeader(
              'My courses',
              action: 'All courses',
              onAction: () => open(const CoursesScreen()),
            ),
            if (h.myCourses.isEmpty)
              const EmptyNote(
                'You have not joined a course yet. Browse courses to get started.',
              )
            else
              for (final c in h.myCourses) CourseTile(course: c),
            if (h.upcoming.isNotEmpty) ...[
              SectionHeader(
                'Upcoming live classes',
                action: 'See all',
                onAction: () => open(const RoomsScreen()),
              ),
              for (final r in h.upcoming) RoomTile(room: r),
            ],
            if (h.completed.isNotEmpty) ...[
              const SectionHeader('Completed lessons'),
              for (final l in h.completed) LessonTile(lesson: l),
            ],
          ],
        ),
      ),
    );
  }
}

class _Shortcut extends StatelessWidget {
  const _Shortcut({
    required this.icon,
    required this.label,
    required this.onTap,
  });
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: MbuiCard(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      onTap: onTap,
      child: Column(
        children: [
          Icon(icon, color: AppColors.indigo600),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.gray700,
            ),
          ),
        ],
      ),
    ),
  );
}
