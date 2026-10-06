import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_finance_hr_api.dart' show tzs;
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class StaffContract {
  const StaffContract({
    required this.id,
    required this.number,
    required this.status,
    required this.salary,
    this.staff,
    this.position,
    this.startDate,
    this.endDate,
    this.daysLeft,
  });

  final int id;
  final String number;
  final String status;
  final double salary;
  final String? staff;
  final String? position;
  final String? startDate;
  final String? endDate;
  final int? daysLeft;

  factory StaffContract.fromJson(Map<String, dynamic> j) => StaffContract(
        id: (j['id'] as num).toInt(),
        number: j['contract_number'] as String? ?? '',
        status: j['status'] as String? ?? 'draft',
        salary: (j['basic_salary'] as num?)?.toDouble() ?? 0,
        staff: j['staff'] as String?,
        position: j['position'] as String?,
        startDate: j['start_date'] as String?,
        endDate: j['end_date'] as String?,
        daysLeft: (j['days_left'] as num?)?.toInt(),
      );
}

class ContractsPage {
  const ContractsPage(this.items, this.counts, this.statuses, this.staff);
  final List<StaffContract> items;
  final Map<String, int> counts;
  final List<String> statuses;
  final List<({int id, String name})> staff;
}

final _contractsProvider = FutureProvider.autoDispose.family<ContractsPage, String>((ref, filter) async {
  final body = await ref.watch(apiClientProvider).get('/admin/finance/contracts', query: {'filter': filter})
      as Map<String, dynamic>;
  final meta = body['meta'] as Map<String, dynamic>? ?? const {};
  return ContractsPage(
    (body['data'] as List).map((e) => StaffContract.fromJson(e as Map<String, dynamic>)).toList(),
    (meta['counts'] as Map<String, dynamic>? ?? const {}).map((k, v) => MapEntry(k, (v as num).toInt())),
    (meta['statuses'] as List? ?? const []).map((e) => '$e').toList(),
    [
      for (final s in (meta['staff'] as List? ?? const []).cast<Map<String, dynamic>>())
        (id: (s['id'] as num).toInt(), name: '${s['name']} (${s['staff_number']})'),
    ],
  );
});

/// Staff contracts: who is on contract, which end within 30 days, which
/// have expired, and a new contract (same rules as Finance → Contracts).
class FinanceContractsTab extends ConsumerStatefulWidget {
  const FinanceContractsTab({super.key});

  @override
  ConsumerState<FinanceContractsTab> createState() => _FinanceContractsTabState();
}

class _FinanceContractsTabState extends ConsumerState<FinanceContractsTab> {
  String _filter = 'all';

  Future<void> _add(ContractsPage page) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => _NewContractScreen(staff: page.staff, statuses: page.statuses)),
    );
    if (saved == true) ref.invalidate(_contractsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_contractsProvider(_filter));
    return AsyncValueView<ContractsPage>(
      value: async,
      onRefresh: () async => ref.refresh(_contractsProvider(_filter).future),
      data: (page) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            children: [
              for (final f in const ['all', 'active', 'expiring', 'expired'])
                ChoiceChip(
                  label: Text(switch (f) {
                    'active' => 'Active (${page.counts['active'] ?? 0})',
                    'expiring' => 'Ending in 30 days (${page.counts['expiring'] ?? 0})',
                    'expired' => 'Expired (${page.counts['expired'] ?? 0})',
                    _ => 'All',
                  }),
                  selected: _filter == f,
                  onSelected: (_) => setState(() => _filter = f),
                ),
            ],
          ),
          const SizedBox(height: 12),
          MbuiButton(
            label: 'New contract',
            icon: Icons.note_add_outlined,
            fullWidth: true,
            onPressed: page.staff.isEmpty ? null : () => _add(page),
          ),
          const SizedBox(height: 12),
          if (page.items.isEmpty) const MbuiCard(child: Text('No contracts here.')),
          for (final c in page.items)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Expanded(child: Text(c.staff ?? '—', style: const TextStyle(fontWeight: FontWeight.w700))),
                      MbuiBadge(
                        c.daysLeft != null && c.daysLeft! >= 0 && c.daysLeft! <= 30 && c.status == 'active'
                            ? '${c.daysLeft} days left'
                            : c.status,
                        appearance: switch (c.status) {
                          'active' when (c.daysLeft ?? 999) <= 30 => MbuiAppearance.warning,
                          'active' => MbuiAppearance.success,
                          'expired' || 'terminated' || 'cancelled' => MbuiAppearance.danger,
                          _ => MbuiAppearance.neutral,
                        },
                      ),
                    ]),
                    const SizedBox(height: 4),
                    Text(
                      [c.number, ?c.position, tzs(c.salary)].join(' · '),
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                    ),
                    Text(
                      '${c.startDate ?? '?'} → ${c.endDate ?? 'open-ended'}',
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500),
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

class _NewContractScreen extends ConsumerStatefulWidget {
  const _NewContractScreen({required this.staff, required this.statuses});
  final List<({int id, String name})> staff;
  final List<String> statuses;

  @override
  ConsumerState<_NewContractScreen> createState() => _NewContractScreenState();
}

class _NewContractScreenState extends ConsumerState<_NewContractScreen> {
  final _salary = TextEditingController();
  final _type = TextEditingController();
  final _notes = TextEditingController();
  int? _staffId;
  DateTime _start = DateTime.now();
  DateTime? _end;
  String _status = 'active';
  bool _busy = false;
  final _fmt = DateFormat('yyyy-MM-dd');

  @override
  void dispose() {
    _salary.dispose();
    _type.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickDate(bool start) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: start ? _start : (_end ?? _start.add(const Duration(days: 365))),
      firstDate: DateTime(2015),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => start ? _start = picked : _end = picked);
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final body = await ref.read(apiClientProvider).post('/admin/finance/contracts', data: {
        'finance_staff_id': _staffId,
        'start_date': _fmt.format(_start),
        if (_end != null) 'end_date': _fmt.format(_end!),
        'basic_salary': double.tryParse(_salary.text.replaceAll(',', '').trim()),
        'contract_type': _type.text.trim(),
        'status': _status,
        'notes': _notes.text.trim(),
      }) as Map<String, dynamic>;
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(body['message'] as String? ?? 'Saved.')));
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) {
        final first = e.errors?.values.expand((v) => v).firstOrNull ?? e.message;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(first)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: const Text('New contract')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DropdownButtonFormField<int>(
              initialValue: _staffId,
              decoration: const InputDecoration(labelText: 'Staff member'),
              items: [for (final s in widget.staff) DropdownMenuItem(value: s.id, child: Text(s.name))],
              onChanged: (v) => setState(() => _staffId = v),
            ),
            const SizedBox(height: 12),
            TextField(controller: _type, decoration: const InputDecoration(labelText: 'Contract type (e.g. Permanent, Fixed term)')),
            const SizedBox(height: 12),
            TextField(
              controller: _salary,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Basic salary (TZS)'),
            ),
            const SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Start date'),
              subtitle: Text(_fmt.format(_start)),
              trailing: const Icon(Icons.calendar_today_outlined),
              onTap: () => _pickDate(true),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('End date'),
              subtitle: Text(_end == null ? 'Open-ended' : _fmt.format(_end!)),
              trailing: _end == null
                  ? const Icon(Icons.calendar_today_outlined)
                  : IconButton(icon: const Icon(Icons.clear), onPressed: () => setState(() => _end = null)),
              onTap: () => _pickDate(false),
            ),
            DropdownButtonFormField<String>(
              initialValue: _status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: [for (final s in widget.statuses) DropdownMenuItem(value: s, child: Text(s))],
              onChanged: (v) => setState(() => _status = v ?? 'active'),
            ),
            const Padding(
              padding: EdgeInsets.only(top: 6),
              child: Text(
                'An active contract replaces the current one and updates the staff salary and position.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
            ),
            const SizedBox(height: 12),
            TextField(controller: _notes, maxLines: 3, decoration: const InputDecoration(labelText: 'Notes')),
            const SizedBox(height: 16),
            MbuiButton(
              label: 'Save contract',
              fullWidth: true,
              loading: _busy,
              onPressed: _busy || _staffId == null ? null : _save,
            ),
          ],
        ),
      );
}
