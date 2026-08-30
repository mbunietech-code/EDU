import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_system_api.dart';
import '../../models/admin_system.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_accounts_screen.dart';
import 'admin_common.dart';
import 'admin_database_screen.dart';
import 'admin_form_kit.dart';
import 'admin_team_screen.dart';

class AdminSystemScreen extends ConsumerWidget {
  const AdminSystemScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final open = ref.watch(errorLogsProvider).asData?.value
            .where((e) => !e.resolved)
            .length ??
        0;

    final tiles = <_SysTile>[
      _SysTile('Error logs', Icons.bug_report_outlined, const ErrorLogsScreen(),
          badge: open),
      _SysTile('Subscriptions', Icons.autorenew, const AdminSubscriptionsScreen()),
      _SysTile('Shared accounts', Icons.vpn_key_outlined,
          const AdminAccountsScreen()),
      _SysTile('Payment methods', Icons.account_balance_wallet_outlined,
          const AdminPaymentMethodsScreen()),
      _SysTile('Contact messages', Icons.mail_outline,
          const ContactMessagesScreen()),
      _SysTile('Deleted records', Icons.restore_from_trash_outlined,
          const DeletedRecordsScreen()),
      _SysTile('Activity log', Icons.history, const ActivityLogScreen()),
      _SysTile('Team & roles', Icons.admin_panel_settings_outlined,
          const AdminTeamScreen()),
      _SysTile('Database', Icons.storage_outlined, const AdminDatabaseScreen()),
      _SysTile('Settings', Icons.settings_outlined, const AdminSettingsScreen()),
    ];

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('System')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final t in tiles)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                onTap: () => Navigator.of(context)
                    .push(MaterialPageRoute(builder: (_) => t.screen)),
                child: Row(
                  children: [
                    Icon(t.icon, color: AppColors.indigo600),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Text(t.label,
                          style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: AppColors.gray900)),
                    ),
                    if (t.badge > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.red600,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text('${t.badge}',
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.w700)),
                      ),
                    const SizedBox(width: 6),
                    const Icon(Icons.chevron_right, color: AppColors.gray400),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _SysTile {
  _SysTile(this.label, this.icon, this.screen, {this.badge = 0});
  final String label;
  final IconData icon;
  final Widget screen;
  final int badge;
}

// ======================================================== Error logs
class ErrorLogsScreen extends ConsumerWidget {
  const ErrorLogsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(errorLogsProvider);
    final filter = ref.watch(errorLogFilterProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Error logs')),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: const {'open': 'Open', 'resolved': 'Resolved', 'all': 'All'},
            selected: filter,
            onSelected: (v) =>
                ref.read(errorLogFilterProvider.notifier).state = v ?? 'open',
          ),
          const SizedBox(height: 4),
          Expanded(
            child: AsyncValueView<List<ErrorLogRow>>(
              value: async,
              onRefresh: () async => ref.refresh(errorLogsProvider.future),
              data: (items) {
                if (items.isEmpty) {
                  return const Center(
                    child: Text('No errors. ',
                        style: TextStyle(color: AppColors.gray500)),
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  itemBuilder: (context, i) {
                    final e = items[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => ErrorLogDetailScreen(id: e.id),
                        )),
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(e.exception,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                if (e.resolved)
                                  const MbuiStatusBadge('approved')
                                else
                                  MbuiBadge('${e.occurrences}×',
                                      appearance: MbuiAppearance.danger),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(e.message,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.gray600)),
                            if (e.location != null) ...[
                              const SizedBox(height: 4),
                              Text(e.location!,
                                  style: const TextStyle(
                                      fontSize: 11,
                                      fontFamily: 'monospace',
                                      color: AppColors.gray400)),
                            ],
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class ErrorLogDetailScreen extends ConsumerWidget {
  const ErrorLogDetailScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(errorLogProvider(id));

    Future<void> act(Future<void> Function() fn, String done) async {
      try {
        await fn();
        ref.invalidate(errorLogProvider(id));
        ref.invalidate(errorLogsProvider);
        if (context.mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(done)));
        }
      } on ApiException catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(e.message)));
        }
      }
    }

    final repo = ref.read(adminSystemRepositoryProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Error')),
      body: AsyncValueView<ErrorLogDetail>(
        value: async,
        onRefresh: () async => ref.refresh(errorLogProvider(id).future),
        data: (e) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiTitle(e.exception),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Message', e.message),
                  if (e.file != null)
                    AdminRow('Location', '${e.file}:${e.line}'),
                  if (e.method != null || e.url != null)
                    AdminRow('Request',
                        '${e.method ?? ''} ${e.url ?? ''}'.trim()),
                  AdminRow('Occurrences', '${e.occurrences}'),
                  if (e.userName != null)
                    AdminRow('User', '${e.userName} (${e.userEmail ?? ''})'),
                  AdminRow('Status', '',
                      valueWidget: MbuiStatusBadge(
                          e.resolved ? 'approved' : 'pending')),
                  if (e.resolvedBy != null)
                    AdminRow('Resolved by', e.resolvedBy!),
                ],
              ),
            ),
            if (e.trace != null) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Stack trace'),
              const SizedBox(height: 8),
              MbuiCard(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Text(e.trace!,
                      style: const TextStyle(
                          fontSize: 11, fontFamily: 'monospace', height: 1.5)),
                ),
              ),
            ],
            const SizedBox(height: 20),
            if (e.resolved)
              MbuiButton(
                label: 'Reopen',
                variant: MbuiVariant.secondary,
                fullWidth: true,
                onPressed: () => act(() => repo.reopenError(id), 'Reopened.'),
              )
            else
              MbuiButton(
                label: 'Mark resolved',
                variant: MbuiVariant.success,
                fullWidth: true,
                onPressed: () =>
                    act(() => repo.resolveError(id), 'Marked resolved.'),
              ),
            const SizedBox(height: 10),
            MbuiButton(
              label: 'Delete log',
              variant: MbuiVariant.danger,
              fullWidth: true,
              onPressed: () async {
                await act(() => repo.deleteError(id), 'Deleted.');
                if (context.mounted) Navigator.pop(context);
              },
            ),
          ],
        ),
      ),
    );
  }
}

// ======================================================== Deleted records
class DeletedRecordsScreen extends ConsumerWidget {
  const DeletedRecordsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(deletedRecordsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Deleted records')),
      body: AsyncValueView<List<DeletedRecordRow>>(
        value: async,
        onRefresh: () async => ref.refresh(deletedRecordsProvider.future),
        data: (items) {
          if (items.isEmpty) {
            return const Center(
                child: Text('Nothing has been deleted.',
                    style: TextStyle(color: AppColors.gray500)));
          }
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            itemBuilder: (context, i) {
              final r = items[i];
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => DeletedRecordDetailScreen(id: r.id),
                  )),
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(r.label,
                                style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray900)),
                          ),
                          MbuiBadge(r.entity),
                        ],
                      ),
                      if (r.reason != null) ...[
                        const SizedBox(height: 4),
                        Text(r.reason!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontSize: 12, color: AppColors.gray600)),
                      ],
                      const SizedBox(height: 4),
                      Text('${r.deletedBy ?? "—"} · ${r.deletedAgo ?? ""}',
                          style: const TextStyle(
                              fontSize: 11, color: AppColors.gray400)),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class DeletedRecordDetailScreen extends ConsumerWidget {
  const DeletedRecordDetailScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(deletedRecordProvider(id));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Deleted record')),
      body: AsyncValueView<DeletedRecordDetail>(
        value: async,
        onRefresh: () async => ref.refresh(deletedRecordProvider(id).future),
        data: (r) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiTitle(r.label),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Type', r.entity),
                  if (r.reason != null) AdminRow('Reason', r.reason!),
                  if (r.deletedBy != null) AdminRow('Deleted by', r.deletedBy!),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Snapshot'),
            const SizedBox(height: 8),
            MbuiCard(
              child: Column(
                children: [
                  for (final entry in r.snapshot.entries)
                    AdminRow(entry.key, '${entry.value}'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ======================================================== Contact messages
class ContactMessagesScreen extends ConsumerWidget {
  const ContactMessagesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(contactMessagesProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Contact messages')),
      body: AsyncValueView<List<ContactMessageRow>>(
        value: async,
        onRefresh: () async => ref.refresh(contactMessagesProvider.future),
        data: (items) {
          if (items.isEmpty) {
            return const Center(
                child: Text('No messages.',
                    style: TextStyle(color: AppColors.gray500)));
          }
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            itemBuilder: (context, i) {
              final m = items[i];
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => ContactMessageDetailScreen(id: m.id),
                  )),
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (!m.isRead)
                            Container(
                              width: 8,
                              height: 8,
                              margin: const EdgeInsets.only(right: 8),
                              decoration: const BoxDecoration(
                                  color: AppColors.indigo600,
                                  shape: BoxShape.circle),
                            ),
                          Expanded(
                            child: Text(m.subject ?? '(no subject)',
                                style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray900)),
                          ),
                          if (m.createdAgo != null)
                            Text(m.createdAgo!,
                                style: const TextStyle(
                                    fontSize: 11, color: AppColors.gray400)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text('${m.name} · ${m.email}',
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.gray500)),
                      const SizedBox(height: 4),
                      Text(m.message,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.gray600)),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class ContactMessageDetailScreen extends ConsumerWidget {
  const ContactMessageDetailScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(contactMessageProvider(id));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Message')),
      body: AsyncValueView<ContactMessageRow>(
        value: async,
        onRefresh: () async => ref.refresh(contactMessageProvider(id).future),
        data: (m) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiTitle(m.subject ?? '(no subject)'),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('From', m.name),
                  AdminRow('Email', m.email),
                ],
              ),
            ),
            const SizedBox(height: 16),
            MbuiCard(
              child: Text(m.message,
                  style: const TextStyle(fontSize: 14, height: 1.5)),
            ),
            const SizedBox(height: 16),
            MbuiButton(
              label: 'Delete message',
              variant: MbuiVariant.danger,
              fullWidth: true,
              onPressed: () async {
                final reason = await promptReason(context,
                    title: 'Delete message',
                    actionLabel: 'Delete',
                    hint: 'Reason');
                if (reason == null || reason.isEmpty) return;
                try {
                  await ref
                      .read(adminSystemRepositoryProvider)
                      .deleteContactMessage(id, reason);
                  ref.invalidate(contactMessagesProvider);
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Message deleted.')));
                    Navigator.pop(context);
                  }
                } on ApiException catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context)
                        .showSnackBar(SnackBar(content: Text(e.message)));
                  }
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}

// ======================================================== Activity log
class ActivityLogScreen extends ConsumerWidget {
  const ActivityLogScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(activityProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Activity log')),
      body: AsyncValueView<List<ActivityRow>>(
        value: async,
        onRefresh: () async => ref.refresh(activityProvider.future),
        data: (items) => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: items.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, i) {
            final a = items[i];
            return MbuiCard(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(a.action.replaceAll('_', ' '),
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.gray900)),
                  const SizedBox(height: 2),
                  Text(
                    '${a.actor}${a.entity != null ? " · ${a.entity}" : ""} · ${a.createdAgo ?? ""}',
                    style: const TextStyle(fontSize: 11, color: AppColors.gray400),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

// ======================================================== Subscriptions
class AdminSubscriptionsScreen extends ConsumerWidget {
  const AdminSubscriptionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminSubscriptionsProvider);
    final status = ref.watch(adminSubStatusProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Subscriptions')),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: const {
              null: 'All',
              'active': 'Active',
              'expiring_soon': 'Expiring',
              'expired': 'Expired',
              'suspended': 'Suspended',
              'revoked': 'Revoked',
            },
            selected: status,
            onSelected: (v) =>
                ref.read(adminSubStatusProvider.notifier).state = v,
          ),
          const SizedBox(height: 4),
          Expanded(
            child: AsyncValueView<List<AdminSubscription>>(
              value: async,
              onRefresh: () async =>
                  ref.refresh(adminSubscriptionsProvider.future),
              data: (items) {
                if (items.isEmpty) {
                  return const Center(
                      child: Text('No subscriptions here.',
                          style: TextStyle(color: AppColors.gray500)));
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  itemBuilder: (context, i) {
                    final s = items[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) =>
                              AdminSubscriptionDetailScreen(id: s.id),
                        )),
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text('${s.product} · ${s.customerName}',
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                MbuiStatusBadge(s.status),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [
                                if (s.plan != null) s.plan!,
                                if (s.expiryDate != null) 'ends ${s.expiryDate}',
                                if (s.daysRemaining != null)
                                  '${s.daysRemaining}d left',
                              ].join(' · '),
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class AdminSubscriptionDetailScreen extends ConsumerStatefulWidget {
  const AdminSubscriptionDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<AdminSubscriptionDetailScreen> createState() =>
      _AdminSubscriptionDetailScreenState();
}

class _AdminSubscriptionDetailScreenState
    extends ConsumerState<AdminSubscriptionDetailScreen> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() fn, String done) async {
    setState(() => _busy = true);
    try {
      await fn();
      ref.invalidate(adminSubscriptionProvider(widget.id));
      ref.invalidate(adminSubscriptionsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(done)));
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

  Future<void> _extend() async {
    final controller = TextEditingController(text: '30');
    final days = await showDialog<int>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Extend subscription'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Days'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () =>
                Navigator.pop(ctx, int.tryParse(controller.text.trim())),
            child: const Text('Extend'),
          ),
        ],
      ),
    );
    if (days == null || days <= 0) return;
    await _run(
        () => ref
            .read(adminSystemRepositoryProvider)
            .extendSubscription(widget.id, days),
        'Extended by $days days.');
  }

  Future<void> _state(String state) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Set to "$state"?'),
        content: Text(state == 'revoked'
            ? 'The customer loses access and the account is released.'
            : 'This changes the subscription status.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm')),
        ],
      ),
    );
    if (ok != true) return;
    await _run(
        () => ref
            .read(adminSystemRepositoryProvider)
            .setSubscriptionState(widget.id, state),
        'Subscription $state.');
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminSubscriptionProvider(widget.id));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Subscription')),
      body: AsyncValueView<AdminSubscription>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminSubscriptionProvider(widget.id).future),
        data: (s) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: MbuiTitle(s.product)),
                MbuiStatusBadge(s.status),
              ],
            ),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Customer', s.customerName),
                  if (s.customerEmail != null)
                    AdminRow('Email', s.customerEmail!),
                  if (s.plan != null) AdminRow('Plan', s.plan!),
                  if (s.orderNumber != null)
                    AdminRow('Order', s.orderNumber!),
                  if (s.startDate != null) AdminRow('Started', s.startDate!),
                  if (s.expiryDate != null) AdminRow('Expires', s.expiryDate!),
                  if (s.daysRemaining != null)
                    AdminRow('Days left', '${s.daysRemaining}'),
                ],
              ),
            ),
            const SizedBox(height: 20),
            MbuiButton(
              label: 'Extend',
              icon: Icons.add,
              fullWidth: true,
              loading: _busy,
              onPressed: _extend,
            ),
            const SizedBox(height: 10),
            MbuiButton(
              label: 'Suspend',
              variant: MbuiVariant.secondary,
              fullWidth: true,
              onPressed: _busy ? null : () => _state('suspended'),
            ),
            const SizedBox(height: 10),
            MbuiButton(
              label: 'Revoke',
              variant: MbuiVariant.danger,
              fullWidth: true,
              onPressed: _busy ? null : () => _state('revoked'),
            ),
          ],
        ),
      ),
    );
  }
}

// ======================================================== Payment methods
class AdminPaymentMethodsScreen extends ConsumerWidget {
  const AdminPaymentMethodsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminPaymentMethodsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Payment methods')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const PaymentMethodFormScreen(),
        )),
        icon: const Icon(Icons.add),
        label: const Text('New'),
      ),
      body: AsyncValueView<List<AdminPaymentMethod>>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminPaymentMethodsProvider.future),
        data: (items) => ListView.builder(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          itemCount: items.length,
          itemBuilder: (context, i) {
            final m = items[i];
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => PaymentMethodFormScreen(method: m),
                )),
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(m.name,
                              style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.gray900)),
                          Text(m.code,
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500)),
                        ],
                      ),
                    ),
                    MbuiStatusBadge(m.enabled ? 'active' : 'inactive'),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class PaymentMethodFormScreen extends ConsumerStatefulWidget {
  const PaymentMethodFormScreen({super.key, this.method});
  final AdminPaymentMethod? method;

  @override
  ConsumerState<PaymentMethodFormScreen> createState() =>
      _PaymentMethodFormScreenState();
}

class _PaymentMethodFormScreenState
    extends ConsumerState<PaymentMethodFormScreen> {
  late final TextEditingController _code;
  late final TextEditingController _name;
  late final TextEditingController _desc;
  late final TextEditingController _account;
  late final TextEditingController _instructions;
  late final TextEditingController _sort;
  bool _enabled = true;
  bool _saving = false;

  AdminPaymentMethod? get m => widget.method;
  bool get isNew => m == null;

  @override
  void initState() {
    super.initState();
    _code = TextEditingController(text: m?.code ?? '');
    _name = TextEditingController(text: m?.name ?? '');
    _desc = TextEditingController(text: m?.description ?? '');
    _account = TextEditingController(text: m?.accountNumber ?? '');
    _instructions = TextEditingController(text: m?.instructions ?? '');
    _sort = TextEditingController(text: (m?.sortOrder ?? 0).toString());
    _enabled = m?.enabled ?? true;
  }

  @override
  void dispose() {
    for (final c in [_code, _name, _desc, _account, _instructions, _sort]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final body = <String, dynamic>{
      'name': _name.text.trim(),
      'description': _desc.text.trim(),
      'account_number': _account.text.trim(),
      'instructions': _instructions.text.trim(),
      'enabled': _enabled,
      'sort_order': int.tryParse(_sort.text.trim()) ?? 0,
      if (isNew) 'code': _code.text.trim(),
    };
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminSystemRepositoryProvider);
      if (isNew) {
        await repo.createPaymentMethod(body);
      } else {
        await repo.updatePaymentMethod(m!.id, body);
      }
      ref.invalidate(adminPaymentMethodsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(isNew ? 'Added.' : 'Saved.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdminFormScaffold(
      title: isNew ? 'New payment method' : m!.name,
      saveLabel: isNew ? 'Add method' : 'Save changes',
      saving: _saving,
      onSave: _save,
      children: [
        if (isNew)
          LabeledInput(
              label: 'Code',
              hint: 'Short unique id, e.g. voda, crdb',
              child: TextField(controller: _code)),
        LabeledInput(label: 'Name', child: TextField(controller: _name)),
        LabeledInput(
            label: 'Description',
            child: TextField(controller: _desc, maxLines: 2)),
        LabeledInput(
            label: 'Account number', child: TextField(controller: _account)),
        LabeledInput(
            label: 'Instructions',
            child: TextField(controller: _instructions, maxLines: 3)),
        LabeledInput(
            label: 'Sort order',
            child: TextField(
                controller: _sort, keyboardType: TextInputType.number)),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Enabled', style: TextStyle(fontSize: 14)),
          value: _enabled,
          onChanged: (v) => setState(() => _enabled = v),
        ),
        if (!isNew)
          ImageUploadField(
            label: 'QR code',
            currentUrl: m!.qrUrl,
            onUpload: (path) async {
              await ref
                  .read(adminSystemRepositoryProvider)
                  .uploadPaymentMethodQr(m!.id, path);
              ref.invalidate(adminPaymentMethodsProvider);
            },
          ),
      ],
    );
  }
}

// ======================================================== Settings
class AdminSettingsScreen extends ConsumerWidget {
  const AdminSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminSettingsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Settings')),
      body: AsyncValueView<AdminSettings>(
        value: async,
        onRefresh: () async => ref.refresh(adminSettingsProvider.future),
        data: (s) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const MbuiSectionLabel('Developer tools'),
            const SizedBox(height: 8),
            MbuiCard(
              child: _DevToggle(enabled: s.adminDebugEnabled),
            ),
            const SizedBox(height: 20),
            const MbuiSectionLabel('Branding'),
            const SizedBox(height: 8),
            MbuiCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      _BrandPreview(label: 'Logo', url: s.logoUrl),
                      const SizedBox(width: 20),
                      _BrandPreview(label: 'Favicon', url: s.faviconUrl),
                    ],
                  ),
                  if (s.note != null) ...[
                    const SizedBox(height: 12),
                    Text(s.note!,
                        style: const TextStyle(
                            fontSize: 12, color: AppColors.gray400)),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 20),
            const MbuiSectionLabel('App downloads'),
            const SizedBox(height: 8),
            MbuiCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  for (var i = 0; i < s.downloads.length; i++) ...[
                    if (i > 0) const Divider(height: 1),
                    ListTile(
                      dense: true,
                      title: Text(s.downloads[i].label),
                      subtitle: Text(s.downloads[i].version ?? 'not set',
                          style: const TextStyle(fontSize: 12)),
                      trailing: Icon(
                        s.downloads[i].hasFile || s.downloads[i].url != null
                            ? Icons.check_circle
                            : Icons.remove_circle_outline,
                        size: 18,
                        color: s.downloads[i].hasFile ||
                                s.downloads[i].url != null
                            ? AppColors.emerald600
                            : AppColors.gray300,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DevToggle extends ConsumerStatefulWidget {
  const _DevToggle({required this.enabled});
  final bool enabled;

  @override
  ConsumerState<_DevToggle> createState() => _DevToggleState();
}

class _DevToggleState extends ConsumerState<_DevToggle> {
  late bool _on = widget.enabled;
  bool _busy = false;

  Future<void> _set(bool v) async {
    setState(() {
      _on = v;
      _busy = true;
    });
    try {
      await ref.read(adminSystemRepositoryProvider).setDev(v);
      ref.invalidate(adminSettingsProvider);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _on = !v);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      title: const Text('Show error details to admins',
          style: TextStyle(fontSize: 14)),
      subtitle: const Text(
          'Signed-in admins see full exceptions. Visitors always see the '
          'friendly page.',
          style: TextStyle(fontSize: 12)),
      value: _on,
      onChanged: _busy ? null : _set,
    );
  }
}

class _BrandPreview extends StatelessWidget {
  const _BrandPreview({required this.label, this.url});
  final String label;
  final String? url;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
        const SizedBox(height: 6),
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: AppColors.gray100,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.gray200),
          ),
          clipBehavior: Clip.antiAlias,
          child: url == null
              ? const Icon(Icons.image_not_supported_outlined,
                  color: AppColors.gray400)
              : Image.network(url!,
                  fit: BoxFit.contain,
                  errorBuilder: (_, _, _) => const Icon(Icons.broken_image,
                      color: AppColors.gray400)),
        ),
      ],
    );
  }
}
