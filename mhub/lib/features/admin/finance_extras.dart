import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

// Finance pages added to match the web: money accounts (with transfers),
// audit, one line's records, the start date and unorganised data.
// Every call needs Finance unlocked with the PIN (the API answers 423 if not).

class FinanceExtrasRepository {
  FinanceExtrasRepository(this._api);
  final ApiClient _api;

  Future<Map<String, dynamic>> _get(String path, [Map<String, dynamic>? q]) async =>
      Map<String, dynamic>.from(await _api.get(path, query: q) as Map);

  Future<String> _msg(Future<dynamic> call) async {
    final r = await call;
    return (r is Map ? r['message'] as String? : null) ?? 'Done.';
  }

  Future<Map<String, dynamic>> money() async =>
      Map<String, dynamic>.from((await _get('/admin/finance/money'))['data'] as Map);
  Future<Map<String, dynamic>> account(int id) async =>
      Map<String, dynamic>.from((await _get('/admin/finance/money/$id'))['data'] as Map);
  Future<String> newTransfer(Map<String, dynamic> body) =>
      _msg(_api.post('/admin/finance/money/transfers', data: body));
  Future<Map<String, dynamic>> transfer(int id) async =>
      Map<String, dynamic>.from((await _get('/admin/finance/money/transfers/$id'))['data'] as Map);
  Future<String> updateTransfer(int id, Map<String, dynamic> body) =>
      _msg(_api.put('/admin/finance/money/transfers/$id', data: body));
  Future<String> deleteTransfer(int id, String reason) =>
      _msg(_api.delete('/admin/finance/money/transfers/$id', data: {'reason': reason}));

  Future<Map<String, dynamic>> audit({String? kind}) =>
      _get('/admin/finance/audit', {'kind': ?kind});
  Future<Map<String, dynamic>> line(String key) =>
      _get('/admin/finance/line', {'line': key});
  Future<Map<String, dynamic>> start({String? date}) =>
      _get('/admin/finance/start', {'date': ?date});
  Future<String> saveStart(String date, Map<String, double?> balances) => _msg(_api.put(
      '/admin/finance/start',
      data: {'start_date': date, 'balances': balances}));
  Future<String> clearStart() => _msg(_api.delete('/admin/finance/start'));
  Future<Map<String, dynamic>> unorganised(String tab, {int page = 1}) =>
      _get('/admin/finance/unorganised', {'tab': tab, 'page': page});
}

final financeExtrasRepositoryProvider =
    Provider((ref) => FinanceExtrasRepository(ref.watch(apiClientProvider)));

// ============================================================== helpers

final _tzs = NumberFormat('#,##0');

String _money(dynamic v) {
  final n = (v as num?)?.toDouble() ?? 0;
  return '${n < 0 ? '-' : ''}TZS ${_tzs.format(n.abs())}';
}

String _day(dynamic iso) {
  final d = iso == null ? null : DateTime.tryParse('$iso');
  return d == null ? '—' : DateFormat('d MMM y').format(d);
}

String _err(Object e) => e is ApiException
    ? (e.errors?.values.expand((v) => v).firstOrNull ?? e.message)
    : '$e';

void _toast(BuildContext context, String msg) {
  if (context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }
}

/// FutureBuilder with spinner / error / pull-to-refresh.
class _Loader extends StatefulWidget {
  const _Loader({super.key, required this.load, required this.builder});
  final Future<Map<String, dynamic>> Function() load;
  final Widget Function(BuildContext context, Map<String, dynamic> data, VoidCallback reload) builder;

  @override
  State<_Loader> createState() => _LoaderState();
}

class _LoaderState extends State<_Loader> with AutomaticKeepAliveClientMixin {
  late Future<Map<String, dynamic>> _future = widget.load();

  @override
  bool get wantKeepAlive => true;

  void _reload() => setState(() => _future = widget.load());

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return ListView(padding: const EdgeInsets.all(24), children: [
            Text(_err(snap.error!), style: const TextStyle(color: AppColors.red700)),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _reload, child: const Text('Try again')),
          ]);
        }
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: widget.builder(context, snap.data!, _reload),
        );
      },
    );
  }
}

class _Sub extends StatelessWidget {
  const _Sub(this.text, {this.color = AppColors.gray500});
  final String text;
  final Color color;
  @override
  Widget build(BuildContext context) => Text(text, style: TextStyle(fontSize: 12, color: color));
}

Widget _recordCard(Map r, {VoidCallback? onTap, bool signed = false}) {
  final amount = (r['amount'] as num?)?.toDouble() ?? 0;
  return Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: MbuiCard(
      padding: const EdgeInsets.all(12),
      onTap: onTap,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${r['label'] ?? '—'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                _Sub([_day(r['date']), if ((r['detail'] ?? '').toString().isNotEmpty) '${r['detail']}'].join(' · ')),
              ],
            ),
          ),
          Text(
            signed ? '${amount > 0 ? '+' : ''}${_money(amount)}' : _money(amount),
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: signed ? (amount < 0 ? AppColors.red600 : AppColors.emerald700) : AppColors.gray900,
            ),
          ),
          if (onTap != null) const Icon(Icons.chevron_right, color: AppColors.gray400),
        ],
      ),
    ),
  );
}

// ============================================================== money

class FinanceMoneyTab extends ConsumerWidget {
  const FinanceMoneyTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.read(financeExtrasRepositoryProvider);
    return _Loader(
      load: repo.money,
      builder: (context, d, reload) {
        if (d['ready'] != true) {
          return ListView(padding: const EdgeInsets.all(24), children: const [
            Text('Money accounts need database change 0034. Apply it from Admin → Database.'),
          ]);
        }
        final accounts = (d['accounts'] as List? ?? const []).cast<Map>();
        final active = accounts.where((a) => a['is_active'] != false).toList();
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              child: Row(
                children: [
                  const Expanded(child: Text('Money in all accounts', style: TextStyle(color: AppColors.gray700))),
                  Text(_money(d['total']),
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                ],
              ),
            ),
            const SizedBox(height: 10),
            MbuiButton(
              label: 'Move money between accounts',
              icon: Icons.swap_horiz,
              variant: MbuiVariant.secondary,
              fullWidth: true,
              onPressed: () async {
                final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
                  builder: (_) => FinanceTransferScreen(
                    accounts: active.map((a) => Map<String, dynamic>.from(a)).toList(),
                  ),
                ));
                if (changed == true) reload();
              },
            ),
            const SizedBox(height: 16),
            for (final a in accounts)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  padding: const EdgeInsets.all(14),
                  onTap: () async {
                    await Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => FinanceAccountScreen(
                        accountId: (a['id'] as num).toInt(),
                        name: '${a['name']}',
                      ),
                    ));
                    reload();
                  },
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${a['name']}${a['is_active'] == false ? ' (archived)' : ''}',
                                style: const TextStyle(fontWeight: FontWeight.w700)),
                            _Sub('In ${_money(a['money_in'])} · out ${_money(a['money_out'])}'),
                          ],
                        ),
                      ),
                      Text(_money(a['balance']),
                          style: TextStyle(
                              fontWeight: FontWeight.w800,
                              color: ((a['balance'] as num?) ?? 0) < 0 ? AppColors.red600 : AppColors.gray900)),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class FinanceAccountScreen extends ConsumerWidget {
  const FinanceAccountScreen({super.key, required this.accountId, required this.name});
  final int accountId;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.read(financeExtrasRepositoryProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(name)),
      body: _Loader(
        load: () => repo.account(accountId),
        builder: (context, d, reload) {
          final rows = (d['statement'] as List? ?? const []).cast<Map>().reversed.toList();
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiCard(
                child: Column(
                  children: [
                    AdminRow('Balance now', _money(d['balance'])),
                    if (d['starts_on'] != null)
                      AdminRow('Counted from', '${_day(d['starts_on'])} with ${_money(d['starts_with'])}'),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Statement (newest first)'),
              const SizedBox(height: 8),
              if (rows.isEmpty) const _Sub('No money in or out yet.'),
              for (final r in rows)
                _recordCard(
                  {
                    'label': r['label'],
                    'date': r['date'],
                    'detail': [
                      if ((r['detail'] ?? '').toString().isNotEmpty) r['detail'],
                      if ((r['ref'] ?? '').toString().isNotEmpty) 'Ref ${r['ref']}',
                      'balance ${_money(r['balance'])}',
                    ].join(' · '),
                    'amount': r['amount'],
                  },
                  signed: true,
                  onTap: '${r['kind']}'.startsWith('transfer') && r['id'] != null
                      ? () async {
                          final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
                            builder: (_) => FinanceTransferScreen(transferId: (r['id'] as num).toInt()),
                          ));
                          if (changed == true) reload();
                        }
                      : null,
                ),
            ],
          );
        },
      ),
    );
  }
}

/// New transfer (pass [accounts]) or edit / remove one (pass [transferId]).
class FinanceTransferScreen extends ConsumerStatefulWidget {
  const FinanceTransferScreen({super.key, this.transferId, this.accounts});
  final int? transferId;
  final List<Map<String, dynamic>>? accounts;

  @override
  ConsumerState<FinanceTransferScreen> createState() => _FinanceTransferScreenState();
}

class _FinanceTransferScreenState extends ConsumerState<FinanceTransferScreen> {
  final _amount = TextEditingController();
  final _reference = TextEditingController();
  final _notes = TextEditingController();
  List<Map<String, dynamic>> _accounts = [];
  int? _from;
  int? _to;
  DateTime _date = DateTime.now();
  bool _loading = true;
  bool _saving = false;
  bool _readOnly = false;
  String? _error;

  FinanceExtrasRepository get _repo => ref.read(financeExtrasRepositoryProvider);

  @override
  void initState() {
    super.initState();
    if (widget.transferId == null) {
      _accounts = widget.accounts ?? [];
      _loading = false;
    } else {
      _load();
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final t = await _repo.transfer(widget.transferId!);
      if (!mounted) return;
      setState(() {
        _accounts = (t['accounts'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _from = (t['from_account_id'] as num?)?.toInt();
        _to = (t['to_account_id'] as num?)?.toInt();
        _amount.text = '${t['amount'] ?? ''}';
        _reference.text = '${t['reference'] ?? ''}';
        _notes.text = '${t['notes'] ?? ''}';
        _date = DateTime.tryParse('${t['transferred_at']}') ?? DateTime.now();
        _readOnly = t['read_only'] == true;
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = _err(e);
          _loading = false;
        });
      }
    }
  }

  Future<void> _save() async {
    final amount = double.tryParse(_amount.text.trim());
    if (_from == null || _to == null || _from == _to || amount == null || amount <= 0) {
      _toast(context, 'Pick two different accounts and an amount above zero.');
      return;
    }
    final body = {
      'from_account_id': _from,
      'to_account_id': _to,
      'amount': amount,
      'transferred_at': DateFormat('yyyy-MM-dd').format(_date),
      'reference': _reference.text.trim().isEmpty ? null : _reference.text.trim(),
      'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    };
    setState(() => _saving = true);
    try {
      final msg = widget.transferId == null
          ? await _repo.newTransfer(body)
          : await _repo.updateTransfer(widget.transferId!, body);
      if (!mounted) return;
      _toast(context, msg);
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _remove() async {
    final reason = await promptReason(
      context,
      title: 'Remove transfer',
      actionLabel: 'Remove',
      hint: 'Why is this transfer wrong?',
    );
    if (reason == null || reason.isEmpty) return;
    try {
      final msg = await _repo.deleteTransfer(widget.transferId!, reason);
      if (!mounted) return;
      _toast(context, msg);
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _toast(context, _err(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.transferId != null;
    DropdownButtonFormField<int> pick(String label, int? value, ValueChanged<int?> onChanged) =>
        DropdownButtonFormField<int>(
          initialValue: _accounts.any((a) => a['id'] == value) ? value : null,
          isExpanded: true,
          decoration: InputDecoration(labelText: label),
          items: [
            for (final a in _accounts)
              DropdownMenuItem(
                value: (a['id'] as num).toInt(),
                child: Text('${a['name']}${a['is_active'] == false ? ' (archived)' : ''}'),
              ),
          ],
          onChanged: _readOnly ? null : onChanged,
        );

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(editing ? 'Transfer' : 'New transfer'),
        actions: [
          if (editing && !_readOnly && !_loading && _error == null)
            IconButton(tooltip: 'Remove transfer', icon: const Icon(Icons.delete_outline), onPressed: _remove),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.red700)))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_readOnly)
                      const Padding(
                        padding: EdgeInsets.only(bottom: 12),
                        child: _Sub('This transfer is from before the Finance start date. It stays read-only.',
                            color: AppColors.amber700),
                      ),
                    pick('From account', _from, (v) => setState(() => _from = v)),
                    const SizedBox(height: 12),
                    pick('To account', _to, (v) => setState(() => _to = v)),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _amount,
                      enabled: !_readOnly,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(labelText: 'Amount (TZS)'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      icon: const Icon(Icons.event, size: 18),
                      label: Text('Date: ${DateFormat('d MMM y').format(_date)}'),
                      onPressed: _readOnly
                          ? null
                          : () async {
                              final d = await showDatePicker(
                                context: context,
                                initialDate: _date,
                                firstDate: DateTime(2020),
                                lastDate: DateTime.now(),
                              );
                              if (d != null) setState(() => _date = d);
                            },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _reference,
                      enabled: !_readOnly,
                      decoration: const InputDecoration(labelText: 'Reference (optional)'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _notes,
                      enabled: !_readOnly,
                      maxLines: 3,
                      decoration: const InputDecoration(labelText: 'Notes (optional)'),
                    ),
                    const SizedBox(height: 24),
                    if (!_readOnly)
                      MbuiButton(
                        label: editing ? 'Save transfer' : 'Record transfer',
                        fullWidth: true,
                        loading: _saving,
                        onPressed: _save,
                      ),
                  ],
                ),
    );
  }
}

// ============================================================== audit

class FinanceAuditTab extends ConsumerStatefulWidget {
  const FinanceAuditTab({super.key});

  @override
  ConsumerState<FinanceAuditTab> createState() => _FinanceAuditTabState();
}

class _FinanceAuditTabState extends ConsumerState<FinanceAuditTab> {
  String? _kind;
  Key _key = UniqueKey();

  @override
  Widget build(BuildContext context) {
    return _Loader(
      key: _key,
      load: () => ref.read(financeExtrasRepositoryProvider).audit(kind: _kind),
      builder: (context, d, _) {
        final checks = (d['checks'] as List? ?? const []).cast<Map>();
        final activity = (d['activity'] as List? ?? const []).cast<Map>();
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const MbuiSectionLabel('Checks to fix'),
            const SizedBox(height: 8),
            for (final c in checks)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text('${c['title']}', style: const TextStyle(fontWeight: FontWeight.w700))),
                          MbuiBadge(
                            switch (c['severity']) {
                              'problem' => '${(c['items'] as List).length} to fix',
                              'warning' => '${(c['items'] as List).length} to check',
                              _ => 'All good',
                            },
                            appearance: switch (c['severity']) {
                              'problem' => MbuiAppearance.danger,
                              'warning' => MbuiAppearance.warning,
                              _ => MbuiAppearance.success,
                            },
                          ),
                        ],
                      ),
                      if ((c['items'] as List).isNotEmpty) ...[
                        const SizedBox(height: 4),
                        _Sub('${c['help']}'),
                        const SizedBox(height: 6),
                        for (final i in (c['items'] as List).cast<Map>())
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text('• ${i['label']}${i['detail'] != null ? ' — ${i['detail']}' : ''}',
                                style: const TextStyle(fontSize: 12)),
                          ),
                      ],
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 16),
            const MbuiSectionLabel('Who did what'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                for (final e in {null: 'All', 'added': 'Added', 'changed': 'Changed', 'removed': 'Removed'}.entries)
                  ChoiceChip(
                    label: Text(e.value),
                    selected: _kind == e.key,
                    onSelected: (_) => setState(() {
                      _kind = e.key;
                      _key = UniqueKey();
                    }),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (activity.isEmpty) const _Sub('Nothing yet.'),
            for (final a in activity)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: MbuiCard(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${a['what']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                      if ((a['detail'] ?? '').toString().isNotEmpty) _Sub('${a['detail']}'),
                      _Sub('${a['who'] ?? 'System'} · ${a['at'] == null ? '' : DateFormat('d MMM y HH:mm').format(DateTime.parse('${a['at']}').toLocal())}'),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

// ============================================================== line

class FinanceLineScreen extends ConsumerWidget {
  const FinanceLineScreen({super.key, required this.lineKey, required this.label});
  final String lineKey;
  final String label;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(label)),
      body: _Loader(
        load: () => ref.read(financeExtrasRepositoryProvider).line(lineKey),
        builder: (context, d, _) {
          final row = Map<String, dynamic>.from(d['row'] as Map? ?? const {});
          Widget section(String title, String key) {
            final items = (d[key] as List? ?? const []).cast<Map>();
            if (items.isEmpty) return const SizedBox.shrink();
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 16),
                MbuiSectionLabel('$title (${items.length})'),
                const SizedBox(height: 8),
                for (final r in items) _recordCard(r),
              ],
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _Sub('${(d['period'] as Map?)?['label'] ?? ''}'),
              const SizedBox(height: 8),
              MbuiCard(
                child: Column(
                  children: [
                    AdminRow('Capital', _money(row['capital'])),
                    AdminRow('Income', _money(row['income'])),
                    AdminRow('Expenses', _money(row['expenses'])),
                    if (((row['repaid'] as num?) ?? 0) != 0) AdminRow('Loans repaid', _money(row['repaid'])),
                    AdminRow('Balance', _money(row['balance'])),
                  ],
                ),
              ),
              section('Income', 'income'),
              section('Capital & loans', 'capital'),
              section('Expenses', 'expenses'),
              section('Loan repayments', 'repaid'),
              section('Money given to this line', 'given'),
            ],
          );
        },
      ),
    );
  }
}

// ============================================================== start date

class FinanceStartScreen extends ConsumerStatefulWidget {
  const FinanceStartScreen({super.key});

  @override
  ConsumerState<FinanceStartScreen> createState() => _FinanceStartScreenState();
}

class _FinanceStartScreenState extends ConsumerState<FinanceStartScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  DateTime? _date;
  final Map<int, TextEditingController> _counted = {};
  bool _saving = false;

  FinanceExtrasRepository get _repo => ref.read(financeExtrasRepositoryProvider);

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _counted.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load([DateTime? date]) async {
    try {
      final d = await _repo.start(date: date == null ? null : DateFormat('yyyy-MM-dd').format(date));
      if (!mounted) return;
      setState(() {
        _data = d;
        _date = DateTime.tryParse('${d['date']}');
        for (final a in (d['accounts'] as List).cast<Map>()) {
          final id = (a['id'] as num).toInt();
          _counted.putIfAbsent(id, () => TextEditingController(
              text: a['counted'] == null ? '' : '${(a['counted'] as num).round()}'));
        }
      });
    } catch (e) {
      if (mounted) setState(() => _error = _err(e));
    }
  }

  Future<void> _save() async {
    if (_date == null) return;
    final ok = await confirmAdminAction(
      context,
      title: 'Count from ${DateFormat('d MMM y').format(_date!)}',
      message: 'Finance will count from this day. Earlier records stay, read-only, under Unorganised data.',
      actionLabel: 'Save start date',
    );
    if (!ok) return;
    setState(() => _saving = true);
    try {
      final balances = {
        for (final e in _counted.entries)
          '${e.key}': e.value.text.trim().isEmpty ? null : double.tryParse(e.value.text.trim()),
      };
      final msg = await _repo.saveStart(
        DateFormat('yyyy-MM-dd').format(_date!),
        balances.map((k, v) => MapEntry(k, v)),
      );
      if (!mounted) return;
      _toast(context, msg);
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _clear() async {
    final ok = await confirmAdminAction(
      context,
      title: 'Remove the start date',
      message: 'Finance will count every record again, from the very first one.',
      actionLabel: 'Remove',
      variant: MbuiVariant.danger,
    );
    if (!ok) return;
    try {
      final msg = await _repo.clearStart();
      if (!mounted) return;
      _toast(context, msg);
      Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) _toast(context, _err(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = _data;
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Finance start date')),
      body: _error != null
          ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.red700)))
          : d == null
              ? const Center(child: CircularProgressIndicator())
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _Sub(d['start'] == null
                        ? 'Finance counts every record now. Pick a day to start counting from; earlier records are kept, read-only, under Unorganised data.'
                        : 'Finance counts from ${d['start_label']}.'),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      icon: const Icon(Icons.event, size: 18),
                      label: Text('Start on ${_date == null ? '—' : DateFormat('d MMM y').format(_date!)}'),
                      onPressed: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _date ?? DateTime.now(),
                          firstDate: DateTime(2020),
                          lastDate: DateTime.now(),
                        );
                        if (picked != null) {
                          setState(() => _date = picked);
                          _load(picked);
                        }
                      },
                    ),
                    const SizedBox(height: 16),
                    const MbuiSectionLabel('Money counted in each account that day'),
                    const SizedBox(height: 4),
                    const _Sub('Leave empty to keep what the records say.'),
                    const SizedBox(height: 8),
                    for (final a in (d['accounts'] as List).cast<Map>())
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: TextField(
                          controller: _counted[(a['id'] as num).toInt()],
                          keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
                          decoration: InputDecoration(
                            labelText: '${a['name']}${a['is_active'] == false ? ' (archived)' : ''}',
                            helperText: 'Records say ${_money(a['records'])}',
                          ),
                        ),
                      ),
                    const SizedBox(height: 16),
                    MbuiButton(label: 'Save start date', fullWidth: true, loading: _saving, onPressed: _save),
                    if (d['start'] != null) ...[
                      const SizedBox(height: 10),
                      MbuiButton(
                        label: 'Count every record again',
                        variant: MbuiVariant.danger,
                        fullWidth: true,
                        onPressed: _clear,
                      ),
                    ],
                  ],
                ),
    );
  }
}

// ============================================================== unorganised

class FinanceUnorganisedScreen extends ConsumerWidget {
  const FinanceUnorganisedScreen({super.key});

  static const _tabs = {
    'summary': 'Summary',
    'sales': 'Sales',
    'expenses': 'Expenses',
    'capital': 'Capital & loans',
    'repayments': 'Loan repayments',
    'transfers': 'Transfers',
    'given': 'Given money',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: _tabs.length,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Unorganised data'),
          bottom: TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [for (final t in _tabs.values) Tab(text: t)],
          ),
        ),
        body: TabBarView(children: [for (final t in _tabs.keys) _UnorganisedTab(tab: t)]),
      ),
    );
  }
}

class _UnorganisedTab extends ConsumerStatefulWidget {
  const _UnorganisedTab({required this.tab});
  final String tab;

  @override
  ConsumerState<_UnorganisedTab> createState() => _UnorganisedTabState();
}

class _UnorganisedTabState extends ConsumerState<_UnorganisedTab> {
  final List<Map> _more = [];
  int _page = 1;
  int _last = 1;
  bool _loadingMore = false;

  @override
  Widget build(BuildContext context) {
    final repo = ref.read(financeExtrasRepositoryProvider);
    return _Loader(
      load: () async {
        final d = await repo.unorganised(widget.tab);
        _more.clear();
        final meta = d['meta'] as Map?;
        _page = (meta?['current_page'] as num?)?.toInt() ?? 1;
        _last = (meta?['last_page'] as num?)?.toInt() ?? 1;
        return d;
      },
      builder: (context, d, _) {
        if (d['start'] == null) {
          return ListView(padding: const EdgeInsets.all(24), children: const [
            Text('No start date is set, so every record is counted. Set one under "Finance start date".'),
          ]);
        }
        final rows = [...(d['data'] as List? ?? const []).cast<Map>(), ..._more];
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _Sub('Records from before ${d['start_label']}. Read-only.'),
            const SizedBox(height: 10),
            if (widget.tab == 'summary') ...[
              MbuiCard(
                child: Column(
                  children: [
                    for (final e in (d['totals'] as Map? ?? const {}).entries)
                      AdminRow('${e.key}'[0].toUpperCase() + '${e.key}'.substring(1), _money(e.value)),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              for (final a in (d['accounts'] as List? ?? const []).cast<Map>())
                _recordCard({
                  'label': a['name'],
                  'detail': a['counted_at_start'] == null ? null : 'counted ${_money(a['counted_at_start'])} at the start',
                  'amount': a['balance'],
                }),
              const SizedBox(height: 12),
              for (final r in rows)
                _recordCard({
                  'label': r['label'],
                  'detail': 'Capital ${_money(r['capital'])} · Income ${_money(r['income'])} · Expenses ${_money(r['expenses'])}',
                  'amount': r['balance'],
                }, signed: true),
            ] else ...[
              if (rows.isEmpty) const _Sub('Nothing here.'),
              for (final r in rows) _recordCard(r),
              if (_page < _last)
                MbuiButton(
                  label: 'Load more',
                  variant: MbuiVariant.secondary,
                  fullWidth: true,
                  loading: _loadingMore,
                  onPressed: () async {
                    setState(() => _loadingMore = true);
                    try {
                      final next = await repo.unorganised(widget.tab, page: _page + 1);
                      final meta = next['meta'] as Map?;
                      setState(() {
                        _more.addAll((next['data'] as List).cast<Map>());
                        _page = (meta?['current_page'] as num?)?.toInt() ?? _page + 1;
                        _last = (meta?['last_page'] as num?)?.toInt() ?? _last;
                      });
                    } catch (e) {
                      if (context.mounted) _toast(context, _err(e));
                    } finally {
                      if (mounted) setState(() => _loadingMore = false);
                    }
                  },
                ),
            ],
          ],
        );
      },
    );
  }
}
