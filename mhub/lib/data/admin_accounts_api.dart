import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class VaultAccount {
  const VaultAccount({
    required this.id,
    required this.name,
    required this.product,
    required this.productId,
    required this.status,
    required this.hasCredentials,
    this.description,
    this.subscriptions = const [],
    this.planName,
    this.purchasedAt,
    this.expiresAt,
    this.daysLeft,
    this.planState = 'unknown',
    this.cost,
    this.costCurrency,
    this.autoRenew = false,
    this.users = 0,
  });

  /// The AI plan we bought for this account.
  final String? planName;
  final DateTime? purchasedAt;
  final DateTime? expiresAt;

  /// Days until the plan ends (negative = ended); null without an end date.
  final int? daysLeft;

  /// active | expiring | expired | unknown
  final String planState;
  final double? cost;
  final String? costCurrency;
  final bool autoRenew;

  /// Customers currently using this account.
  final int users;

  final int id;
  final String name;
  final String product;
  final int productId;
  final String status;
  final bool hasCredentials;
  final String? description;
  final List<({int id, String user, String status})> subscriptions;

  factory VaultAccount.fromJson(Map<String, dynamic> j) => VaultAccount(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        product: j['product'] as String? ?? '—',
        productId: (j['product_id'] as num?)?.toInt() ?? 0,
        status: j['status'] as String? ?? '',
        hasCredentials: j['has_credentials'] == true,
        description: j['description'] as String?,
        planName: j['plan_name'] as String?,
        purchasedAt: DateTime.tryParse(j['purchased_at'] as String? ?? ''),
        expiresAt: DateTime.tryParse(j['expires_at'] as String? ?? ''),
        daysLeft: (j['days_left'] as num?)?.toInt(),
        planState: j['plan_state'] as String? ?? 'unknown',
        cost: (j['cost'] as num?)?.toDouble(),
        costCurrency: j['cost_currency'] as String?,
        autoRenew: j['auto_renew'] == true,
        users: (j['users'] as num?)?.toInt() ?? 0,
        subscriptions: (j['subscriptions'] as List? ?? [])
            .map((s) => (
                  id: ((s as Map<String, dynamic>)['id'] as num).toInt(),
                  user: s['user'] as String? ?? '—',
                  status: s['status'] as String? ?? '',
                ))
            .toList(),
      );
}

class AdminAccountsRepository {
  AdminAccountsRepository(this._api);
  final ApiClient _api;

  Future<List<VaultAccount>> list({String? status}) async =>
      ((await _api.get('/admin/accounts',
                  query: {'status': ?status}) as Map<String, dynamic>)['data']
              as List)
          .map((e) => VaultAccount.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<VaultAccount> detail(int id) async => VaultAccount.fromJson(
      (await _api.get('/admin/accounts/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<List<({int id, String name})>> products() async =>
      ((await _api.get('/admin/accounts/targets') as Map<String, dynamic>)['data']
              as Map<String, dynamic>)['products']
          .cast<Map<String, dynamic>>()
          .map<({int id, String name})>((p) =>
              (id: (p['id'] as num).toInt(), name: p['name'] as String? ?? ''))
          .toList();

  Future<int> create(Map<String, dynamic> body) async =>
      (((await _api.post('/admin/accounts', data: body)
              as Map<String, dynamic>)['data']) as Map<String, dynamic>)['id']
          as int;

  Future<void> update(int id, Map<String, dynamic> body) =>
      _api.put('/admin/accounts/$id', data: body);

  Future<void> archive(int id) => _api.post('/admin/accounts/$id/archive');

  /// AI plans we bought, soonest end date first.
  Future<AiPlansPage> plans(String filter) async {
    final body = await _api.get('/admin/accounts/plans',
        query: {if (filter != 'all') 'filter': filter}) as Map<String, dynamic>;
    final meta = body['meta'] as Map<String, dynamic>? ?? const {};
    int n(String k) => (meta[k] as num?)?.toInt() ?? 0;
    return AiPlansPage(
      items: (body['data'] as List)
          .map((e) => VaultAccount.fromJson(e as Map<String, dynamic>))
          .toList(),
      total: n('total'),
      active: n('active'),
      expiring: n('expiring'),
      expired: n('expired'),
      unknown: n('unknown'),
      expiringDays: n('expiring_days'),
      costByCurrency: (meta['cost_by_currency'] is Map
              ? (meta['cost_by_currency'] as Map).cast<String, dynamic>()
              : const <String, dynamic>{})
          .map((k, v) => MapEntry(k, (v as num).toDouble())),
    );
  }

  Future<String?> reveal(int id) async {
    final res =
        await _api.post('/admin/accounts/$id/reveal') as Map<String, dynamic>;
    return (res['data'] as Map<String, dynamic>?)?['credentials'] as String?;
  }
}

class AiPlansPage {
  const AiPlansPage({
    required this.items,
    required this.total,
    required this.active,
    required this.expiring,
    required this.expired,
    required this.unknown,
    required this.expiringDays,
    required this.costByCurrency,
  });

  final List<VaultAccount> items;
  final int total;
  final int active;
  final int expiring;
  final int expired;
  final int unknown;
  final int expiringDays;
  final Map<String, double> costByCurrency;
}

final aiPlansProvider = FutureProvider.autoDispose
    .family<AiPlansPage, String>((ref, filter) => ref.watch(adminAccountsRepositoryProvider).plans(filter));

final adminAccountsRepositoryProvider =
    Provider((ref) => AdminAccountsRepository(ref.watch(apiClientProvider)));

final vaultStatusFilterProvider = StateProvider<String?>((ref) => null);
final vaultAccountsProvider = FutureProvider.autoDispose<List<VaultAccount>>(
    (ref) => ref.watch(adminAccountsRepositoryProvider).list(
          status: ref.watch(vaultStatusFilterProvider),
        ));
final vaultAccountProvider =
    FutureProvider.autoDispose.family<VaultAccount, int>((ref, id) =>
        ref.watch(adminAccountsRepositoryProvider).detail(id));
final vaultProductsProvider =
    FutureProvider.autoDispose<List<({int id, String name})>>(
        (ref) => ref.watch(adminAccountsRepositoryProvider).products());
