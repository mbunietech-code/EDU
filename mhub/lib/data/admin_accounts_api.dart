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
  });

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

  Future<String?> reveal(int id) async {
    final res =
        await _api.post('/admin/accounts/$id/reveal') as Map<String, dynamic>;
    return (res['data'] as Map<String, dynamic>?)?['credentials'] as String?;
  }
}

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
