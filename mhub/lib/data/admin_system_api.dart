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
  Future<List<AdminSubscription>> subscriptions({String? status}) async => _list(
      await _api.get('/admin/subscriptions', query: {'status': ?status}),
      AdminSubscription.fromJson);

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
final adminSubscriptionsProvider =
    FutureProvider.autoDispose<List<AdminSubscription>>((ref) =>
        ref.watch(adminSystemRepositoryProvider).subscriptions(
              status: ref.watch(adminSubStatusProvider),
            ));
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
