import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../models/admin_system.dart';

List<T> _list<T>(dynamic data, T Function(Map<String, dynamic>) fromJson) =>
    ((data as Map<String, dynamic>)['data'] as List)
        .map((e) => fromJson(e as Map<String, dynamic>))
        .toList();

Map<String, dynamic> _one(dynamic data) =>
    (data as Map<String, dynamic>)['data'] as Map<String, dynamic>;

class AdminSystemRepository {
  AdminSystemRepository(this._api);
  final ApiClient _api;

  // Error logs
  Future<List<ErrorLogRow>> errorLogs(String status) async => _list(
      await _api.get('/admin/error-logs', query: {'status': status}),
      ErrorLogRow.fromJson);

  Future<ErrorLogDetail> errorLog(int id) async =>
      ErrorLogDetail.fromJson(_one(await _api.get('/admin/error-logs/$id')));

  Future<void> resolveError(int id) =>
      _api.post('/admin/error-logs/$id/resolve');
  Future<void> reopenError(int id) => _api.post('/admin/error-logs/$id/reopen');
  Future<void> deleteError(int id) => _api.delete('/admin/error-logs/$id');

  // Deleted records
  Future<List<DeletedRecordRow>> deletedRecords() async => _list(
      await _api.get('/admin/deleted-records'), DeletedRecordRow.fromJson);

  Future<DeletedRecordDetail> deletedRecord(int id) async =>
      DeletedRecordDetail.fromJson(
          _one(await _api.get('/admin/deleted-records/$id')));

  // Activity
  Future<List<ActivityRow>> activity() async =>
      _list(await _api.get('/admin/activity'), ActivityRow.fromJson);

  // Settings
  Future<AdminSettings> settings() async =>
      AdminSettings.fromJson(await _api.get('/admin/settings') as Map<String, dynamic>);

  Future<AdminSettings> setDev(bool on) async {
    final res = await _api.post('/admin/settings/dev', data: {
      'show_error_details': on,
    });
    return AdminSettings.fromJson(res as Map<String, dynamic>);
  }

  // Subscriptions
  /// [filters]: status, search, user, product, plan, account, device,
  /// expires (past | 7d | 30d | later | Y-m-d) and sort, as on the web page.
  Future<AdminSubscriptionPage> subscriptions(
      {Map<String, String> filters = const {}, int page = 1}) async {
    final j = await _api.get('/admin/subscriptions', query: {
      for (final e in filters.entries)
        if (e.value.isNotEmpty) e.key: e.value,
      'page': page,
    }) as Map<String, dynamic>;
    final meta = j['meta'] as Map<String, dynamic>? ?? const {};
    Map<int, String> idNames(dynamic list) => {
          for (final e in (list as List? ?? const []))
            ((e as Map)['id'] as num).toInt(): '${e['name']}',
        };
    return AdminSubscriptionPage(
      _list(j, AdminSubscription.fromJson),
      currentPage: (meta['current_page'] as num?)?.toInt() ?? page,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? page,
      total: (meta['total'] as num?)?.toInt() ?? 0,
      products: idNames(meta['products']),
      plans: idNames(meta['plans']),
      devices: [for (final d in (meta['devices'] as List? ?? const [])) '$d'],
    );
  }

  Future<AdminSubscription> subscription(int id) async =>
      AdminSubscription.fromJson(
          _one(await _api.get('/admin/subscriptions/$id')));

  Future<void> extendSubscription(int id, int days) =>
      _api.post('/admin/subscriptions/$id/extend', data: {'days': days});

  Future<void> setSubscriptionState(int id, String state) =>
      _api.post('/admin/subscriptions/$id/state', data: {'state': state});

  // Contact messages
  Future<List<ContactMessageRow>> contactMessages() async => _list(
      await _api.get('/admin/contact-messages'), ContactMessageRow.fromJson);

  Future<ContactMessageRow> contactMessage(int id) async =>
      ContactMessageRow.fromJson(
          _one(await _api.get('/admin/contact-messages/$id')));

  Future<void> deleteContactMessage(int id, String reason) => _api.delete(
      '/admin/contact-messages/$id', data: {'reason': reason});

  // Payment methods
  Future<List<AdminPaymentMethod>> paymentMethods() async => _list(
      await _api.get('/admin/payment-methods'), AdminPaymentMethod.fromJson);

  Future<void> createPaymentMethod(Map<String, dynamic> body) =>
      _api.post('/admin/payment-methods', data: body);

  Future<void> updatePaymentMethod(int id, Map<String, dynamic> body) =>
      _api.put('/admin/payment-methods/$id', data: body);

  Future<void> uploadPaymentMethodQr(int id, String filePath) async {
    final form = FormData.fromMap({
      'qr_image': await MultipartFile.fromFile(filePath),
    });
    await _api.post('/admin/payment-methods/$id/qr', data: form);
  }
}

final adminSystemRepositoryProvider =
    Provider((ref) => AdminSystemRepository(ref.watch(apiClientProvider)));

final errorLogFilterProvider = StateProvider<String>((ref) => 'open');
final errorLogsProvider = FutureProvider.autoDispose<List<ErrorLogRow>>((ref) =>
    ref.watch(adminSystemRepositoryProvider).errorLogs(
          ref.watch(errorLogFilterProvider),
        ));
final errorLogProvider = FutureProvider.autoDispose.family<ErrorLogDetail, int>(
    (ref, id) => ref.watch(adminSystemRepositoryProvider).errorLog(id));

final deletedRecordsProvider = FutureProvider.autoDispose<List<DeletedRecordRow>>(
    (ref) => ref.watch(adminSystemRepositoryProvider).deletedRecords());
final deletedRecordProvider =
    FutureProvider.autoDispose.family<DeletedRecordDetail, int>(
        (ref, id) => ref.watch(adminSystemRepositoryProvider).deletedRecord(id));

final activityProvider = FutureProvider.autoDispose<List<ActivityRow>>(
    (ref) => ref.watch(adminSystemRepositoryProvider).activity());

final adminSettingsProvider = FutureProvider.autoDispose<AdminSettings>(
    (ref) => ref.watch(adminSystemRepositoryProvider).settings());

final adminSubStatusProvider = StateProvider<String?>((ref) => null);
/// Filters other than status: search, user, product, plan, device, expires, sort.
/// Labels for the user / product / plan chips are kept under "`key`_label".
final adminSubFiltersProvider = StateProvider<Map<String, String>>((ref) => const {});

final adminSubscriptionsProvider =
    FutureProvider.autoDispose<AdminSubscriptionPage>((ref) {
  final status = ref.watch(adminSubStatusProvider);
  final filters = {
    for (final e in ref.watch(adminSubFiltersProvider).entries)
      if (!e.key.endsWith('_label')) e.key: e.value,
    'status': ?status,
  };
  return ref.watch(adminSystemRepositoryProvider).subscriptions(filters: filters);
});
final adminSubscriptionProvider =
    FutureProvider.autoDispose.family<AdminSubscription, int>((ref, id) =>
        ref.watch(adminSystemRepositoryProvider).subscription(id));

final adminPaymentMethodsProvider =
    FutureProvider.autoDispose<List<AdminPaymentMethod>>(
        (ref) => ref.watch(adminSystemRepositoryProvider).paymentMethods());

final contactMessagesProvider =
    FutureProvider.autoDispose<List<ContactMessageRow>>(
        (ref) => ref.watch(adminSystemRepositoryProvider).contactMessages());
final contactMessageProvider =
    FutureProvider.autoDispose.family<ContactMessageRow, int>((ref, id) =>
        ref.watch(adminSystemRepositoryProvider).contactMessage(id));
