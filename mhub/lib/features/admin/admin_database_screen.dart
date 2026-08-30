import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_database_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

class AdminDatabaseScreen extends ConsumerStatefulWidget {
  const AdminDatabaseScreen({super.key});

  @override
  ConsumerState<AdminDatabaseScreen> createState() =>
      _AdminDatabaseScreenState();
}

class _AdminDatabaseScreenState extends ConsumerState<AdminDatabaseScreen> {
  bool _busy = false;

  Future<void> _run(Future<String> Function() fn) async {
    setState(() => _busy = true);
    try {
      final msg = await fn();
      ref.invalidate(adminDatabaseProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(msg)));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _apply(int count) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Apply $count schema change(s)?'),
        content: const Text(
            'This runs pending .sql migrations against the live database. '
            'Changes are designed to be safe and idempotent.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Apply')),
        ],
      ),
    );
    if (ok == true) {
      await _run(() => ref.read(adminDatabaseRepositoryProvider).apply());
    }
  }

  Future<void> _backup() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Create a full backup?'),
        content: const Text(
            'A complete .sql dump is generated and stored on the server. '
            'Download it from the Database page on the website.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Create backup')),
        ],
      ),
    );
    if (ok == true) {
      await _run(() => ref.read(adminDatabaseRepositoryProvider).backup());
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminDatabaseProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Database')),
      body: AsyncValueView<DbInfo>(
        value: async,
        onRefresh: () async => ref.refresh(adminDatabaseProvider.future),
        data: (db) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(db.database,
                            style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: AppColors.gray900)),
                      ),
                      MbuiStatusBadge(db.status == 'stable'
                          ? 'active'
                          : db.status == 'attention'
                              ? 'pending'
                              : 'rejected'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  AdminRow('Status', db.statusLabel),
                  AdminRow('Tables', '${db.tables}'),
                  AdminRow('Rows (est.)', '${db.rows}'),
                  AdminRow('Size', '${db.sizeMb} MB'),
                ],
              ),
            ),
            if (db.checks.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Health checks'),
              const SizedBox(height: 8),
              MbuiCard(
                child: Column(
                  children: [
                    for (final c in db.checks)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          children: [
                            Icon(
                              c.ok ? Icons.check_circle : Icons.error_outline,
                              size: 18,
                              color: c.ok
                                  ? AppColors.emerald600
                                  : AppColors.red600,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                c.detail == null
                                    ? c.label
                                    : '${c.label} — ${c.detail}',
                                style: const TextStyle(fontSize: 13),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            const MbuiSectionLabel('Pending schema changes'),
            const SizedBox(height: 8),
            if (db.pending.isEmpty)
              const MbuiCard(
                child: Text('Schema is up to date.',
                    style: TextStyle(fontSize: 13, color: AppColors.gray500)),
              )
            else ...[
              for (final p in db.pending)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Text(
                      '${p.filename}  ·  ${p.statements} statement(s)',
                      style: const TextStyle(
                          fontSize: 12, fontFamily: 'monospace'),
                    ),
                  ),
                ),
              const SizedBox(height: 4),
              MbuiButton(
                label: 'Apply ${db.pending.length} change(s)',
                icon: Icons.play_arrow,
                fullWidth: true,
                loading: _busy,
                onPressed: () => _apply(db.pending.length),
              ),
            ],
            const SizedBox(height: 20),
            const MbuiSectionLabel('Backups'),
            const SizedBox(height: 8),
            if (db.backups.isEmpty)
              const MbuiCard(
                child: Text('No backups created from the app yet.',
                    style: TextStyle(fontSize: 13, color: AppColors.gray500)),
              )
            else
              MbuiCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < db.backups.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      ListTile(
                        dense: true,
                        title: Text(db.backups[i].filename,
                            style: const TextStyle(
                                fontSize: 12, fontFamily: 'monospace')),
                        subtitle: Text(
                            '${(db.backups[i].size / 1024).toStringAsFixed(0)} KB',
                            style: const TextStyle(fontSize: 11)),
                      ),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 10),
            MbuiButton(
              label: 'Create full backup',
              variant: MbuiVariant.secondary,
              icon: Icons.backup_outlined,
              fullWidth: true,
              loading: _busy,
              onPressed: _backup,
            ),
            const SizedBox(height: 6),
            const Text(
              'Backups are stored on the server. Download the .sql file from the '
              'Database page on the website.',
              style: TextStyle(fontSize: 12, color: AppColors.gray500),
            ),
            if (db.history.isNotEmpty) ...[
              const SizedBox(height: 20),
              const MbuiSectionLabel('History'),
              const SizedBox(height: 8),
              MbuiCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < db.history.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      ListTile(
                        dense: true,
                        leading: Icon(
                          db.history[i].ok
                              ? Icons.check_circle
                              : Icons.error_outline,
                          size: 18,
                          color: db.history[i].ok
                              ? AppColors.emerald600
                              : AppColors.red600,
                        ),
                        title: Text(db.history[i].filename,
                            style: const TextStyle(
                                fontSize: 12, fontFamily: 'monospace')),
                        subtitle: db.history[i].error != null
                            ? Text(db.history[i].error!,
                                style: const TextStyle(
                                    fontSize: 11, color: AppColors.red700))
                            : (db.history[i].appliedAt != null
                                ? Text(db.history[i].appliedAt!,
                                    style: const TextStyle(fontSize: 11))
                                : null),
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
