import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class AdminReportsScreen extends ConsumerWidget {
  const AdminReportsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminReportsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Reports')),
      body: AsyncValueView<AdminReports>(
        value: async,
        onRefresh: () async => ref.refresh(adminReportsProvider.future),
        data: (r) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Wrap(
              spacing: 8,
              children: [
                for (final m in const [3, 6, 12, 24])
                  ChoiceChip(
                    label: Text('$m months'),
                    selected: r.months == m,
                    onSelected: (_) => ref.read(adminReportsMonthsProvider.notifier).state = m,
                  ),
              ],
            ),
            const SizedBox(height: 12),
            MbuiCard(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(child: _Total('Revenue · ${r.months} months', _tzs(r.revenueTotal))),
                  Expanded(child: _Total('Orders · ${r.months} months', '${r.ordersTotal}')),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Overview'),
            const SizedBox(height: 8),
            LayoutBuilder(builder: (context, c) {
              final cols = (c.maxWidth ~/ 200).clamp(1, 4);
              return GridView.count(
                crossAxisCount: cols,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: cols == 1 ? 3.6 : 1.8,
                children: [
                  for (final m in r.metrics)
                    MbuiCard(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(m.value,
                              style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.gray900)),
                          const SizedBox(height: 2),
                          Text(m.label,
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500)),
                        ],
                      ),
                    ),
                ],
              );
            }),
            const SizedBox(height: 24),
            const MbuiSectionLabel('Revenue'),
            const SizedBox(height: 8),
            MbuiCard(
              child: r.revenue.isEmpty
                  ? const Text('No approved payments in this period.',
                      style: TextStyle(fontSize: 13, color: AppColors.gray500))
                  : _BarChart(
                      bars: [
                        for (final p in r.revenue.reversed)
                          _Bar(p.label, p.value, p.valueLabel),
                      ],
                    ),
            ),
            const SizedBox(height: 24),
            const MbuiSectionLabel('Orders by month'),
            const SizedBox(height: 8),
            if (r.orders.isEmpty)
              const MbuiCard(child: Text('No orders yet.'))
            else
              for (final o in r.orders)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(o.label,
                                  style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.gray900)),
                            ),
                            Text('${o.total} total',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.gray500)),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: [
                            for (final e in o.byStatus.entries)
                              MbuiBadge('${e.key}: ${e.value}',
                                  appearance: MbuiAppearance.neutral),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
            const SizedBox(height: 24),
            const MbuiSectionLabel('Top products'),
            const SizedBox(height: 8),
            MbuiCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  for (var i = 0; i < r.products.length; i++) ...[
                    if (i > 0) const Divider(height: 1),
                    _ItemRow(
                      item: r.products[i],
                      detail: '${r.products[i].orders} orders · ${r.products[i].subscriptions} subs',
                    ),
                  ],
                ],
              ),
            ),
            if (r.tools.isNotEmpty) ...[
              const SizedBox(height: 24),
              const MbuiSectionLabel('Research tools'),
              const SizedBox(height: 8),
              MbuiCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: [
                    for (var i = 0; i < r.tools.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      _ItemRow(item: r.tools[i], detail: '${r.tools[i].orders} orders'),
                    ],
                  ],
                ),
              ),
            ],
            if (r.subscriptions.isNotEmpty || r.accounts.isNotEmpty) ...[
              const SizedBox(height: 24),
              const MbuiSectionLabel('Subscriptions & accounts'),
              const SizedBox(height: 8),
              MbuiCard(
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final e in r.subscriptions.entries)
                      MbuiBadge('sub ${e.key}: ${e.value}',
                          appearance: MbuiAppearance.info),
                    for (final e in r.accounts.entries)
                      MbuiBadge('acct ${e.key}: ${e.value}',
                          appearance: MbuiAppearance.neutral),
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

String _tzs(num v) => 'TZS ${NumberFormat.decimalPattern().format(v.round())}';

class _Total extends StatelessWidget {
  const _Total(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.gray900)),
        ],
      );
}

/// A product or tool: name, status, orders and revenue in the period.
class _ItemRow extends StatelessWidget {
  const _ItemRow({required this.item, required this.detail});
  final AdminProductStat item;
  final String detail;

  @override
  Widget build(BuildContext context) => ListTile(
        dense: true,
        title: Text(item.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        subtitle: Text(
          [detail, if (item.status != null && item.status != 'published') item.status!].join(' · '),
          style: const TextStyle(fontSize: 12, color: AppColors.gray500),
        ),
        trailing: Text(_tzs(item.revenue), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
      );
}

class _Bar {
  const _Bar(this.label, this.value, this.valueLabel);
  final String label;
  final double value;
  final String valueLabel;
}

class _BarChart extends StatelessWidget {
  const _BarChart({required this.bars});
  final List<_Bar> bars;

  @override
  Widget build(BuildContext context) {
    final max = bars.fold<double>(1, (m, b) => b.value > m ? b.value : m);
    return Column(
      children: [
        for (final b in bars)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: [
                SizedBox(
                  width: 64,
                  child: Text(b.label,
                      style: const TextStyle(
                          fontSize: 11, color: AppColors.gray500)),
                ),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: (b.value / max).clamp(0.0, 1.0),
                      minHeight: 16,
                      backgroundColor: AppColors.gray100,
                      valueColor:
                          const AlwaysStoppedAnimation(AppColors.indigo500),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  width: 96,
                  child: Text(b.valueLabel,
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: AppColors.gray700)),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
