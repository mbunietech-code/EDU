import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/app_notification.dart';

class NotificationsRepository {
  NotificationsRepository(this._api);

  final ApiClient _api;

  Future<({List<AppNotification> items, int unread})> list() async {
    final data = await _api.get('/notifications') as Map<String, dynamic>;
    final items = (data['data'] as List)
        .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
        .toList();
    final unread = ((data['meta'] as Map?)?['unread'] as num?)?.toInt() ?? 0;
    return (items: items, unread: unread);
  }

  Future<void> markRead(String id) => _api.post('/notifications/$id/read');

  Future<void> markAllRead() => _api.post('/notifications/read-all');
}

final notificationsRepositoryProvider = Provider<NotificationsRepository>((ref) {
  return NotificationsRepository(ref.watch(apiClientProvider));
});

final notificationsProvider = FutureProvider.autoDispose<
    ({List<AppNotification> items, int unread})>((ref) {
  return ref.watch(notificationsRepositoryProvider).list();
});
