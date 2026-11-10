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
  const AdminList(
    this.items, {
    this.pending = 0,
    this.currentPage = 1,
    this.lastPage = 1,
    this.total,
  });
  final List<T> items;
  final int pending;
  final int currentPage;
  final int lastPage;
  final int? total;

  bool get hasMore => currentPage < lastPage;
}

/// One page of the admin chat inbox plus how many conversations each tab holds.
class AdminInbox {
  const AdminInbox(
    this.items, {
    this.counts = const {},
    this.unreadTotal = 0,
    this.currentPage = 1,
    this.lastPage = 1,
  });
  final List<AdminConversation> items;
  final Map<String, int> counts;
  final int unreadTotal;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

int _int(dynamic v, [int fallback = 0]) => (v as num?)?.toInt() ?? fallback;

class AdminRepository {
  AdminRepository(this._api);
  final ApiClient _api;

  // --- Orders ---
  /// [status]: pending, paid, confirmed, active, expired, renewed, rejected, cancelled.
  /// [kind]: subscription, software, vpn or tool. [type]: renewal or new.
  Future<AdminList<AdminOrder>> orders({
    String? status,
    String? kind,
    String? type,
    String? search,
    int page = 1,
  }) async {
    final data = await _api.get('/admin/orders', query: {
      'status': ?status,
      'kind': ?kind,
      'type': ?type,
      if (search != null && search.isNotEmpty) 'search': search,
      'page': page,
    }) as Map<String, dynamic>;
    final meta = data['meta'] as Map<String, dynamic>? ?? const {};
    return AdminList(
      (data['data'] as List)
          .map((e) => AdminOrder.fromJson(e as Map<String, dynamic>))
          .toList(),
      pending: _int(meta['pending']),
      currentPage: _int(meta['current_page'], page),
      lastPage: _int(meta['last_page'], page),
      total: (meta['total'] as num?)?.toInt(),
    );
  }

  Future<AdminOrder> order(int id) async => AdminOrder.fromJson(
      (await _api.get('/admin/orders/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<void> rejectOrder(int id, String reason) =>
      _api.post('/admin/orders/$id/reject', data: {'reason': reason});

  Future<void> updateOrder(int id, String? paymentInstructions) =>
      _api.put('/admin/orders/$id', data: {'payment_instructions': paymentInstructions});

  Future<void> deleteOrder(int id, String reason) =>
      _api.delete('/admin/orders/$id', data: {'reason': reason});

  Future<String> orderReceiptUrl(int id) async {
    final data = await _api.get('/admin/orders/$id/receipt-url') as Map<String, dynamic>;
    return data['url'] as String;
  }

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
  /// [status]: all, unread, awaiting, replied or online. [period]: today, 7d or 30d.
  /// [search] matches the customer's name, email or any message text.
  Future<AdminInbox> conversations({
    String status = 'all',
    String? period,
    String? search,
    int page = 1,
  }) async {
    final data = await _api.get('/admin/chat', query: {
      'status': status,
      'period': ?period,
      if (search != null && search.isNotEmpty) 'search': search,
      'page': page,
    }) as Map<String, dynamic>;
    final meta = data['meta'] as Map<String, dynamic>? ?? const {};
    final counts = (meta['counts'] as Map<String, dynamic>? ?? const {})
        .map((k, v) => MapEntry(k, _int(v)));
    return AdminInbox(
      _list(data, AdminConversation.fromJson),
      counts: counts,
      unreadTotal: _int(meta['unread_total']),
      currentPage: _int(meta['current_page'], page),
      lastPage: _int(meta['last_page'], page),
    );
  }

  Future<List<ChatMessage>> conversation(int id) async {
    final data = await _api.get('/admin/chat/$id') as Map<String, dynamic>;
    return (data['messages'] as List)
        .map((e) => ChatMessage.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<AdminReports> reports({int months = 6}) async => AdminReports.fromJson(
      await _api.get('/admin/reports', query: {'months': months}) as Map<String, dynamic>);

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
final adminOrderKindProvider = StateProvider<String?>((ref) => null);
final adminOrderTypeProvider = StateProvider<String?>((ref) => null);
final adminOrderSearchProvider = StateProvider<String>((ref) => '');
final adminPaymentFilterProvider = StateProvider<String?>((ref) => 'pending');

/// First page of orders for the current filters; the screen loads further pages itself.
final adminOrdersProvider = FutureProvider.autoDispose<AdminList<AdminOrder>>(
    (ref) => ref.watch(adminRepositoryProvider).orders(
          status: ref.watch(adminOrderFilterProvider),
          kind: ref.watch(adminOrderKindProvider),
          type: ref.watch(adminOrderTypeProvider),
          search: ref.watch(adminOrderSearchProvider),
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

final adminChatStatusProvider = StateProvider<String>((ref) => 'all');
final adminChatPeriodProvider = StateProvider<String?>((ref) => null);
final adminChatSearchProvider = StateProvider<String>((ref) => '');

/// First page of the chat inbox for the current tab, period and search.
final adminConversationsProvider = FutureProvider.autoDispose<AdminInbox>(
    (ref) => ref.watch(adminRepositoryProvider).conversations(
          status: ref.watch(adminChatStatusProvider),
          period: ref.watch(adminChatPeriodProvider),
          search: ref.watch(adminChatSearchProvider),
        ));

/// Months covered by the reports screen (3, 6, 12 or 24).
final adminReportsMonthsProvider = StateProvider.autoDispose<int>((ref) => 6);

final adminReportsProvider = FutureProvider.autoDispose<AdminReports>((ref) =>
    ref.watch(adminRepositoryProvider).reports(months: ref.watch(adminReportsMonthsProvider)));
