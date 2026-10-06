import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

// Finance HR (staff, payroll, statutory returns, loans) — /admin/finance/*.

double _d(dynamic v) => (v as num?)?.toDouble() ?? 0;
int _i(dynamic v) => (v as num?)?.toInt() ?? 0;
Map<String, dynamic> _m(dynamic v) => v as Map<String, dynamic>;
List<Map<String, dynamic>> _l(dynamic v) => ((v as List?) ?? const []).cast<Map<String, dynamic>>();

String tzs(num v) {
  final s = v.round().toString();
  final out = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0 && s[i - 1] != '-') out.write(',');
    out.write(s[i]);
  }
  return 'TZS $out';
}

class IdName {
  const IdName(this.id, this.name);
  final int id;
  final String name;
}

class StaffRow {
  StaffRow(Map<String, dynamic> j)
      : id = _i(j['id']),
        number = j['staff_number'] as String? ?? '',
        name = j['name'] as String? ?? '',
        position = j['position'] as String?,
        department = j['department'] as String?,
        status = j['status'] as String? ?? 'active',
        basicSalary = _d(j['basic_salary']),
        phone = j['phone'] as String?,
        bank = j['bank_name'] as String?,
        account = j['bank_account_number'] as String?,
        mobileMoney = j['mobile_money'] as String?;

  final int id;
  final String number;
  final String name;
  final String? position;
  final String? department;
  final String status;
  final double basicSalary;
  final String? phone;
  final String? bank;
  final String? account;
  final String? mobileMoney;
}

class StaffDirectory {
  StaffDirectory(Map<String, dynamic> j)
      : staff = _l(j['staff']).map(StaffRow.new).toList(),
        total = _i(_m(j['metrics'])['total']),
        active = _i(_m(j['metrics'])['active']),
        salaryBase = _d(_m(j['metrics'])['salary_base']),
        nextNumber = j['next_staff_number'] as String? ?? '',
        departments = _l(_m(j['options'])['departments']).map((e) => IdName(_i(e['id']), e['name'] as String)).toList(),
        positions = _l(_m(j['options'])['positions']).map((e) => IdName(_i(e['id']), e['name'] as String)).toList(),
        employmentTypes =
            _l(_m(j['options'])['employment_types']).map((e) => IdName(_i(e['id']), e['name'] as String)).toList(),
        banks = ((_m(j['options'])['banks'] as List?) ?? const []).cast<String>(),
        statuses = ((_m(j['options'])['statuses'] as List?) ?? const []).cast<String>();

  final List<StaffRow> staff;
  final int total;
  final int active;
  final double salaryBase;
  final String nextNumber;
  final List<IdName> departments;
  final List<IdName> positions;
  final List<IdName> employmentTypes;
  final List<String> banks;
  final List<String> statuses;
}

class PayrollPeriodRow {
  PayrollPeriodRow(Map<String, dynamic> j)
      : name = j['name'] as String? ?? '',
        status = j['status'] as String? ?? 'draft',
        staffCount = _i(j['staff_count']),
        gross = _d(j['gross_pay']),
        net = _d(j['net_pay']),
        employerCost = _d(j['employer_cost']);

  final String name;
  final String status;
  final int staffCount;
  final double gross;
  final double net;
  final double employerCost;
}

class PayrollLine {
  PayrollLine(Map<String, dynamic> j)
      : name = j['staff_name'] as String? ?? '',
        number = j['staff_number'] as String?,
        position = j['position'] as String?,
        gross = _d(j['gross_pay']),
        paye = _d(j['paye']),
        nssf = _d(j['nssf']),
        deductions = _d(j['total_deductions']),
        net = _d(j['net_pay']);

  final String name;
  final String? number;
  final String? position;
  final double gross;
  final double paye;
  final double nssf;
  final double deductions;
  final double net;
}

class PayrollLatest {
  PayrollLatest(Map<String, dynamic> j)
      : name = j['name'] as String? ?? '',
        status = j['status'] as String? ?? 'draft',
        gross = _d(j['gross_pay']),
        paye = _d(j['paye']),
        nssfStaff = _d(j['nssf_staff']),
        nssfEmployer = _d(j['nssf_employer']),
        sdl = _d(j['sdl']),
        wcf = _d(j['wcf']),
        provisions = _d(j['provisions']),
        deductions = _d(j['total_deductions']),
        net = _d(j['net_pay']),
        employerCost = _d(j['employer_cost']),
        returnsPending = _i(j['returns_pending']),
        items = _l(j['items']).map(PayrollLine.new).toList();

  final String name;
  final String status;
  final double gross;
  final double paye;
  final double nssfStaff;
  final double nssfEmployer;
  final double sdl;
  final double wcf;
  final double provisions;
  final double deductions;
  final double net;
  final double employerCost;
  final int returnsPending;
  final List<PayrollLine> items;
}

class PayrollData {
  PayrollData(Map<String, dynamic> j)
      : periods = _l(j['periods']).map(PayrollPeriodRow.new).toList(),
        latest = j['latest'] == null ? null : PayrollLatest(_m(j['latest'])),
        activeStaff = _i(j['active_staff']),
        rates = _m(j['rates']).map((k, v) => MapEntry(k, (v as num).toDouble()));

  final List<PayrollPeriodRow> periods;
  final PayrollLatest? latest;
  final int activeStaff;
  final Map<String, double> rates;
}

class ReturnRow {
  ReturnRow(Map<String, dynamic> j)
      : id = _i(j['id']),
        label = j['label'] as String? ?? '',
        authority = j['authority'] as String? ?? '',
        period = j['period'] as String?,
        amount = _d(j['amount']),
        dueDate = j['due_date'] as String?,
        status = j['status'] as String? ?? 'pending',
        paidAt = j['paid_at'] as String?,
        reference = j['reference'] as String?;

  final int id;
  final String label;
  final String authority;
  final String? period;
  final double amount;
  final String? dueDate;

  /// pending | overdue | paid
  final String status;
  final String? paidAt;
  final String? reference;
}

class ReturnsData {
  ReturnsData(Map<String, dynamic> j)
      : returns = _l(j['returns']).map(ReturnRow.new).toList(),
        overdue = _d(_m(j['summary'])['overdue']),
        pending = _d(_m(j['summary'])['pending']),
        paidThisYear = _d(_m(j['summary'])['paid_this_year']),
        provisions = _d(_m(j['summary'])['provisions']);

  final List<ReturnRow> returns;
  final double overdue;
  final double pending;
  final double paidThisYear;
  final double provisions;
}

class LoanRow {
  LoanRow(Map<String, dynamic> j)
      : id = _i(j['id']),
        label = j['label'] as String? ?? '',
        source = j['source'] as String? ?? '',
        amount = _d(j['amount']),
        repaid = _d(j['repaid']),
        outstanding = _d(j['outstanding']),
        fullyRepaid = j['fully_repaid'] as bool? ?? false,
        repayments = _l(j['repayments']).map(RepaymentRow.new).toList();

  final int id;
  final String label;
  final String source;
  final double amount;
  final double repaid;
  final double outstanding;
  final bool fullyRepaid;
  final List<RepaymentRow> repayments;
}

class RepaymentRow {
  RepaymentRow(Map<String, dynamic> j)
      : id = _i(j['id']),
        amount = _d(j['amount']),
        paidAt = j['paid_at'] as String?,
        method = j['method'] as String?,
        reference = j['reference'] as String?,
        recordedBy = j['recorded_by'] as String?;

  final int id;
  final double amount;
  final String? paidAt;
  final String? method;
  final String? reference;
  final String? recordedBy;
}

class LoansData {
  LoansData(Map<String, dynamic> j)
      : loans = _l(j['loans']).map(LoanRow.new).toList(),
        borrowed = _d(_m(j['totals'])['borrowed']),
        repaid = _d(_m(j['totals'])['repaid']),
        outstanding = _d(_m(j['totals'])['outstanding']);

  final List<LoanRow> loans;
  final double borrowed;
  final double repaid;
  final double outstanding;
}

class FinanceHrRepository {
  FinanceHrRepository(this._api);
  final ApiClient _api;

  static const _base = '/admin/finance';

  Map<String, dynamic> _data(dynamic body) => _m(_m(body)['data']);
  String _message(dynamic body, String fallback) => (_m(body)['message'] as String?) ?? fallback;

  Future<StaffDirectory> staff({String search = ''}) async =>
      StaffDirectory(_data(await _api.get('$_base/staff', query: {if (search.isNotEmpty) 'q': search})));

  Future<String> addStaff(Map<String, dynamic> body) async =>
      _message(await _api.post('$_base/staff', data: body), 'Staff member added.');

  Future<String> addSetting(Map<String, dynamic> body) async =>
      _message(await _api.post('$_base/staff/settings', data: body), 'Saved.');

  Future<PayrollData> payroll() async => PayrollData(_data(await _api.get('$_base/payroll')));

  Future<String> preparePayroll(Map<String, dynamic> body) async =>
      _message(await _api.post('$_base/payroll', data: body), 'Payroll draft prepared.');

  Future<String> saveRates(Map<String, dynamic> rates) async =>
      _message(await _api.put('$_base/payroll/rates', data: rates), 'Rates saved.');

  Future<ReturnsData> returns() async => ReturnsData(_data(await _api.get('$_base/returns')));

  Future<String> markReturnPaid(int id, {required String paidAt, String? reference}) async => _message(
      await _api.post('$_base/returns/$id/paid', data: {'paid_at': paidAt, 'reference': ?reference}), 'Marked as paid.');

  Future<String> markReturnPending(int id) async =>
      _message(await _api.post('$_base/returns/$id/pending'), 'Moved back to pending.');

  Future<LoansData> loans() async => LoansData(_data(await _api.get('$_base/loans')));

  Future<LoanRow> loan(int id) async => LoanRow(_data(await _api.get('$_base/loans/$id')));

  Future<String> repay(int loanId, Map<String, dynamic> body) async =>
      _message(await _api.post('$_base/loans/$loanId/repayments', data: body), 'Repayment recorded.');

  Future<String> removeRepayment(int loanId, int repaymentId, String reason) async => _message(
      await _api.delete('$_base/loans/$loanId/repayments/$repaymentId', data: {'reason': reason}), 'Repayment removed.');
}

final financeHrRepositoryProvider = Provider((ref) => FinanceHrRepository(ref.watch(apiClientProvider)));

final financeStaffProvider = FutureProvider.autoDispose
    .family<StaffDirectory, String>((ref, search) => ref.watch(financeHrRepositoryProvider).staff(search: search));
final financePayrollProvider = FutureProvider.autoDispose((ref) => ref.watch(financeHrRepositoryProvider).payroll());
final financeReturnsProvider = FutureProvider.autoDispose((ref) => ref.watch(financeHrRepositoryProvider).returns());
final financeLoansProvider = FutureProvider.autoDispose((ref) => ref.watch(financeHrRepositoryProvider).loans());
final financeLoanProvider =
    FutureProvider.autoDispose.family<LoanRow, int>((ref, id) => ref.watch(financeHrRepositoryProvider).loan(id));
