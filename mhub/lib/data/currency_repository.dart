import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/currency.dart';

/// Live TZS->USD/CNY rates, fetched once and kept for the session.
/// Falls back to [CurrencyRates.fallback] if the request fails.
final currencyRatesProvider = FutureProvider<CurrencyRates>((ref) async {
  try {
    final data = await ref.watch(apiClientProvider).get('/currency-rates');
    return CurrencyRates.fromJson(data as Map<String, dynamic>);
  } on ApiException {
    return CurrencyRates.fallback;
  }
});

/// Non-async view: the loaded rates, or the fallback while loading.
final currencyRatesValueProvider = Provider<CurrencyRates>((ref) {
  return ref.watch(currencyRatesProvider).maybeWhen(
        data: (r) => r,
        orElse: () => CurrencyRates.fallback,
      );
});
