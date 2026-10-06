import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_finance_hr_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

// Finance HR tabs: Staff, Payroll, Returns, Loans (same rules as the web).

String _today() => DateFormat('yyyy-MM-dd').format(DateTime.now());

void _toast(BuildContext context, String text) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

String _errorText(ApiException e) {
  final all = e.errors?.values.expand((v) => v).toList() ?? const <String>[];
  return all.isNotEmpty ? all.first : e.message;
}

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value, {this.color = AppColors.gray900});
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
        child: MbuiCard(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
              const SizedBox(height: 4),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(value, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: color)),
              ),
            ],
          ),
        ),
      );
}

Widget _field(TextEditingController c, String label, {TextInputType? type, String? hint}) => Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(controller: c, keyboardType: type, decoration: InputDecoration(labelText: label, hintText: hint)),
    );

// --- Staff -----------------------------------------------------------------

class FinanceStaffTab extends ConsumerStatefulWidget {
  const FinanceStaffTab({super.key});

  @override
  ConsumerState<FinanceStaffTab> createState() => _FinanceStaffTabState();
}

class _FinanceStaffTabState extends ConsumerState<FinanceStaffTab> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(financeStaffProvider(_search));

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: async.hasValue
          ? FloatingActionButton.extended(
              heroTag: 'staff',
              icon: const Icon(Icons.person_add_alt),
              label: const Text('Staff'),
              onPressed: () async {
                final added = await Navigator.of(context).push<bool>(
                  MaterialPageRoute(builder: (_) => _AddStaffScreen(directory: async.requireValue)),
                );
                if (added == true) ref.invalidate(financeStaffProvider);
              },
            )
          : null,
      body: AsyncValueView<StaffDirectory>(
        value: async,
        onRefresh: () async => ref.refresh(financeStaffProvider(_search).future),
        data: (d) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          children: [
            Row(children: [
              _Stat('Active staff', '${d.active} of ${d.total}'),
              const SizedBox(width: 8),
              _Stat('Salary base', tzs(d.salaryBase)),
            ]),
            const SizedBox(height: 12),
            TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search name, number, phone'),
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => setState(() => _search = v.trim()),
            ),
            const SizedBox(height: 12),
            if (d.staff.isEmpty) const MbuiCard(child: Text('No staff yet. Tap + Staff to add the first one.')),
            for (final s in d.staff)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Expanded(
                          child: Text(s.name, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                        ),
                        MbuiBadge(s.status.replaceAll('_', ' '),
                            appearance: s.status == 'active' ? MbuiAppearance.success : MbuiAppearance.neutral),
                      ]),
                      const SizedBox(height: 3),
                      Text(
                        [s.number, s.position ?? 'No position', ?s.department].join(' · '),
                        style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        [
                          tzs(s.basicSalary),
                          if (s.bank != null) '${s.bank}${s.account != null ? ' ${s.account}' : ''}',
                          if (s.mobileMoney != null) 'M-money ${s.mobileMoney}',
                        ].join(' · '),
                        style: const TextStyle(fontSize: 11, color: AppColors.gray400),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _AddStaffScreen extends ConsumerStatefulWidget {
  const _AddStaffScreen({required this.directory});
  final StaffDirectory directory;

  @override
  ConsumerState<_AddStaffScreen> createState() => _AddStaffScreenState();
}

class _AddStaffScreenState extends ConsumerState<_AddStaffScreen> {
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _salary = TextEditingController();
  final _bankOther = TextEditingController();
  final _account = TextEditingController();
  final _mobileMoney = TextEditingController();
  int? _position;
  int? _department;
  int? _employmentType;
  String _status = 'active';
  String? _bank;
  bool _busy = false;
  String? _error;

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await ref.read(financeHrRepositoryProvider).addStaff({
        'first_name': _first.text.trim(),
        'last_name': _last.text.trim(),
        'phone': _phone.text.trim(),
        'email': _email.text.trim(),
        'basic_salary': _salary.text.trim(),
        'status': _status,
        'finance_position_id': ?_position,
        'finance_department_id': ?_department,
        'finance_employment_type_id': ?_employmentType,
        'bank_name': ?_bank,
        if (_bank == 'other') 'bank_name_other': _bankOther.text.trim(),
        'bank_account_number': _account.text.trim(),
        'mobile_money': _mobileMoney.text.trim(),
      }..removeWhere((_, v) => v == ''));
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      setState(() {
        _busy = false;
        _error = _errorText(e);
      });
    }
  }

  DropdownButtonFormField<int> _pick(String label, List<IdName> items, int? value, ValueChanged<int?> onChanged) =>
      DropdownButtonFormField<int>(
        initialValue: value,
        isExpanded: true,
        decoration: InputDecoration(labelText: label),
        items: [
          const DropdownMenuItem<int>(value: null, child: Text('None')),
          for (final i in items) DropdownMenuItem(value: i.id, child: Text(i.name)),
        ],
        onChanged: onChanged,
      );

  @override
  Widget build(BuildContext context) {
    final d = widget.directory;

    return Scaffold(
      appBar: AppBar(title: const Text('Add staff')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(
            child: Text('Staff number will be ${d.nextNumber} (assigned automatically).',
                style: const TextStyle(fontSize: 13, color: AppColors.gray600)),
          ),
          const SizedBox(height: 16),
          _field(_first, 'First name'),
          _field(_last, 'Last name'),
          _field(_phone, 'Phone', type: TextInputType.phone),
          _field(_email, 'Email', type: TextInputType.emailAddress),
          _pick('Position', d.positions, _position, (v) => setState(() => _position = v)),
          const SizedBox(height: 12),
          _pick('Department', d.departments, _department, (v) => setState(() => _department = v)),
          const SizedBox(height: 12),
          _pick('Employment type', d.employmentTypes, _employmentType, (v) => setState(() => _employmentType = v)),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _status,
            decoration: const InputDecoration(labelText: 'Status'),
            items: [for (final s in d.statuses) DropdownMenuItem(value: s, child: Text(s.replaceAll('_', ' ')))],
            onChanged: (v) => setState(() => _status = v ?? 'active'),
          ),
          const SizedBox(height: 12),
          _field(_salary, 'Basic salary (TZS)', type: TextInputType.number),
          DropdownButtonFormField<String>(
            initialValue: _bank,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Bank'),
            items: [
              const DropdownMenuItem<String>(value: null, child: Text('None')),
              for (final b in d.banks) DropdownMenuItem(value: b, child: Text(b)),
              const DropdownMenuItem(value: 'other', child: Text('Other (type name)')),
            ],
            onChanged: (v) => setState(() => _bank = v),
          ),
          const SizedBox(height: 12),
          if (_bank == 'other') _field(_bankOther, 'Bank name'),
          _field(_account, 'Bank account number'),
          _field(_mobileMoney, 'Mobile money number', type: TextInputType.phone),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: AppColors.red600)),
            ),
          MbuiButton(label: 'Save staff', fullWidth: true, loading: _busy, onPressed: _save),
        ],
      ),
    );
  }
}

// --- Payroll ---------------------------------------------------------------

class FinancePayrollTab extends ConsumerWidget {
  const FinancePayrollTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financePayrollProvider);

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'payroll',
        icon: const Icon(Icons.calculate_outlined),
        label: const Text('Prepare draft'),
        onPressed: () async {
          final done = await Navigator.of(context)
              .push<bool>(MaterialPageRoute(builder: (_) => const _PreparePayrollScreen()));
          if (done == true) {
            ref.invalidate(financePayrollProvider);
            ref.invalidate(financeReturnsProvider);
          }
        },
      ),
      body: AsyncValueView<PayrollData>(
        value: async,
        onRefresh: () async => ref.refresh(financePayrollProvider.future),
        data: (p) {
          final l = p.latest;
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
            children: [
              if (l == null)
                MbuiCard(child: Text('No payroll yet. ${p.activeStaff} active staff ready. Tap Prepare draft.'))
              else ...[
                Text('${l.name} · ${l.status}',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.gray900)),
                const SizedBox(height: 8),
                Row(children: [
                  _Stat('Gross', tzs(l.gross)),
                  const SizedBox(width: 8),
                  _Stat('Net pay', tzs(l.net), color: AppColors.emerald700),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  _Stat('PAYE (TRA)', tzs(l.paye)),
                  const SizedBox(width: 8),
                  _Stat('NSSF', tzs(l.nssfStaff + l.nssfEmployer)),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  _Stat('SDL', tzs(l.sdl)),
                  const SizedBox(width: 8),
                  _Stat('WCF', tzs(l.wcf)),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  _Stat('Provisions', tzs(l.provisions), color: AppColors.amber700),
                  const SizedBox(width: 8),
                  _Stat('Employer cost', tzs(l.employerCost), color: AppColors.indigo700),
                ]),
                if (l.returnsPending > 0) ...[
                  const SizedBox(height: 8),
                  Text('${l.returnsPending} statutory returns still to pay (see Returns tab).',
                      style: const TextStyle(fontSize: 12, color: AppColors.amber700)),
                ],
                const SizedBox(height: 16),
                const MbuiSectionLabel('Staff in this payroll'),
                const SizedBox(height: 8),
                for (final i in l.items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: MbuiCard(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(i.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                                Text('Gross ${tzs(i.gross)} · PAYE ${tzs(i.paye)} · NSSF ${tzs(i.nssf)}',
                                    style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
                              ],
                            ),
                          ),
                          Text(tzs(i.net),
                              style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.emerald700)),
                        ],
                      ),
                    ),
                  ),
              ],
              const SizedBox(height: 16),
              Row(children: [
                const Expanded(child: MbuiSectionLabel('Payroll history')),
                TextButton.icon(
                  icon: const Icon(Icons.tune, size: 18),
                  label: const Text('Statutory rates'),
                  onPressed: () async {
                    final saved = await Navigator.of(context)
                        .push<bool>(MaterialPageRoute(builder: (_) => _RatesScreen(rates: p.rates)));
                    if (saved == true) ref.invalidate(financePayrollProvider);
                  },
                ),
              ]),
              for (final h in p.periods)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(h.name),
                  subtitle: Text('${h.staffCount} staff · ${h.status}'),
                  trailing: Text(tzs(h.net), style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _PreparePayrollScreen extends ConsumerStatefulWidget {
  const _PreparePayrollScreen();

  @override
  ConsumerState<_PreparePayrollScreen> createState() => _PreparePayrollScreenState();
}

class _PreparePayrollScreenState extends ConsumerState<_PreparePayrollScreen> {
  final _month = TextEditingController(text: DateFormat('yyyy-MM').format(DateTime.now()));
  final _fields = {
    'transport_allowance': TextEditingController(),
    'meal_allowance': TextEditingController(),
    'housing_allowance': TextEditingController(),
    'overtime_pay': TextEditingController(),
    'bonus_pay': TextEditingController(),
    'insurance_amount': TextEditingController(),
    'loan_deductions': TextEditingController(),
    'other_deductions': TextEditingController(),
  };
  final _notes = TextEditingController();
  bool _busy = false;
  String? _error;

  static const _labels = {
    'transport_allowance': 'Transport allowance',
    'meal_allowance': 'Meal allowance',
    'housing_allowance': 'Housing allowance',
    'overtime_pay': 'Overtime',
    'bonus_pay': 'Bonus',
    'insurance_amount': 'Insurance / NHIF deduction',
    'loan_deductions': 'Loan deduction',
    'other_deductions': 'Other deduction',
  };

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await ref.read(financeHrRepositoryProvider).preparePayroll({
        'period_month': _month.text.trim(),
        for (final e in _fields.entries)
          if (e.value.text.trim().isNotEmpty) e.key: e.value.text.trim(),
        if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
      });
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      setState(() {
        _busy = false;
        _error = _errorText(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Prepare payroll draft')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const MbuiCard(
              child: Text(
                'PAYE, NSSF, SDL, WCF and provisions are calculated automatically. '
                'Amounts below apply to every active staff member.',
                style: TextStyle(fontSize: 13, color: AppColors.gray600),
              ),
            ),
            const SizedBox(height: 16),
            _field(_month, 'Payroll month (YYYY-MM)'),
            for (final e in _fields.entries) _field(e.value, '${_labels[e.key]} (TZS)', type: TextInputType.number),
            _field(_notes, 'Notes'),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(_error!, style: const TextStyle(color: AppColors.red600)),
              ),
            MbuiButton(label: 'Prepare draft', fullWidth: true, loading: _busy, onPressed: _submit),
          ],
        ),
      );
}

class _RatesScreen extends ConsumerStatefulWidget {
  const _RatesScreen({required this.rates});
  final Map<String, double> rates;

  @override
  ConsumerState<_RatesScreen> createState() => _RatesScreenState();
}

class _RatesScreenState extends ConsumerState<_RatesScreen> {
  static const _labels = {
    'nssf_employee': 'NSSF (staff) %',
    'nssf_employer': 'NSSF (employer) %',
    'sdl': 'SDL %',
    'sdl_min_staff': 'SDL from staff count',
    'wcf': 'WCF %',
    'leave_provision': 'Leave provision %',
    'severance_provision': 'Severance provision %',
    'gratuity_provision': 'Gratuity provision %',
  };

  late final Map<String, TextEditingController> _c = {
    for (final k in widget.rates.keys)
      k: TextEditingController(
          text: k == 'sdl_min_staff' ? widget.rates[k]!.toInt().toString() : _trim(widget.rates[k]!)),
  };
  bool _busy = false;

  static String _trim(double v) => v == v.roundToDouble() ? v.toInt().toString() : v.toString();

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final message = await ref
          .read(financeHrRepositoryProvider)
          .saveRates({for (final e in _c.entries) e.key: e.value.text.trim()});
      if (mounted) {
        _toast(context, message);
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      if (mounted) _toast(context, _errorText(e));
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Statutory rates')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text('Tanzania mainland defaults. Change them only if the law changes.',
                style: TextStyle(fontSize: 13, color: AppColors.gray500)),
            const SizedBox(height: 16),
            for (final e in _c.entries)
              _field(e.value, _labels[e.key] ?? e.key, type: const TextInputType.numberWithOptions(decimal: true)),
            MbuiButton(label: 'Save rates', fullWidth: true, loading: _busy, onPressed: _save),
          ],
        ),
      );
}

// --- Returns ---------------------------------------------------------------

class FinanceReturnsTab extends ConsumerWidget {
  const FinanceReturnsTab({super.key});

  Future<void> _markPaid(BuildContext context, WidgetRef ref, ReturnRow r) async {
    final reference = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Mark ${r.label} paid'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${tzs(r.amount)} to ${r.authority} · ${r.period ?? ''}'),
            const SizedBox(height: 12),
            TextField(
              controller: reference,
              decoration: const InputDecoration(labelText: 'Control number / receipt (optional)'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Mark paid')),
        ],
      ),
    );
    if (ok != true) return;

    try {
      final message = await ref.read(financeHrRepositoryProvider).markReturnPaid(r.id,
          paidAt: _today(), reference: reference.text.trim().isEmpty ? null : reference.text.trim());
      ref.invalidate(financeReturnsProvider);
      if (context.mounted) _toast(context, message);
    } on ApiException catch (e) {
      if (context.mounted) _toast(context, _errorText(e));
    }
  }

  Future<void> _undo(BuildContext context, WidgetRef ref, ReturnRow r) async {
    try {
      final message = await ref.read(financeHrRepositoryProvider).markReturnPending(r.id);
      ref.invalidate(financeReturnsProvider);
      if (context.mounted) _toast(context, message);
    } on ApiException catch (e) {
      if (context.mounted) _toast(context, _errorText(e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financeReturnsProvider);

    return AsyncValueView<ReturnsData>(
      value: async,
      onRefresh: () async => ref.refresh(financeReturnsProvider.future),
      data: (d) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [
            _Stat('Overdue', tzs(d.overdue), color: d.overdue > 0 ? AppColors.red600 : AppColors.gray900),
            const SizedBox(width: 8),
            _Stat('Coming up', tzs(d.pending), color: AppColors.amber700),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            _Stat('Paid this year', tzs(d.paidThisYear), color: AppColors.emerald700),
            const SizedBox(width: 8),
            _Stat('Provisions', tzs(d.provisions)),
          ]),
          const SizedBox(height: 16),
          if (d.returns.isEmpty)
            const MbuiCard(child: Text('No returns yet. Prepare a payroll draft and its PAYE, SDL, NSSF and WCF appear here.')),
          for (final r in d.returns)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: r.status == 'paid' ? null : () => _markPaid(context, ref, r),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${r.label} · ${r.period ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text(
                            r.status == 'paid'
                                ? 'Paid ${r.paidAt ?? ''}${r.reference != null ? ' · ${r.reference}' : ''}'
                                : 'Pay to ${r.authority} by ${r.dueDate ?? ''}',
                            style: TextStyle(
                                fontSize: 12, color: r.status == 'overdue' ? AppColors.red600 : AppColors.gray500),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(tzs(r.amount), style: const TextStyle(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 4),
                        if (r.status == 'paid')
                          GestureDetector(
                            onTap: () => _undo(context, ref, r),
                            child: const Text('Undo', style: TextStyle(fontSize: 12, color: AppColors.gray500)),
                          )
                        else
                          MbuiBadge(r.status == 'overdue' ? 'Overdue' : 'Tap to mark paid',
                              appearance: r.status == 'overdue' ? MbuiAppearance.danger : MbuiAppearance.warning),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// --- Loans -----------------------------------------------------------------

class FinanceLoansTab extends ConsumerWidget {
  const FinanceLoansTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financeLoansProvider);

    return AsyncValueView<LoansData>(
      value: async,
      onRefresh: () async => ref.refresh(financeLoansProvider.future),
      data: (d) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [
            _Stat('Borrowed', tzs(d.borrowed)),
            const SizedBox(width: 8),
            _Stat('Repaid', tzs(d.repaid), color: AppColors.emerald700),
            const SizedBox(width: 8),
            _Stat('Still owed', tzs(d.outstanding), color: d.outstanding > 0 ? AppColors.amber700 : AppColors.gray900),
          ]),
          const SizedBox(height: 16),
          if (d.loans.isEmpty)
            const MbuiCard(child: Text('No loans. Capital marked "This is a loan" appears here.')),
          for (final l in d.loans)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: () async {
                  await Navigator.of(context).push(MaterialPageRoute(builder: (_) => _LoanScreen(loanId: l.id)));
                  ref.invalidate(financeLoansProvider);
                },
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Expanded(child: Text(l.label, style: const TextStyle(fontWeight: FontWeight.w700))),
                      MbuiBadge(l.fullyRepaid ? 'Repaid' : 'Owes ${tzs(l.outstanding)}',
                          appearance: l.fullyRepaid ? MbuiAppearance.success : MbuiAppearance.warning),
                    ]),
                    const SizedBox(height: 4),
                    Text('From ${l.source} · borrowed ${tzs(l.amount)} · repaid ${tzs(l.repaid)}',
                        style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _LoanScreen extends ConsumerStatefulWidget {
  const _LoanScreen({required this.loanId});
  final int loanId;

  @override
  ConsumerState<_LoanScreen> createState() => _LoanScreenState();
}

class _LoanScreenState extends ConsumerState<_LoanScreen> {
  final _amount = TextEditingController();
  final _method = TextEditingController();
  final _reference = TextEditingController();
  bool _busy = false;
  String? _error;

  Future<void> _repay() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await ref.read(financeHrRepositoryProvider).repay(widget.loanId, {
        'amount': _amount.text.trim(),
        'paid_at': _today(),
        if (_method.text.trim().isNotEmpty) 'method': _method.text.trim(),
        if (_reference.text.trim().isNotEmpty) 'reference': _reference.text.trim(),
      });
      _amount.clear();
      _method.clear();
      _reference.clear();
      ref.invalidate(financeLoanProvider(widget.loanId));
      if (mounted) _toast(context, message);
    } on ApiException catch (e) {
      _error = _errorText(e);
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _remove(RepaymentRow r) async {
    final reason = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Remove repayment?'),
        content: TextField(controller: reason, decoration: const InputDecoration(labelText: 'Reason')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Remove')),
        ],
      ),
    );
    if (ok != true || reason.text.trim().isEmpty) return;

    try {
      final message = await ref.read(financeHrRepositoryProvider).removeRepayment(widget.loanId, r.id, reason.text.trim());
      ref.invalidate(financeLoanProvider(widget.loanId));
      if (mounted) _toast(context, message);
    } on ApiException catch (e) {
      if (mounted) _toast(context, _errorText(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(financeLoanProvider(widget.loanId));

    return Scaffold(
      appBar: AppBar(title: const Text('Loan repayments')),
      body: AsyncValueView<LoanRow>(
        value: async,
        onRefresh: () async => ref.refresh(financeLoanProvider(widget.loanId).future),
        data: (l) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(l.label, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            Text('Borrowed from ${l.source}', style: const TextStyle(color: AppColors.gray500)),
            const SizedBox(height: 12),
            Row(children: [
              _Stat('Borrowed', tzs(l.amount)),
              const SizedBox(width: 8),
              _Stat('Repaid', tzs(l.repaid), color: AppColors.emerald700),
              const SizedBox(width: 8),
              _Stat('Still owed', tzs(l.outstanding), color: AppColors.amber700),
            ]),
            const SizedBox(height: 16),
            if (!l.fullyRepaid)
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MbuiSectionLabel('Record a repayment'),
                    const SizedBox(height: 10),
                    _field(_amount, 'Amount (TZS)', type: TextInputType.number, hint: 'Max ${tzs(l.outstanding)}'),
                    _field(_method, 'Paid via (optional)', hint: 'M-Pesa, Cash, CRDB transfer'),
                    _field(_reference, 'Reference (optional)'),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Text(_error!, style: const TextStyle(color: AppColors.red600)),
                      ),
                    MbuiButton(label: 'Record repayment', loading: _busy, onPressed: _repay),
                  ],
                ),
              )
            else
              const MbuiCard(child: Text('This loan is fully repaid.')),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Repayment history'),
            if (l.repayments.isEmpty) const Padding(padding: EdgeInsets.all(8), child: Text('No repayments yet.')),
            for (final r in l.repayments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(tzs(r.amount), style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text([r.paidAt ?? '', ?r.method, ?r.reference, ?r.recordedBy].where((s) => s.isNotEmpty).join(' · ')),
                trailing: IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _remove(r)),
              ),
          ],
        ),
      ),
    );
  }
}
