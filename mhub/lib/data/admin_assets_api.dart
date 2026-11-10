import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class AdminAsset {
  const AdminAsset({
    required this.id,
    required this.name,
    required this.category,
    required this.categoryLabel,
    required this.state,
    required this.billingCycle,
    required this.cycleLabel,
    required this.currency,
    this.vendor,
    this.purchaseUrl,
    this.account,
    this.purchasedAt,
    this.expiresAt,
    this.daysLeft,
    this.cost,
    this.autoRenew = false,
    this.responsibleId,
    this.responsibleName,
    this.responsibleJobTitle,
    this.notes,
    this.canRenew = false,
  });

  final int id;
  final String name;
  final String category;
  final String categoryLabel;

  /// expired | soon | active | no_expiry
  final String state;
  final String billingCycle;
  final String cycleLabel;
  final String currency;
  final String? vendor;
  final String? purchaseUrl;
  final String? account;
  final String? purchasedAt;
  final String? expiresAt;
  final int? daysLeft;
  final double? cost;
  final bool autoRenew;
  final int? responsibleId;
  final String? responsibleName;
  final String? responsibleJobTitle;
  final String? notes;
  final bool canRenew;

  factory AdminAsset.fromJson(Map<String, dynamic> j) => AdminAsset(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        category: j['category'] as String? ?? 'other',
        categoryLabel: j['category_label'] as String? ?? '',
        state: j['state'] as String? ?? 'no_expiry',
        billingCycle: j['billing_cycle'] as String? ?? 'yearly',
        cycleLabel: j['cycle_label'] as String? ?? '',
        currency: j['currency'] as String? ?? 'TZS',
        vendor: j['vendor'] as String?,
        purchaseUrl: j['purchase_url'] as String?,
        account: j['account'] as String?,
        purchasedAt: j['purchased_at'] as String?,
        expiresAt: j['expires_at'] as String?,
        daysLeft: (j['days_left'] as num?)?.toInt(),
        cost: (j['cost'] as num?)?.toDouble(),
        autoRenew: j['auto_renew'] == true,
        responsibleId: (j['responsible_id'] as num?)?.toInt(),
        responsibleName: j['responsible_name'] as String?,
        responsibleJobTitle: j['responsible_job_title'] as String?,
        notes: j['notes'] as String?,
        canRenew: j['can_renew'] == true,
      );
}

class AdminAssetPage {
  const AdminAssetPage(
    this.items, {
    this.currentPage = 1,
    this.lastPage = 1,
    this.total = 0,
    this.counts = const {},
    this.categories = const {},
    this.canManage = false,
  });
  final List<AdminAsset> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final Map<String, int> counts;
  final Map<String, String> categories;
  final bool canManage;
}

class AdminAssetOptions {
  const AdminAssetOptions({
    required this.categories,
    required this.cycles,
    required this.admins,
  });
  final Map<String, String> categories;
  final Map<String, String> cycles;

  /// id → "Name (Job title)"
  final Map<int, String> admins;
}

Map<String, String> _strMap(dynamic m) =>
    (m as Map? ?? const {}).map((k, v) => MapEntry('$k', '$v'));

class AdminAssetsRepository {
  AdminAssetsRepository(this._api);
  final ApiClient _api;

  Future<AdminAssetPage> assets({
    String status = 'all',
    String? category,
    String? q,
    int page = 1,
  }) async {
    final j = await _api.get('/admin/assets', query: {
      'status': status,
      'category': ?category,
      if (q != null && q.isNotEmpty) 'q': q,
      'page': page,
    }) as Map<String, dynamic>;
    final meta = j['meta'] as Map<String, dynamic>? ?? const {};
    return AdminAssetPage(
      (j['data'] as List)
          .map((e) => AdminAsset.fromJson(e as Map<String, dynamic>))
          .toList(),
      currentPage: (meta['current_page'] as num?)?.toInt() ?? page,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? page,
      total: (meta['total'] as num?)?.toInt() ?? 0,
      counts: (meta['counts'] as Map? ?? const {})
          .map((k, v) => MapEntry('$k', (v as num?)?.toInt() ?? 0)),
      categories: _strMap(meta['categories']),
      canManage: meta['can_manage'] == true,
    );
  }

  Future<AdminAsset> asset(int id) async => AdminAsset.fromJson(
      (await _api.get('/admin/assets/$id') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<AdminAssetOptions> options() async {
    final j = await _api.get('/admin/assets/options') as Map<String, dynamic>;
    final admins = <int, String>{};
    for (final a in (j['admins'] as List? ?? const [])) {
      final m = a as Map<String, dynamic>;
      final title = m['job_title'] as String?;
      admins[(m['id'] as num).toInt()] =
          title == null || title.isEmpty ? '${m['name']}' : '${m['name']} ($title)';
    }
    return AdminAssetOptions(
      categories: _strMap(j['categories']),
      cycles: _strMap(j['cycles']),
      admins: admins,
    );
  }

  Future<String> _msg(Future<dynamic> call) async {
    final res = await call;
    return (res is Map ? res['message'] as String? : null) ?? 'Done.';
  }

  Future<String> create(Map<String, dynamic> body) =>
      _msg(_api.post('/admin/assets', data: body));

  Future<String> update(int id, Map<String, dynamic> body) =>
      _msg(_api.put('/admin/assets/$id', data: body));

  Future<String> renew(int id) => _msg(_api.post('/admin/assets/$id/renew'));

  Future<String> delete(int id) => _msg(_api.delete('/admin/assets/$id'));
}

final adminAssetsRepositoryProvider =
    Provider((ref) => AdminAssetsRepository(ref.watch(apiClientProvider)));

final adminAssetStatusProvider = StateProvider<String>((ref) => 'all');
final adminAssetCategoryProvider = StateProvider<String?>((ref) => null);
final adminAssetSearchProvider = StateProvider<String>((ref) => '');

final adminAssetsProvider = FutureProvider.autoDispose<AdminAssetPage>(
    (ref) => ref.watch(adminAssetsRepositoryProvider).assets(
          status: ref.watch(adminAssetStatusProvider),
          category: ref.watch(adminAssetCategoryProvider),
          q: ref.watch(adminAssetSearchProvider),
        ));

final adminAssetProvider = FutureProvider.autoDispose.family<AdminAsset, int>(
    (ref, id) => ref.watch(adminAssetsRepositoryProvider).asset(id));

final adminAssetOptionsProvider = FutureProvider.autoDispose<AdminAssetOptions>(
    (ref) => ref.watch(adminAssetsRepositoryProvider).options());
