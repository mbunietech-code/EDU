import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'learning_widgets.dart';

class LearningSearchScreen extends ConsumerStatefulWidget {
  const LearningSearchScreen({super.key});

  @override
  ConsumerState<LearningSearchScreen> createState() =>
      _LearningSearchScreenState();
}

class _LearningSearchScreenState extends ConsumerState<LearningSearchScreen> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final repo = ref.watch(learningRepositoryProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Search learning')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            autofocus: true,
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Search courses, lessons, live classes',
            ),
            onChanged: (value) => setState(() => _q = value.trim()),
          ),
          const SizedBox(height: 16),
          if (_q.isEmpty)
            const EmptyNote('Type something to search your learning library.')
          else
            FutureBuilder(
              future: Future.wait([
                repo.courses(search: _q),
                repo.lessons(search: _q),
                repo.rooms('all'),
                repo.instructors(search: _q),
              ]),
              builder: (context, snapshot) {
                if (!snapshot.hasData) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: CircularProgressIndicator(),
                    ),
                  );
                }
                final courses = snapshot.data![0] as List<CourseCard>;
                final lessons = snapshot.data![1] as List<LessonCard>;
                final rooms = (snapshot.data![2] as List<RoomCard>)
                    .where(
                      (r) => r.title.toLowerCase().contains(_q.toLowerCase()),
                    )
                    .toList();
                final instructors = snapshot.data![3] as List<InstructorCard>;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (courses.isNotEmpty) ...[
                      const SectionHeader('Courses'),
                      for (final c in courses) CourseTile(course: c),
                    ],
                    if (lessons.isNotEmpty) ...[
                      const SectionHeader('Lessons'),
                      for (final l in lessons) LessonTile(lesson: l),
                    ],
                    if (rooms.isNotEmpty) ...[
                      const SectionHeader('Live classes'),
                      for (final r in rooms) RoomTile(room: r),
                    ],
                    if (instructors.isNotEmpty) ...[
                      const SectionHeader('Instructors'),
                      for (final i in instructors)
                        InstructorTile(instructor: i),
                    ],
                    if (courses.isEmpty &&
                        lessons.isEmpty &&
                        rooms.isEmpty &&
                        instructors.isEmpty)
                      const EmptyNote('No learning results found.'),
                  ],
                );
              },
            ),
        ],
      ),
    );
  }
}

class CategoriesScreen extends ConsumerWidget {
  const CategoriesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(title: const Text('Categories')),
    body: AsyncValueView<List<LearningCategoryCard>>(
      value: ref.watch(learningCategoriesProvider),
      onRefresh: () async => ref.refresh(learningCategoriesProvider.future),
      data: (items) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final c in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => CategoryDetailScreen(category: c),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.category_outlined,
                      color: AppColors.indigo600,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            c.name,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: AppColors.gray900,
                            ),
                          ),
                          Text(
                            '${c.coursesCount} courses · ${c.lessonsCount} lessons',
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.gray500,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right, color: AppColors.gray400),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}

class CategoryDetailScreen extends ConsumerWidget {
  const CategoryDetailScreen({super.key, required this.category});
  final LearningCategoryCard category;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(title: Text(category.name, overflow: TextOverflow.ellipsis)),
    body: AsyncValueView<LearningCategoryDetail>(
      value: ref.watch(learningCategoryProvider(category.slug)),
      onRefresh: () async =>
          ref.refresh(learningCategoryProvider(category.slug).future),
      data: (detail) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if ((detail.card.description ?? '').isNotEmpty)
            EmptyNote(detail.card.description!),
          if (detail.courses.isNotEmpty) ...[
            const SectionHeader('Courses'),
            for (final c in detail.courses) CourseTile(course: c),
          ],
          if (detail.lessons.isNotEmpty) ...[
            const SectionHeader('Lessons'),
            for (final l in detail.lessons) LessonTile(lesson: l),
          ],
          if (detail.rooms.isNotEmpty) ...[
            const SectionHeader('Live classes'),
            for (final r in detail.rooms) RoomTile(room: r),
          ],
        ],
      ),
    ),
  );
}

class InstructorsScreen extends ConsumerStatefulWidget {
  const InstructorsScreen({super.key});

  @override
  ConsumerState<InstructorsScreen> createState() => _InstructorsScreenState();
}

class _InstructorsScreenState extends ConsumerState<InstructorsScreen> {
  String _q = '';

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(title: const Text('Instructors')),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: TextField(
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Search instructors',
            ),
            onChanged: (value) => setState(() => _q = value.trim()),
          ),
        ),
        Expanded(
          child: AsyncValueView<List<InstructorCard>>(
            value: ref.watch(instructorsProvider(_q)),
            onRefresh: () async => ref.refresh(instructorsProvider(_q).future),
            data: (items) => ListView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              children: [for (final i in items) InstructorTile(instructor: i)],
            ),
          ),
        ),
      ],
    ),
  );
}

class InstructorDetailScreen extends ConsumerWidget {
  const InstructorDetailScreen({super.key, required this.instructor});
  final InstructorCard instructor;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(
      title: Text(instructor.name, overflow: TextOverflow.ellipsis),
    ),
    body: AsyncValueView<InstructorDetail>(
      value: ref.watch(instructorProvider(instructor.id)),
      onRefresh: () async =>
          ref.refresh(instructorProvider(instructor.id).future),
      data: (detail) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            child: Text(
              '${detail.card.coursesCount} courses · ${detail.card.lessonsCount} lessons · ${detail.card.roomsCount} live classes',
              style: const TextStyle(color: AppColors.gray700),
            ),
          ),
          if (detail.courses.isNotEmpty) ...[
            const SectionHeader('Courses'),
            for (final c in detail.courses) CourseTile(course: c),
          ],
          if (detail.lessons.isNotEmpty) ...[
            const SectionHeader('Lessons'),
            for (final l in detail.lessons) LessonTile(lesson: l),
          ],
          if (detail.rooms.isNotEmpty) ...[
            const SectionHeader('Live classes'),
            for (final r in detail.rooms) RoomTile(room: r),
          ],
        ],
      ),
    ),
  );
}

class InstructorTile extends StatelessWidget {
  const InstructorTile({super.key, required this.instructor});
  final InstructorCard instructor;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: MbuiCard(
      padding: const EdgeInsets.all(14),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => InstructorDetailScreen(instructor: instructor),
        ),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: AppColors.indigo50,
            child: Text(
              instructor.name.isNotEmpty
                  ? instructor.name[0].toUpperCase()
                  : '?',
              style: const TextStyle(
                color: AppColors.indigo600,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  instructor.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.gray900,
                  ),
                ),
                Text(
                  '${instructor.coursesCount} courses · ${instructor.lessonsCount} lessons',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.gray500,
                  ),
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

class LearningCalendarScreen extends ConsumerStatefulWidget {
  const LearningCalendarScreen({super.key});

  @override
  ConsumerState<LearningCalendarScreen> createState() =>
      _LearningCalendarScreenState();
}

class _LearningCalendarScreenState
    extends ConsumerState<LearningCalendarScreen> {
  late String _month = DateFormat('yyyy-MM').format(DateTime.now());

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(learningCalendarProvider(_month));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Learning calendar')),
      body: AsyncValueView<LearningCalendar>(
        value: value,
        onRefresh: () async =>
            ref.refresh(learningCalendarProvider(_month).future),
        data: (calendar) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                IconButton(
                  onPressed: () => setState(() => _month = calendar.prevMonth),
                  icon: const Icon(Icons.chevron_left),
                ),
                Expanded(
                  child: Text(
                    calendar.month,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  onPressed: () => setState(() => _month = calendar.nextMonth),
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
            if (calendar.events.isEmpty)
              const EmptyNote('No classes in this month.')
            else
              for (final room in calendar.events) RoomTile(room: room),
          ],
        ),
      ),
    );
  }
}

class LearningProgressScreen extends ConsumerWidget {
  const LearningProgressScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: AppColors.pageBackground,
    appBar: AppBar(title: const Text('My progress')),
    body: AsyncValueView<LearningProgressDashboard>(
      value: ref.watch(learningProgressProvider),
      onRefresh: () async => ref.refresh(learningProgressProvider.future),
      data: (p) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              _Stat(label: 'Courses', value: '${p.courses.length}'),
              const SizedBox(width: 8),
              _Stat(label: 'Completed', value: '${p.completedLessons.length}'),
              const SizedBox(width: 8),
              _Stat(label: 'Live time', value: _duration(p.attendedSeconds)),
            ],
          ),
          if (p.courses.isNotEmpty) ...[
            const SectionHeader('Course progress'),
            for (final c in p.courses) CourseTile(course: c),
          ],
          if (p.completedLessons.isNotEmpty) ...[
            const SectionHeader('Completed lessons'),
            for (final l in p.completedLessons) LessonTile(lesson: l),
          ],
          if (p.attendance.isNotEmpty) ...[
            const SectionHeader('Live attendance'),
            for (final a in p.attendance)
              if (a.room != null) RoomTile(room: a.room!),
          ],
        ],
      ),
    ),
  );

  static String _duration(int seconds) {
    final h = seconds ~/ 3600;
    final m = (seconds % 3600) ~/ 60;
    return h > 0 ? '${h}h ${m}m' : '${m}m';
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: MbuiCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.gray900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 11, color: AppColors.gray500),
          ),
        ],
      ),
    ),
  );
}
