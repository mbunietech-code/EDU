/// TZS -> USD / CNY multipliers, so prices can be shown in all three
/// currencies (mirrors the website's currency-conversion line).
class CurrencyRates {
  const CurrencyRates({required this.usd, required this.cny});

  final double usd;
  final double cny;

  /// Sensible fallback (matches config/currency.php defaults) used before the
  /// live rates load or if the request fails.
  static const fallback = CurrencyRates(usd: 0.00038, cny: 0.00255);

  factory CurrencyRates.fromJson(Map<String, dynamic> j) {
    final d = (j['data'] as Map<String, dynamic>?) ?? j;
    return CurrencyRates(
      usd: (d['usd'] as num?)?.toDouble() ?? fallback.usd,
      cny: (d['cny'] as num?)?.toDouble() ?? fallback.cny,
    );
  }

  String usdLabel(num tzs) => '\$${_fmt(tzs * usd)}';
  String cnyLabel(num tzs) => '¥${_fmt(tzs * cny)}';

  static String _fmt(double v) {
    if (v >= 100) {
      return v.toStringAsFixed(0).replaceAllMapped(
          RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');
    }
    return v.toStringAsFixed(2);
  }
}
