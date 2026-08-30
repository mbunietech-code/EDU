import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../widgets/async_value_view.dart';
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
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: const [NotificationBell()],
      ),
      body: AsyncValueView<DashboardData>(
        value: async,
        onRefresh: () async => ref.refresh(userDashboardProvider.future),
        data: (data) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Karibu${user != null ? ', ${user.name}' : ''}',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 16),
            StatGrid(stats: data.stats),
            const SizedBox(height: 24),
            if (data.recent.isNotEmpty) ...[
              Text('Recent activity',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final item in data.recent)
                Card(
                  child: ListTile(
                    title: Text('${item['title'] ?? item['label'] ?? '—'}'),
                    subtitle: item['subtitle'] != null
                        ? Text('${item['subtitle']}')
                        : null,
                    trailing: item['status'] != null
                        ? Chip(label: Text('${item['status']}'))
                        : null,
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
