import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/order.dart';

class OrdersRepository {
  OrdersRepository(this._api);

  final ApiClient _api;

  Future<List<Order>> list() async {
    final data = await _api.get('/orders');
    final items = (data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Order> detail(int id) async {
    final data = await _api.get('/orders/$id');
    return Order.fromJson((data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<void> cancel(int id) async {
    await _api.post('/orders/$id/cancel');
  }
}

final ordersRepositoryProvider = Provider<OrdersRepository>((ref) {
  return OrdersRepository(ref.watch(apiClientProvider));
});

final ordersProvider = FutureProvider.autoDispose<List<Order>>((ref) {
  return ref.watch(ordersRepositoryProvider).list();
});

final orderDetailProvider =
    FutureProvider.autoDispose.family<Order, int>((ref, id) {
  return ref.watch(ordersRepositoryProvider).detail(id);
});
