import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

/// Rows from the Mbunie VPN control plane are passed through as JSON maps,
/// exactly as the web admin (admin/vpn) receives them.
typedef VpnRow = Map<String, dynamic>;

/// One page of a VPN list: `meta` = {page, last_page, total}.
class VpnPage {
  const VpnPage(this.items, {this.page = 1, this.lastPage = 1, this.total = 0});
  final List<VpnRow> items;
  final int page;
  final int lastPage;
  final int total;

  bool get hasMore => page < lastPage;

  factory VpnPage.fromJson(Map<String, dynamic> j) {
    final meta = j['meta'] as Map<String, dynamic>? ?? const {};
    final items = (j['data'] as List? ?? const [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    return VpnPage(
      items,
      page: (meta['page'] as num?)?.toInt() ?? 1,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
      total: (meta['total'] as num?)?.toInt() ?? items.length,
    );
  }
}

class AdminVpnRepository {
  AdminVpnRepository(this._api);
  final ApiClient _api;

  Future<Map<String, dynamic>> overview() async =>
      Map<String, dynamic>.from(await _api.get('/admin/vpn') as Map);

  Future<VpnPage> customers({String? q, int page = 1}) async => VpnPage.fromJson(
      await _api.get('/admin/vpn/customers', query: {
        if (q != null && q.isNotEmpty) 'q': q,
        'page': page,
      }) as Map<String, dynamic>);

  Future<Map<String, dynamic>> customer(int id) async =>
      Map<String, dynamic>.from(await _api.get('/admin/vpn/customers/$id') as Map);

  /// [filter]: all, online, removed or errors.
  Future<VpnPage> devices({String filter = 'all', String? q, int page = 1}) async =>
      VpnPage.fromJson(await _api.get('/admin/vpn/devices', query: {
        'filter': filter,
        if (q != null && q.isNotEmpty) 'q': q,
        'page': page,
      }) as Map<String, dynamic>);

  /// [status]: review, pending, paid or failed.
  Future<VpnPage> payments({String status = 'review', String? q, int page = 1}) async =>
      VpnPage.fromJson(await _api.get('/admin/vpn/payments', query: {
        'status': status,
        if (q != null && q.isNotEmpty) 'q': q,
        'page': page,
      }) as Map<String, dynamic>);

  Future<Map<String, dynamic>> plans() async =>
      Map<String, dynamic>.from(await _api.get('/admin/vpn/plans') as Map);

  Future<List<int>> proofBytes(int invoiceId) =>
      _api.getBytes('/admin/vpn/payments/$invoiceId/proof');

  Future<String> _act(String path, [Map<String, dynamic>? data]) async {
    final res = await _api.post(path, data: data ?? const {});
    return (res is Map ? res['message'] as String? : null) ?? 'Done.';
  }

  Future<String> retryOrder(int orderId) => _act('/admin/vpn/orders/$orderId/retry');

  Future<String> setCustomerStatus(int id, String status, {String? reason}) =>
      _act('/admin/vpn/customers/$id/status', {
        'status': status,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<String> grantPlan(int id, String planCode, {String? note}) =>
      _act('/admin/vpn/customers/$id/grant', {
        'plan_code': planCode,
        if (note != null && note.isNotEmpty) 'note': note,
      });

  Future<String> extend(int subscriptionId, int days, {String? note}) =>
      _act('/admin/vpn/subscriptions/$subscriptionId/extend', {
        'days': days,
        if (note != null && note.isNotEmpty) 'note': note,
      });

  Future<String> suspend(int subscriptionId, {String? reason}) =>
      _act('/admin/vpn/subscriptions/$subscriptionId/suspend', {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<String> restore(int subscriptionId) =>
      _act('/admin/vpn/subscriptions/$subscriptionId/restore');

  Future<String> removeDevice(int deviceId, {String? reason}) =>
      _act('/admin/vpn/devices/$deviceId/remove', {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<String> allowDevice(int deviceId) =>
      _act('/admin/vpn/devices/$deviceId/allow');

  Future<String> approvePayment(int invoiceId, {String? note}) =>
      _act('/admin/vpn/payments/$invoiceId/approve', {
        if (note != null && note.isNotEmpty) 'note': note,
      });

  Future<String> rejectPayment(int invoiceId, String reason) =>
      _act('/admin/vpn/payments/$invoiceId/reject', {'reason': reason});

  Future<String> updatePlan(int planId, Map<String, dynamic> body) async {
    final res = await _api.put('/admin/vpn/plans/$planId', data: body);
    return (res is Map ? res['message'] as String? : null) ?? 'Saved.';
  }

  Future<String> syncPlans() => _act('/admin/vpn/plans/sync');
}

final adminVpnRepositoryProvider =
    Provider((ref) => AdminVpnRepository(ref.watch(apiClientProvider)));

final adminVpnOverviewProvider = FutureProvider.autoDispose<Map<String, dynamic>>(
    (ref) => ref.watch(adminVpnRepositoryProvider).overview());

final adminVpnCustomerProvider =
    FutureProvider.autoDispose.family<Map<String, dynamic>, int>(
        (ref, id) => ref.watch(adminVpnRepositoryProvider).customer(id));

final adminVpnPlansProvider = FutureProvider.autoDispose<Map<String, dynamic>>(
    (ref) => ref.watch(adminVpnRepositoryProvider).plans());
