import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/stat_grid.dart';
import 'dashboard_repository.dart';

class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminDashboardProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Admin overview'),
        actions: const [NotificationBell(), SizedBox(width: 4)],
      ),
      body: AsyncValueView<DashboardData>(
        value: async,
        onRefresh: () async => ref.refresh(adminDashboardProvider.future),
        data: (data) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const MbuiPageHeader(
              title: 'Overview',
              subtitle: 'Platform activity and key numbers.',
            ),
            const SizedBox(height: 16),
            StatGrid(stats: data.stats),
            if (data.recent.isNotEmpty) ...[
              const SizedBox(height: 24),
              const MbuiSectionLabel('Latest orders'),
              const SizedBox(height: 10),
              MbuiCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < data.recent.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      ListTile(
                        leading: const CircleAvatar(
                          backgroundColor: AppColors.indigo50,
                          child: Icon(Icons.receipt_long_outlined,
                              size: 18, color: AppColors.indigo600),
                        ),
                        title: Text('${data.recent[i]['title'] ?? '—'}'),
                        subtitle: data.recent[i]['subtitle'] != null
                            ? Text('${data.recent[i]['subtitle']}')
                            : null,
                        trailing: data.recent[i]['amount'] != null
                            ? Text('${data.recent[i]['amount']}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.gray900,
                                ))
                            : null,
                      ),
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
