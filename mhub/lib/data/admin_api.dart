import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/admin.dart';
import '../models/tool.dart' show ChatMessage;

List<T> _list<T>(dynamic data, T Function(Map<String, dynamic>) fromJson) {
  final items = (data as Map<String, dynamic>)['data'] as List;
  return items.map((e) => fromJson(e as Map<String, dynamic>)).toList();
}

/// A list plus the `meta.pending` counter the admin queues show as a badge.
class AdminList<T> {
  const AdminList(this.items, {this.pending = 0});
  final List<T> items;
  final int pending;
}

class AdminRepository {
  AdminRepository(this._api);
  final ApiClient _api;

  // --- Orders ---
  Future<AdminList<AdminOrder>> orders({String? status, String? search}) async {
    final data = await _api.get('/admin/orders', query: {
      'status': ?status,
      if (search != null && search.isNotEmpty) 'search': search,
    }) as Map<String, dynamic>;
    return AdminList(
      (data['data'] as List)
          .map((e) => AdminOrder.fromJson(e as Map<String, dynamic>))
          .toList(),
      pending: (data['meta']?['pending'] as num?)?.toInt() ?? 0,
    );
  }

  Future<AdminOrder> order(int id) async => AdminOrder.fromJson(
      (await _api.get('/admin/orders/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<void> rejectOrder(int id, String reason) =>
      _api.post('/admin/orders/$id/reject', data: {'reason': reason});

  // --- Payments ---
  Future<AdminList<AdminPayment>> payments({String? status}) async {
    final data = await _api.get('/admin/payments', query: {
      'status': ?status,
    }) as Map<String, dynamic>;
    return AdminList(
      (data['data'] as List)
          .map((e) => AdminPayment.fromJson(e as Map<String, dynamic>))
          .toList(),
      pending: (data['meta']?['pending'] as num?)?.toInt() ?? 0,
    );
  }

  Future<AdminPayment> payment(int id) async => AdminPayment.fromJson(
      (await _api.get('/admin/payments/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<void> approvePayment(int id) => _api.post('/admin/payments/$id/approve');

  Future<void> rejectPayment(int id, String? reason) => _api.post(
      '/admin/payments/$id/reject',
      data: {if (reason != null && reason.isNotEmpty) 'reason': reason});

  Future<List<int>> proofBytes(String path) => _api.getBytes(path);

  // --- Users ---
  Future<List<AdminUser>> users({String? search, String? status}) async =>
      _list(
        await _api.get('/admin/users', query: {
          if (search != null && search.isNotEmpty) 'search': search,
          'status': ?status,
        }),
        AdminUser.fromJson,
      );

  Future<AdminUser> user(int id) async => AdminUser.fromJson(
      (await _api.get('/admin/users/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<void> setUserStatus(int id, String status) =>
      _api.post('/admin/users/$id/status', data: {'status': status});

  // --- Chat ---
  Future<List<AdminConversation>> conversations({String? search}) async => _list(
        await _api.get('/admin/chat', query: {
          if (search != null && search.isNotEmpty) 'search': search,
        }),
        AdminConversation.fromJson,
      );

  Future<List<ChatMessage>> conversation(int id) async {
    final data = await _api.get('/admin/chat/$id') as Map<String, dynamic>;
    return (data['messages'] as List)
        .map((e) => ChatMessage.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<AdminReports> reports() async => AdminReports.fromJson(
      await _api.get('/admin/reports') as Map<String, dynamic>);

  Future<ChatMessage> replyToConversation(int id, String body) async {
    final data =
        await _api.post('/admin/chat/$id', data: {'body': body}) as Map<String, dynamic>;
    return ChatMessage.fromJson(data['data'] as Map<String, dynamic>);
  }
}

final adminRepositoryProvider =
    Provider((ref) => AdminRepository(ref.watch(apiClientProvider)));

// Filter state for the admin order/payment queues.
final adminOrderFilterProvider = StateProvider<String?>((ref) => null);
final adminPaymentFilterProvider = StateProvider<String?>((ref) => 'pending');

final adminOrdersProvider = FutureProvider.autoDispose<AdminList<AdminOrder>>(
    (ref) => ref.watch(adminRepositoryProvider).orders(
          status: ref.watch(adminOrderFilterProvider),
        ));

final adminOrderProvider = FutureProvider.autoDispose.family<AdminOrder, int>(
    (ref, id) => ref.watch(adminRepositoryProvider).order(id));

final adminPaymentsProvider = FutureProvider.autoDispose<AdminList<AdminPayment>>(
    (ref) => ref.watch(adminRepositoryProvider).payments(
          status: ref.watch(adminPaymentFilterProvider),
        ));

final adminPaymentProvider = FutureProvider.autoDispose.family<AdminPayment, int>(
    (ref, id) => ref.watch(adminRepositoryProvider).payment(id));

final adminUsersSearchProvider = StateProvider<String>((ref) => '');
final adminUsersProvider = FutureProvider.autoDispose<List<AdminUser>>((ref) =>
    ref.watch(adminRepositoryProvider).users(
          search: ref.watch(adminUsersSearchProvider),
        ));
final adminUserProvider = FutureProvider.autoDispose.family<AdminUser, int>(
    (ref, id) => ref.watch(adminRepositoryProvider).user(id));

final adminConversationsProvider =
    FutureProvider.autoDispose<List<AdminConversation>>(
        (ref) => ref.watch(adminRepositoryProvider).conversations());

final adminReportsProvider = FutureProvider.autoDispose<AdminReports>(
    (ref) => ref.watch(adminRepositoryProvider).reports());
