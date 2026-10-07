import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../data/admin_accounts_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_accounts_screen.dart';
import 'admin_common.dart';

final _date = DateFormat('d MMM yyyy');

/// The AI plans MbunieEduHub pays the providers for: when each was bought,
/// when it ends and how many days are left (soonest first).
class AiPlansScreen extends ConsumerStatefulWidget {
  const AiPlansScreen({super.key});

  @override
  ConsumerState<AiPlansScreen> createState() => _AiPlansScreenState();
}

class _AiPlansScreenState extends ConsumerState<AiPlansScreen> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(aiPlansProvider(_filter));
    final page = async.valueOrNull;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('AI plans we bought')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AccountFormScreen()));
          ref.invalidate(aiPlansProvider);
        },
        icon: const Icon(Icons.add),
        label: const Text('Add a plan'),
      ),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: {
              'all': 'All${page == null ? '' : ' (${page.total})'}',
              'expiring': 'Ending soon${page == null ? '' : ' (${page.expiring})'}',
              'expired': 'Ended${page == null ? '' : ' (${page.expired})'}',
              'active': 'Running${page == null ? '' : ' (${page.active})'}',
              'unknown': 'No end date${page == null ? '' : ' (${page.unknown})'}',
            },
            selected: _filter,
            onSelected: (v) => setState(() => _filter = v ?? 'all'),
          ),
          Expanded(
            child: AsyncValueView<AiPlansPage>(
              value: async,
              onRefresh: () async => ref.refresh(aiPlansProvider(_filter).future),
              data: (p) => ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                children: [
                  if (_filter == 'all') ...[
                    _Summary(page: p),
                    const SizedBox(height: 16),
                  ],
                  if (p.items.isEmpty)
                    const MbuiCard(
                      child: Text(
                        'No plans here yet. Open an account and fill in "Plan we bought" (bought on and ends on).',
                        style: TextStyle(color: AppColors.gray500),
                      ),
                    ),
                  for (final a in p.items)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _PlanCard(
                        account: a,
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => AccountDetailScreen(id: a.id)),
                          );
                          ref.invalidate(aiPlansProvider);
                        },
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.page});
  final AiPlansPage page;

  @override
  Widget build(BuildContext context) {
    Widget tile(String label, String value, Color color) => Expanded(
          child: MbuiCard(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
                const SizedBox(height: 2),
                Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: color)),
              ],
            ),
          ),
        );

    final paid = page.costByCurrency.entries
        .map((e) => '${e.key} ${NumberFormat.decimalPattern().format(e.key == 'TZS' ? e.value.round() : e.value)}')
        .join(' · ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(children: [
          tile('Plans', '${page.total}', AppColors.gray900),
          const SizedBox(width: 8),
          tile('Ending in ${page.expiringDays} days', '${page.expiring}',
              page.expiring > 0 ? AppColors.amber700 : AppColors.gray900),
          const SizedBox(width: 8),
          tile('Ended', '${page.expired}', page.expired > 0 ? AppColors.red600 : AppColors.gray900),
        ]),
        if (paid.isNotEmpty) ...[
          const SizedBox(height: 8),
          Text('Paid for these plans: $paid', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
        ],
      ],
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.account, required this.onTap});
  final VaultAccount account;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final a = account;
    final days = a.daysLeft;
    final (label, appearance, barColor) = switch (a.planState) {
      'expired' => ('Ended ${days!.abs()} day${days.abs() == 1 ? '' : 's'} ago', MbuiAppearance.danger, AppColors.red600),
      'expiring' => (days == 0 ? 'Ends today' : '$days day${days == 1 ? '' : 's'} left', MbuiAppearance.warning, AppColors.amber700),
      'active' => ('$days days left', MbuiAppearance.success, AppColors.emerald600),
      _ => ('No end date', MbuiAppearance.neutral, AppColors.gray300),
    };

    double? used;
    if (a.purchasedAt != null && a.expiresAt != null && days != null) {
      final length = a.expiresAt!.difference(a.purchasedAt!).inDays.clamp(1, 100000);
      used = ((length - days.clamp(0, length)) / length).clamp(0.0, 1.0);
    }

    return MbuiCard(
      padding: const EdgeInsets.all(14),
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.product, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.gray900)),
                    Text(
                      [if ((a.planName ?? '').isNotEmpty) a.planName!, a.name].join(' · '),
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                    ),
                  ],
                ),
              ),
              MbuiBadge(label, appearance: appearance),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              _DateCol('Bought', a.purchasedAt),
              _DateCol('Ends', a.expiresAt, bold: true),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    if (a.cost != null)
                      Text(
                        '${a.costCurrency ?? 'TZS'} ${NumberFormat.decimalPattern().format(a.costCurrency == 'TZS' ? a.cost!.round() : a.cost)}',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    Text(
                      '${a.users} customer${a.users == 1 ? '' : 's'}${a.autoRenew ? ' · auto-renew' : ''}',
                      style: const TextStyle(fontSize: 11, color: AppColors.gray500),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (used != null) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: used,
                minHeight: 5,
                backgroundColor: AppColors.gray100,
                color: barColor,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _DateCol extends StatelessWidget {
  const _DateCol(this.label, this.date, {this.bold = false});
  final String label;
  final DateTime? date;
  final bool bold;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(right: 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
            Text(
              date == null ? '—' : _date.format(date!),
              style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w700 : FontWeight.w500, color: AppColors.gray900),
            ),
          ],
        ),
      );
}
