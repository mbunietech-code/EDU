import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/learning_api.dart';
import '../../models/learning.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import 'learning_widgets.dart';

/// Live classes: Live now / Upcoming / Past.
class RoomsScreen extends StatelessWidget {
  const RoomsScreen({super.key});

  static const _tabs = {'live': 'Live now', 'upcoming': 'Upcoming', 'completed': 'Past'};

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _tabs.length,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Live classes'),
          bottom: TabBar(
            labelColor: AppColors.indigo600,
            unselectedLabelColor: AppColors.gray500,
            indicatorColor: AppColors.indigo600,
            tabs: [for (final t in _tabs.values) Tab(text: t)],
          ),
        ),
        body: TabBarView(children: [for (final t in _tabs.keys) _RoomList(tab: t)]),
      ),
    );
  }
}

class _RoomList extends ConsumerWidget {
  const _RoomList({required this.tab});
  final String tab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rooms = ref.watch(roomsProvider(tab));

    return AsyncValueView<List<RoomCard>>(
      value: rooms,
      onRefresh: () async => ref.refresh(roomsProvider(tab).future),
      data: (list) => ListView(
        padding: const EdgeInsets.all(16),
        children: list.isEmpty
            ? [
                EmptyNote(switch (tab) {
                  'live' => 'No class is live right now.',
                  'upcoming' => 'No upcoming classes scheduled.',
                  _ => 'No past classes yet.',
                }),
              ]
            : [for (final r in list) RoomTile(room: r)],
      ),
    );
  }
}
