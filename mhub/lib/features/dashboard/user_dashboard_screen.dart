import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/stat_grid.dart';
import '../auth/auth_controller.dart';
import 'dashboard_repository.dart';

class UserDashboardScreen extends ConsumerWidget {
  const UserDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user;
    final async = ref.watch(userDashboardProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: const [NotificationBell(), SizedBox(width: 4)],
      ),
      body: AsyncValueView<DashboardData>(
        value: async,
        onRefresh: () async => ref.refresh(userDashboardProvider.future),
        data: (data) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiPageHeader(
              title: 'Welcome back${user != null ? ', ${user.name}' : ''}',
              subtitle: 'Your access and orders at a glance.',
            ),
            const SizedBox(height: 16),
            StatGrid(stats: data.stats),
            if (data.recent.isNotEmpty) ...[
              const SizedBox(height: 24),
              const MbuiSectionLabel('Recent activity'),
              const SizedBox(height: 10),
              MbuiCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < data.recent.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      _RecentTile(item: data.recent[i]),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RecentTile extends StatelessWidget {
  const _RecentTile({required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final status = item['status']?.toString();
    return ListTile(
      title: Text('${item['title'] ?? item['label'] ?? '—'}'),
      subtitle: item['subtitle'] != null ? Text('${item['subtitle']}') : null,
      trailing: status != null ? MbuiStatusBadge(status) : null,
    );
  }
}
