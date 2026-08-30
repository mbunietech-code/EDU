import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/payment.dart';
import '../models/scholarship.dart';
import '../models/subscription.dart';
import '../models/tool.dart';

List<T> _list<T>(dynamic data, T Function(Map<String, dynamic>) fromJson) {
  final items = (data as Map<String, dynamic>)['data'] as List;
  return items.map((e) => fromJson(e as Map<String, dynamic>)).toList();
}

// --- Payments --------------------------------------------------------------
class PaymentsRepository {
  PaymentsRepository(this._api);
  final ApiClient _api;

  Future<List<Payment>> list() async =>
      _list(await _api.get('/payments'), Payment.fromJson);

  Future<Payment> detail(int id) async => Payment.fromJson(
      (await _api.get('/payments/$id') as Map<String, dynamic>)['data'] as Map<String, dynamic>);

  Future<List<PaymentMethod>> methods() async =>
      _list(await _api.get('/payment-methods'), PaymentMethod.fromJson);

  Future<void> submit({
    required int orderId,
    required String method,
    required String proofPath,
    String? reference,
    String? note,
  }) async {
    final form = FormData.fromMap({
      'payment_method': method,
      if (reference != null && reference.isNotEmpty) 'transaction_reference': reference,
      if (note != null && note.isNotEmpty) 'note': note,
      'payment_proof': await MultipartFile.fromFile(proofPath),
    });
    await _api.post('/orders/$orderId/payments', data: form);
  }
}

final paymentsRepositoryProvider =
    Provider((ref) => PaymentsRepository(ref.watch(apiClientProvider)));
final paymentsProvider =
    FutureProvider.autoDispose((ref) => ref.watch(paymentsRepositoryProvider).list());
final paymentMethodsProvider =
    FutureProvider.autoDispose((ref) => ref.watch(paymentsRepositoryProvider).methods());

// --- Subscriptions ------------------------------------------------------
class SubscriptionsRepository {
  SubscriptionsRepository(this._api);
  final ApiClient _api;

  Future<List<Subscription>> list() async =>
      _list(await _api.get('/subscriptions'), Subscription.fromJson);
}

final subscriptionsRepositoryProvider =
    Provider((ref) => SubscriptionsRepository(ref.watch(apiClientProvider)));
final subscriptionsProvider =
    FutureProvider.autoDispose((ref) => ref.watch(subscriptionsRepositoryProvider).list());

// --- Scholarships ------------------------------------------------------
class ScholarshipsRepository {
  ScholarshipsRepository(this._api);
  final ApiClient _api;

  Future<List<Scholarship>> list() async =>
      _list(await _api.get('/scholarships'), Scholarship.fromJson);

  Future<Scholarship> detail(String slug) async => Scholarship.fromJson(
      (await _api.get('/scholarships/$slug') as Map<String, dynamic>)['data'] as Map<String, dynamic>);
}

final scholarshipsRepositoryProvider =
    Provider((ref) => ScholarshipsRepository(ref.watch(apiClientProvider)));
final scholarshipsProvider =
    FutureProvider.autoDispose((ref) => ref.watch(scholarshipsRepositoryProvider).list());
final scholarshipDetailProvider = FutureProvider.autoDispose
    .family<Scholarship, String>((ref, slug) => ref.watch(scholarshipsRepositoryProvider).detail(slug));

// --- Research tools ---------------------------------------------------
class ToolsRepository {
  ToolsRepository(this._api);
  final ApiClient _api;

  Future<List<Tool>> list() async => _list(await _api.get('/tools'), Tool.fromJson);

  Future<Tool> detail(String slug) async => Tool.fromJson(
      (await _api.get('/tools/$slug') as Map<String, dynamic>)['data'] as Map<String, dynamic>);
}

final toolsRepositoryProvider =
    Provider((ref) => ToolsRepository(ref.watch(apiClientProvider)));
final toolsProvider =
    FutureProvider.autoDispose((ref) => ref.watch(toolsRepositoryProvider).list());
final toolDetailProvider = FutureProvider.autoDispose
    .family<Tool, String>((ref, slug) => ref.watch(toolsRepositoryProvider).detail(slug));
