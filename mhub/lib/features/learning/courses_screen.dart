import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import 'learning_widgets.dart';

class CoursesScreen extends ConsumerStatefulWidget {
  const CoursesScreen({super.key});

  @override
  ConsumerState<CoursesScreen> createState() => _CoursesScreenState();
}

class _CoursesScreenState extends ConsumerState<CoursesScreen> {
  String _search = '';
  bool _mine = false;

  @override
  Widget build(BuildContext context) {
    final filter = (search: _search, mine: _mine);
    final courses = ref.watch(coursesProvider(filter));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Courses')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search courses'),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() => _search = v.trim()),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                ChoiceChip(label: const Text('All courses'), selected: !_mine, onSelected: (_) => setState(() => _mine = false)),
                const SizedBox(width: 8),
                ChoiceChip(label: const Text('My courses'), selected: _mine, onSelected: (_) => setState(() => _mine = true)),
              ],
            ),
          ),
          Expanded(
            child: AsyncValueView<List<CourseCard>>(
              value: courses,
              onRefresh: () async => ref.refresh(coursesProvider(filter).future),
              data: (list) => list.isEmpty
                  ? ListView(
                      padding: const EdgeInsets.all(16),
                      children: [EmptyNote(_mine ? 'You have not joined a course yet.' : 'No courses found.')],
                    )
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [for (final c in list) CourseTile(course: c)],
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
