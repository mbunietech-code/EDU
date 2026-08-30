import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../widgets/async_value_view.dart';
import '../../widgets/stat_grid.dart';
import 'dashboard_repository.dart';

class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminDashboardProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Admin overview')),
      body: AsyncValueView<DashboardData>(
        value: async,
        onRefresh: () async => ref.refresh(adminDashboardProvider.future),
        data: (data) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            StatGrid(stats: data.stats),
            const SizedBox(height: 24),
            if (data.recent.isNotEmpty) ...[
              Text('Latest', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final item in data.recent)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.receipt_long),
                    title: Text('${item['title'] ?? '—'}'),
                    subtitle: item['subtitle'] != null
                        ? Text('${item['subtitle']}')
                        : null,
                    trailing: item['amount'] != null
                        ? Text('${item['amount']}')
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
