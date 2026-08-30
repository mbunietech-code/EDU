import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class DbHealthCheck {
  const DbHealthCheck(this.label, this.ok, this.detail);
  final String label;
  final bool ok;
  final String? detail;
}

class DbInfo {
  const DbInfo({
    required this.status,
    required this.statusLabel,
    required this.database,
    required this.tables,
    required this.rows,
    required this.sizeMb,
    required this.checks,
    required this.pending,
    required this.history,
    required this.backups,
  });

  final String status;
  final String statusLabel;
  final String database;
  final int tables;
  final int rows;
  final double sizeMb;
  final List<DbHealthCheck> checks;
  final List<({String filename, int statements})> pending;
  final List<({String filename, bool ok, String? error, String? appliedAt})> history;
  final List<({String filename, int size, String createdAt})> backups;

  factory DbInfo.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>;
    final h = d['health'] as Map<String, dynamic>;
    return DbInfo(
      status: h['status'] as String? ?? '',
      statusLabel: h['status_label'] as String? ?? '',
      database: h['database'] as String? ?? '',
      tables: (h['tables'] as num?)?.toInt() ?? 0,
      rows: (h['rows'] as num?)?.toInt() ?? 0,
      sizeMb: (h['size_mb'] as num?)?.toDouble() ?? 0,
      checks: (h['checks'] as List? ?? [])
          .map((c) => DbHealthCheck(
                (c as Map<String, dynamic>)['label'] as String? ?? '',
                c['ok'] == true,
                c['detail'] as String?,
              ))
          .toList(),
      pending: (d['pending'] as List? ?? [])
          .map((p) => (
                filename: (p as Map<String, dynamic>)['filename'] as String? ?? '',
                statements: (p['statements'] as num?)?.toInt() ?? 0,
              ))
          .toList(),
      history: (d['history'] as List? ?? [])
          .map((x) => (
                filename: (x as Map<String, dynamic>)['filename'] as String? ?? '',
                ok: x['ok'] == true,
                error: x['error'] as String?,
                appliedAt: x['applied_at'] as String?,
              ))
          .toList(),
      backups: (d['backups'] as List? ?? [])
          .map((b) => (
                filename: (b as Map<String, dynamic>)['filename'] as String? ?? '',
                size: (b['size'] as num?)?.toInt() ?? 0,
                createdAt: b['created_at'] as String? ?? '',
              ))
          .toList(),
    );
  }
}

class AdminDatabaseRepository {
  AdminDatabaseRepository(this._api);
  final ApiClient _api;

  Future<DbInfo> info() async =>
      DbInfo.fromJson(await _api.get('/admin/database') as Map<String, dynamic>);

  Future<String> apply() async {
    final res = await _api.post('/admin/database/apply') as Map<String, dynamic>;
    return res['message'] as String? ?? 'Done.';
  }

  Future<String> backup() async {
    final res = await _api.post('/admin/database/backup') as Map<String, dynamic>;
    final data = res['data'] as Map<String, dynamic>?;
    return data == null
        ? (res['message'] as String? ?? 'Backup created.')
        : 'Backup "${data['filename']}" created (${data['size']} bytes).';
  }
}

final adminDatabaseRepositoryProvider =
    Provider((ref) => AdminDatabaseRepository(ref.watch(apiClientProvider)));

final adminDatabaseProvider = FutureProvider.autoDispose<DbInfo>(
    (ref) => ref.watch(adminDatabaseRepositoryProvider).info());
