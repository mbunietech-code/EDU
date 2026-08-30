import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class FinanceStatus {
  const FinanceStatus({required this.unlocked, required this.pinSet});
  final bool unlocked;
  final bool pinSet;

  factory FinanceStatus.fromJson(Map<String, dynamic> j) => FinanceStatus(
        unlocked: j['unlocked'] == true,
        pinSet: j['pin_set'] == true,
      );
}

class FinanceRow {
  const FinanceRow({
    required this.label,
    required this.type,
    required this.capital,
    required this.income,
    required this.expenses,
    required this.balance,
  });

  final String label;
  final String type;
  final double capital;
  final double income;
  final double expenses;
  final double balance;

  factory FinanceRow.fromJson(Map<String, dynamic> j) => FinanceRow(
        label: j['label'] as String? ?? '',
        type: j['type'] as String? ?? '',
        capital: (j['capital'] as num?)?.toDouble() ?? 0,
        income: (j['income'] as num?)?.toDouble() ?? 0,
        expenses: (j['expenses'] as num?)?.toDouble() ?? 0,
        balance: (j['balance'] as num?)?.toDouble() ?? 0,
      );
}

class FinanceOverview {
  const FinanceOverview({required this.rows, required this.totals});
  final List<FinanceRow> rows;
  final FinanceRow totals;

  factory FinanceOverview.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>;
    final t = d['totals'] as Map<String, dynamic>;
    return FinanceOverview(
      rows: (d['rows'] as List)
          .map((e) => FinanceRow.fromJson(e as Map<String, dynamic>))
          .toList(),
      totals: FinanceRow(
        label: 'Total',
        type: '',
        capital: (t['capital'] as num).toDouble(),
        income: (t['income'] as num).toDouble(),
        expenses: (t['expenses'] as num).toDouble(),
        balance: (t['balance'] as num).toDouble(),
      ),
    );
  }
}

class FinanceExpenseRow {
  const FinanceExpenseRow({
    required this.id,
    required this.label,
    required this.amountLabel,
    this.category,
    this.target,
    this.spentAt,
    this.createdBy,
    this.hasReceipt = false,
  });

  final int id;
  final String label;
  final String amountLabel;
  final String? category;
  final String? target;
  final String? spentAt;
  final String? createdBy;
  final bool hasReceipt;

  factory FinanceExpenseRow.fromJson(Map<String, dynamic> j) => FinanceExpenseRow(
        id: (j['id'] as num).toInt(),
        label: j['label'] as String? ?? '',
        amountLabel: j['amount_label'] as String? ?? '',
        category: j['category'] as String?,
        target: j['target'] as String?,
        spentAt: j['spent_at'] as String?,
        createdBy: j['created_by'] as String?,
        hasReceipt: j['has_receipt'] == true,
      );
}

class FinanceCapitalRow {
  const FinanceCapitalRow({
    required this.id,
    required this.label,
    required this.amountLabel,
    this.source,
    this.isLoan = false,
    this.target,
    this.createdBy,
  });

  final int id;
  final String label;
  final String amountLabel;
  final String? source;
  final bool isLoan;
  final String? target;
  final String? createdBy;

  factory FinanceCapitalRow.fromJson(Map<String, dynamic> j) => FinanceCapitalRow(
        id: (j['id'] as num).toInt(),
        label: j['label'] as String? ?? '',
        amountLabel: j['amount_label'] as String? ?? '',
        source: j['source'] as String?,
        isLoan: j['is_loan'] == true,
        target: j['target'] as String?,
        createdBy: j['created_by'] as String?,
      );
}

class FinanceTargets {
  const FinanceTargets({required this.products, required this.tools, required this.categories});
  final List<({int id, String name})> products;
  final List<({int id, String name})> tools;
  final List<String> categories;

  factory FinanceTargets.fromJson(Map<String, dynamic> j) {
    final d = j['data'] as Map<String, dynamic>;
    List<({int id, String name})> parse(dynamic list) => (list as List)
        .map((e) => (
              id: ((e as Map<String, dynamic>)['id'] as num).toInt(),
              name: e['name'] as String? ?? '',
            ))
        .toList();
    return FinanceTargets(
      products: parse(d['products']),
      tools: parse(d['tools']),
      categories: (d['categories'] as List).map((e) => e.toString()).toList(),
    );
  }
}

class AdminFinanceRepository {
  AdminFinanceRepository(this._api);
  final ApiClient _api;

  Future<FinanceStatus> status() async => FinanceStatus.fromJson(
      (await _api.get('/admin/finance/status') as Map<String, dynamic>)['data']
          as Map<String, dynamic>);

  Future<bool> unlock(String pin) async {
    await _api.post('/admin/finance/unlock', data: {'pin': pin});
    return true;
  }

  Future<FinanceOverview> overview() async => FinanceOverview.fromJson(
      await _api.get('/admin/finance/overview') as Map<String, dynamic>);

  Future<List<FinanceExpenseRow>> expenses() async =>
      ((await _api.get('/admin/finance/expenses') as Map<String, dynamic>)['data']
              as List)
          .map((e) => FinanceExpenseRow.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<List<FinanceCapitalRow>> capital() async =>
      ((await _api.get('/admin/finance/capital') as Map<String, dynamic>)['data']
              as List)
          .map((e) => FinanceCapitalRow.fromJson(e as Map<String, dynamic>))
          .toList();

  Future<FinanceTargets> targets() async => FinanceTargets.fromJson(
      await _api.get('/admin/finance/targets') as Map<String, dynamic>);

  Future<void> addExpense(Map<String, dynamic> body, {String? receiptPath}) async {
    if (receiptPath == null) {
      await _api.post('/admin/finance/expenses', data: body);
      return;
    }
    final form = FormData.fromMap({
      ...body,
      'receipt': await MultipartFile.fromFile(receiptPath),
    });
    await _api.post('/admin/finance/expenses', data: form);
  }

  Future<void> addCapital(Map<String, dynamic> body) =>
      _api.post('/admin/finance/capital', data: body);
}

final adminFinanceRepositoryProvider =
    Provider((ref) => AdminFinanceRepository(ref.watch(apiClientProvider)));

final financeStatusProvider = FutureProvider.autoDispose<FinanceStatus>(
    (ref) => ref.watch(adminFinanceRepositoryProvider).status());
final financeOverviewProvider = FutureProvider.autoDispose<FinanceOverview>(
    (ref) => ref.watch(adminFinanceRepositoryProvider).overview());
final financeExpensesProvider =
    FutureProvider.autoDispose<List<FinanceExpenseRow>>(
        (ref) => ref.watch(adminFinanceRepositoryProvider).expenses());
final financeCapitalProvider =
    FutureProvider.autoDispose<List<FinanceCapitalRow>>(
        (ref) => ref.watch(adminFinanceRepositoryProvider).capital());
final financeTargetsProvider = FutureProvider.autoDispose<FinanceTargets>(
    (ref) => ref.watch(adminFinanceRepositoryProvider).targets());
