import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import 'learning_widgets.dart';

class LessonsScreen extends ConsumerStatefulWidget {
  const LessonsScreen({super.key});

  @override
  ConsumerState<LessonsScreen> createState() => _LessonsScreenState();
}

class _LessonsScreenState extends ConsumerState<LessonsScreen> {
  static const _statuses = {null: 'All', 'in_progress': 'In progress', 'not_started': 'Not started', 'completed': 'Completed'};

  String _search = '';
  String? _status;

  @override
  Widget build(BuildContext context) {
    final filter = (search: _search, status: _status);
    final lessons = ref.watch(lessonsProvider(filter));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Lessons')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search lessons'),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() => _search = v.trim()),
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final s in _statuses.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(s.value),
                      selected: _status == s.key,
                      onSelected: (_) => setState(() => _status = s.key),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: AsyncValueView<List<LessonCard>>(
              value: lessons,
              onRefresh: () async => ref.refresh(lessonsProvider(filter).future),
              data: (list) => ListView(
                padding: const EdgeInsets.all(16),
                children: list.isEmpty
                    ? [const EmptyNote('No lessons found.')]
                    : [for (final l in list) LessonTile(lesson: l)],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
