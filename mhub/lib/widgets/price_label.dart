import 'package:flutter/material.dart';

import '../models/currency.dart';
import '../theme/tokens.dart';

/// A price shown in TZS + approximate USD + approximate CNY, each in its own
/// colour so the three are easy to tell apart.
///   TZS 50,000   ≈ $19   ≈ ¥128
class PriceLabel extends StatelessWidget {
  const PriceLabel({
    super.key,
    required this.amountTzs,
    required this.rates,
    this.prefix,
    this.tzsSize = 13,
    this.convSize = 11.5,
  });

  /// Raw TZS amount. If null or 0, only [freeText] is shown.
  final num? amountTzs;
  final CurrencyRates rates;

  /// e.g. "From " on catalogue cards.
  final String? prefix;
  final double tzsSize;
  final double convSize;

  static const _tzsColor = AppColors.indigo600;
  static const _usdColor = AppColors.emerald700;
  static const _cnyColor = AppColors.amber700;

  @override
  Widget build(BuildContext context) {
    final amount = amountTzs;
    if (amount == null || amount <= 0) {
      return Text(
        '${prefix ?? ''}Free',
        style: TextStyle(
            fontSize: tzsSize, fontWeight: FontWeight.w700, color: _tzsColor),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '${prefix ?? ''}TZS ${_grouped(amount)}',
          style: TextStyle(
              fontSize: tzsSize, fontWeight: FontWeight.w700, color: _tzsColor),
        ),
        const SizedBox(height: 2),
        Row(
          children: [
            Text('≈ ${rates.usdLabel(amount)}',
                style: TextStyle(
                    fontSize: convSize,
                    fontWeight: FontWeight.w600,
                    color: _usdColor)),
            Text('  ·  ',
                style: TextStyle(fontSize: convSize, color: AppColors.gray400)),
            Text('≈ ${rates.cnyLabel(amount)}',
                style: TextStyle(
                    fontSize: convSize,
                    fontWeight: FontWeight.w600,
                    color: _cnyColor)),
          ],
        ),
      ],
    );
  }

  static String _grouped(num v) => v
      .toStringAsFixed(0)
      .replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');
}
