import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';

/// A single headline metric shown on a dashboard.
class StatCard {
  const StatCard({required this.label, required this.value, this.hint});

  final String label;
  final String value;
  final String? hint;

  factory StatCard.fromJson(Map<String, dynamic> json) => StatCard(
        label: json['label'] as String? ?? '',
        value: '${json['value'] ?? ''}',
        hint: json['hint'] as String?,
      );
}

class DashboardData {
  const DashboardData({required this.stats, this.recent = const []});

  final List<StatCard> stats;
  final List<Map<String, dynamic>> recent;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as List? ?? [])
        .map((e) => StatCard.fromJson(e as Map<String, dynamic>))
        .toList();
    final recent = (json['recent'] as List? ?? [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
    return DashboardData(stats: stats, recent: recent);
  }
}

class DashboardRepository {
  DashboardRepository(this._api);

  final ApiClient _api;

  Future<DashboardData> userDashboard() async {
    final data = await _api.get('/dashboard');
    return DashboardData.fromJson(data as Map<String, dynamic>);
  }

  Future<DashboardData> adminDashboard() async {
    final data = await _api.get('/admin/dashboard');
    return DashboardData.fromJson(data as Map<String, dynamic>);
  }
}

final dashboardRepositoryProvider = Provider<DashboardRepository>((ref) {
  return DashboardRepository(ref.watch(apiClientProvider));
});

final userDashboardProvider = FutureProvider.autoDispose<DashboardData>((ref) {
  return ref.watch(dashboardRepositoryProvider).userDashboard();
});

final adminDashboardProvider = FutureProvider.autoDispose<DashboardData>((ref) {
  return ref.watch(dashboardRepositoryProvider).adminDashboard();
});
