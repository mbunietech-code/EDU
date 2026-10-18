import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/currency_repository.dart';
import '../theme/tokens.dart';

/// An order amount in TZS with its USD and CNY equivalents at today's rate,
/// one pill each, so people paying with Alipay / WeChat / PayPal know what to
/// enter.  [TZS 10,000] [≈ $3.80 USD] [≈ ¥25.50 CNY]
class AmountBreakdown extends ConsumerWidget {
  const AmountBreakdown({super.key, required this.amountTzs});

  final num amountTzs;

  /// The TZS amount from an "TZS 10,000" label, for API rows without `amount`.
  static double? parseTzs(String label) =>
      double.tryParse(label.replaceAll(RegExp(r'[^0-9.]'), ''));

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rates = ref.watch(currencyRatesValueProvider);

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        _Pill(
          text: 'TZS ${_grouped(amountTzs)}',
          color: AppColors.indigo700,
          background: AppColors.indigo50,
        ),
        _Pill(
          text: '≈ ${rates.usdLabel(amountTzs)} USD',
          color: AppColors.emerald700,
          background: AppColors.emerald50,
        ),
        _Pill(
          text: '≈ ${rates.cnyLabel(amountTzs)} CNY',
          color: AppColors.amber700,
          background: AppColors.amber50,
        ),
      ],
    );
  }

  static String _grouped(num v) => v
      .toStringAsFixed(0)
      .replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text, required this.color, required this.background});

  final String text;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.full),
      ),
      child: Text(
        text,
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}
