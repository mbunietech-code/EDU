import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api_client.dart';
import '../../data/admin_finance_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';
import 'admin_form_kit.dart';

class AdminFinanceScreen extends ConsumerWidget {
  const AdminFinanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(financeStatusProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Finance')),
      body: AsyncValueView<FinanceStatus>(
        value: status,
        onRefresh: () async => ref.refresh(financeStatusProvider.future),
        data: (s) {
          if (!s.pinSet) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'No Finance PIN is set yet. Set one on the website first.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.gray500),
                ),
              ),
            );
          }
          if (!s.unlocked) return const _PinGate();
          return const _FinanceTabs();
        },
      ),
    );
  }
}

class _PinGate extends ConsumerStatefulWidget {
  const _PinGate();

  @override
  ConsumerState<_PinGate> createState() => _PinGateState();
}

class _PinGateState extends ConsumerState<_PinGate> {
  final _pin = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _pin.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_pin.text.trim().length != 6) {
      setState(() => _error = 'Enter the 6-digit PIN.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(adminFinanceRepositoryProvider).unlock(_pin.text.trim());
      ref.invalidate(financeStatusProvider);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = e.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: MbuiCard(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.lock_outline, size: 32, color: AppColors.indigo600),
                const SizedBox(height: 12),
                const Text('Finance is locked',
                    style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.gray900)),
                const SizedBox(height: 4),
                const Text('Enter the 6-digit PIN to unlock for 30 minutes.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12, color: AppColors.gray500)),
                const SizedBox(height: 16),
                TextField(
                  controller: _pin,
                  keyboardType: TextInputType.number,
                  obscureText: true,
                  maxLength: 6,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 22, letterSpacing: 8),
                  decoration: const InputDecoration(counterText: ''),
                  onSubmitted: (_) => _submit(),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(_error!,
                      style: const TextStyle(
                          color: AppColors.red700, fontSize: 12)),
                ],
                const SizedBox(height: 16),
                MbuiButton(
                  label: 'Unlock',
                  loading: _busy,
                  fullWidth: true,
                  onPressed: _submit,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FinanceTabs extends StatelessWidget {
  const _FinanceTabs();

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          const Material(
            color: Colors.white,
            child: TabBar(tabs: [
              Tab(text: 'Overview'),
              Tab(text: 'Expenses'),
              Tab(text: 'Capital'),
            ]),
          ),
          const Expanded(
            child: TabBarView(children: [
              _OverviewTab(),
              _ExpensesTab(),
              _CapitalTab(),
            ]),
          ),
        ],
      ),
    );
  }
}

class _OverviewTab extends ConsumerWidget {
  const _OverviewTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financeOverviewProvider);
    return AsyncValueView<FinanceOverview>(
      value: async,
      onRefresh: () async => ref.refresh(financeOverviewProvider.future),
      data: (o) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _TotalsCard(totals: o.totals),
          const SizedBox(height: 16),
          const MbuiSectionLabel('By product / tool'),
          const SizedBox(height: 8),
          for (final r in o.rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(r.label,
                              style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.gray900)),
                        ),
                        Text(_money(r.balance),
                            style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: r.balance < 0
                                    ? AppColors.red600
                                    : AppColors.emerald700)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Capital ${_money(r.capital)} · Income ${_money(r.income)} · Expenses ${_money(r.expenses)}',
                      style: const TextStyle(
                          fontSize: 11, color: AppColors.gray500),
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

class _TotalsCard extends StatelessWidget {
  const _TotalsCard({required this.totals});
  final FinanceRow totals;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      child: Column(
        children: [
          _line('Capital', totals.capital),
          _line('Income', totals.income),
          _line('Expenses', -totals.expenses),
          const Divider(height: 20),
          _line('Balance', totals.balance, bold: true),
        ],
      ),
    );
  }

  Widget _line(String label, double value, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          children: [
            Expanded(
              child: Text(label,
                  style: TextStyle(
                      fontSize: bold ? 15 : 13,
                      fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
                      color: AppColors.gray700)),
            ),
            Text(_money(value),
                style: TextStyle(
                    fontSize: bold ? 16 : 13,
                    fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
                    color: value < 0 ? AppColors.red600 : AppColors.gray900)),
          ],
        ),
      );
}

class _ExpensesTab extends ConsumerWidget {
  const _ExpensesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financeExpensesProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'exp',
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const FinanceEntryForm(isExpense: true),
        )),
        icon: const Icon(Icons.add),
        label: const Text('Expense'),
      ),
      body: AsyncValueView<List<FinanceExpenseRow>>(
        value: async,
        onRefresh: () async => ref.refresh(financeExpensesProvider.future),
        data: (items) => ListView.builder(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          itemCount: items.length,
          itemBuilder: (context, i) {
            final e = items[i];
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => FinanceEntryForm(isExpense: true, existingExpense: e),
                )),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(e.label,
                              style: const TextStyle(
                                  fontSize: 14, fontWeight: FontWeight.w700)),
                        ),
                        Text(e.amountLabel,
                            style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppColors.red600)),
                        const SizedBox(width: 4),
                        _DeleteIconButton(
                          onDelete: (reason) => ref
                              .read(adminFinanceRepositoryProvider)
                              .deleteExpense(e.id, reason),
                          onDeleted: () {
                            ref.invalidate(financeExpensesProvider);
                            ref.invalidate(financeOverviewProvider);
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        e.target ?? '',
                        if (e.category != null) e.category!,
                        if (e.spentAt != null) e.spentAt!,
                      ].where((s) => s.isNotEmpty).join(' · '),
                      style: const TextStyle(
                          fontSize: 11, color: AppColors.gray500),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _CapitalTab extends ConsumerWidget {
  const _CapitalTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(financeCapitalProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'cap',
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const FinanceEntryForm(isExpense: false),
        )),
        icon: const Icon(Icons.add),
        label: const Text('Capital'),
      ),
      body: AsyncValueView<List<FinanceCapitalRow>>(
        value: async,
        onRefresh: () async => ref.refresh(financeCapitalProvider.future),
        data: (items) => ListView.builder(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          itemCount: items.length,
          itemBuilder: (context, i) {
            final c = items[i];
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => FinanceEntryForm(isExpense: false, existingCapital: c),
                )),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(c.label,
                              style: const TextStyle(
                                  fontSize: 14, fontWeight: FontWeight.w700)),
                        ),
                        Text(c.amountLabel,
                            style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppColors.emerald700)),
                        const SizedBox(width: 4),
                        _DeleteIconButton(
                          onDelete: (reason) => ref
                              .read(adminFinanceRepositoryProvider)
                              .deleteCapital(c.id, reason),
                          onDeleted: () {
                            ref.invalidate(financeCapitalProvider);
                            ref.invalidate(financeOverviewProvider);
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        c.target ?? '',
                        if (c.source != null) c.source!,
                        if (c.isLoan) 'loan',
                      ].where((s) => s.isNotEmpty).join(' · '),
                      style: const TextStyle(
                          fontSize: 11, color: AppColors.gray500),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _DeleteIconButton extends ConsumerStatefulWidget {
  const _DeleteIconButton({required this.onDelete, required this.onDeleted});
  final Future<void> Function(String reason) onDelete;
  final VoidCallback onDeleted;

  @override
  ConsumerState<_DeleteIconButton> createState() => _DeleteIconButtonState();
}

class _DeleteIconButtonState extends ConsumerState<_DeleteIconButton> {
  bool _busy = false;

  Future<void> _delete() async {
    final reason = await promptReason(
      context,
      title: 'Delete entry',
      actionLabel: 'Delete',
      hint: 'Why are you deleting this? This cannot be undone.',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      await widget.onDelete(reason);
      widget.onDeleted();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Deleted.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _busy
        ? const SizedBox(
            height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
        : IconButton(
            icon: const Icon(Icons.delete_outline, size: 20, color: AppColors.red600),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
            visualDensity: VisualDensity.compact,
            onPressed: _delete,
          );
  }
}

class FinanceEntryForm extends ConsumerStatefulWidget {
  const FinanceEntryForm({
    super.key,
    required this.isExpense,
    this.existingExpense,
    this.existingCapital,
  });
  final bool isExpense;
  final FinanceExpenseRow? existingExpense;
  final FinanceCapitalRow? existingCapital;

  bool get isEditing => existingExpense != null || existingCapital != null;

  @override
  ConsumerState<FinanceEntryForm> createState() => _FinanceEntryFormState();
}

class _FinanceEntryFormState extends ConsumerState<FinanceEntryForm> {
  final _label = TextEditingController();
  final _amount = TextEditingController();
  final _source = TextEditingController();
  final _notes = TextEditingController();
  String? _category;
  bool _isLoan = false;
  DateTime _spentAt = DateTime.now();
  XFile? _receipt;
  bool _saving = false;
  bool _loadingDetail = false;

  @override
  void initState() {
    super.initState();
    if (widget.existingExpense != null) {
      _loadingDetail = true;
      ref
          .read(adminFinanceRepositoryProvider)
          .expenseDetail(widget.existingExpense!.id)
          .then((d) {
        if (!mounted) return;
        setState(() {
          _label.text = d.label;
          _amount.text = d.amount.toStringAsFixed(0);
          _category = d.category;
          _notes.text = d.description ?? '';
          if (d.spentAt != null) {
            _spentAt = DateTime.tryParse(d.spentAt!) ?? _spentAt;
          }
          _loadingDetail = false;
        });
      }).catchError((_) {
        if (mounted) setState(() => _loadingDetail = false);
      });
    } else if (widget.existingCapital != null) {
      _loadingDetail = true;
      ref
          .read(adminFinanceRepositoryProvider)
          .capitalDetail(widget.existingCapital!.id)
          .then((d) {
        if (!mounted) return;
        setState(() {
          _label.text = d.label;
          _amount.text = d.amount.toStringAsFixed(0);
          _source.text = d.source ?? '';
          _isLoan = d.isLoan;
          _notes.text = d.notes ?? '';
          _loadingDetail = false;
        });
      }).catchError((_) {
        if (mounted) setState(() => _loadingDetail = false);
      });
    }
  }

  Future<void> _delete() async {
    final id = widget.existingExpense?.id ?? widget.existingCapital?.id;
    if (id == null) return;

    final reason = await promptReason(
      context,
      title: 'Delete entry',
      actionLabel: 'Delete',
      hint: 'Why are you deleting this? This cannot be undone.',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _saving = true);
    try {
      final repo = ref.read(adminFinanceRepositoryProvider);
      if (widget.isExpense) {
        await repo.deleteExpense(id, reason);
        ref.invalidate(financeExpensesProvider);
      } else {
        await repo.deleteCapital(id, reason);
        ref.invalidate(financeCapitalProvider);
      }
      ref.invalidate(financeOverviewProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Deleted.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    for (final c in [_label, _amount, _source, _notes]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminFinanceRepositoryProvider);
      if (widget.isExpense) {
        final body = {
          'label': _label.text.trim(),
          'amount': double.tryParse(_amount.text.trim()) ?? 0,
          if (_category != null) 'category': _category,
          'description': _notes.text.trim(),
          'spent_at':
              '${_spentAt.year}-${_spentAt.month.toString().padLeft(2, '0')}-${_spentAt.day.toString().padLeft(2, '0')}',
        };
        if (widget.existingExpense != null) {
          await repo.updateExpense(widget.existingExpense!.id, body);
        } else {
          await repo.addExpense(body, receiptPath: _receipt?.path);
        }
        ref.invalidate(financeExpensesProvider);
      } else {
        final body = {
          'label': _label.text.trim(),
          'amount': double.tryParse(_amount.text.trim()) ?? 0,
          'source': _source.text.trim(),
          'is_loan': _isLoan,
          'notes': _notes.text.trim(),
        };
        if (widget.existingCapital != null) {
          await repo.updateCapital(widget.existingCapital!.id, body);
        } else {
          await repo.addCapital(body);
        }
        ref.invalidate(financeCapitalProvider);
      }
      ref.invalidate(financeOverviewProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(widget.isEditing ? 'Updated.' : 'Recorded.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final targets = ref.watch(financeTargetsProvider);
    if (_loadingDetail) {
      return Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: const Text('Loading…')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    return AdminFormScaffold(
      title: widget.isEditing
          ? 'Edit ${widget.isExpense ? 'expense' : 'capital entry'}'
          : (widget.isExpense ? 'New expense' : 'New capital entry'),
      saveLabel: widget.isEditing ? 'Save changes' : 'Record',
      saving: _saving,
      onSave: _save,
      onDelete: widget.isEditing ? _delete : null,
      children: [
        LabeledInput(label: 'Label', child: TextField(controller: _label)),
        LabeledInput(
          label: 'Amount (TZS)',
          child: TextField(
              controller: _amount, keyboardType: TextInputType.number),
        ),
        if (widget.isExpense) ...[
          LabeledInput(
            label: 'Category',
            child: targets.when(
              data: (t) => DropdownButtonFormField<String>(
                initialValue: _category,
                items: [
                  for (final c in t.categories)
                    DropdownMenuItem(value: c, child: Text(c)),
                ],
                onChanged: (v) => setState(() => _category = v),
              ),
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const Text('—'),
            ),
          ),
          LabeledInput(
            label: 'Date',
            child: InkWell(
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _spentAt,
                  firstDate: DateTime(2020),
                  lastDate: DateTime(2100),
                );
                if (picked != null) setState(() => _spentAt = picked);
              },
              child: InputDecorator(
                decoration: const InputDecoration(),
                child: Text(
                    '${_spentAt.year}-${_spentAt.month.toString().padLeft(2, '0')}-${_spentAt.day.toString().padLeft(2, '0')}'),
              ),
            ),
          ),
          LabeledInput(
            label: 'Receipt (image, optional)',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_receipt != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: Image.file(File(_receipt!.path),
                        height: 130, fit: BoxFit.cover),
                  ),
                  const SizedBox(height: 8),
                ],
                MbuiButton(
                  label: _receipt == null ? 'Attach receipt' : 'Replace receipt',
                  variant: MbuiVariant.secondary,
                  icon: Icons.receipt_long_outlined,
                  onPressed: () async {
                    final picked = await ImagePicker().pickImage(
                      source: ImageSource.gallery,
                      maxWidth: 1600,
                      imageQuality: 85,
                    );
                    if (picked != null) setState(() => _receipt = picked);
                  },
                ),
              ],
            ),
          ),
        ] else ...[
          LabeledInput(label: 'Source', child: TextField(controller: _source)),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('This is a loan', style: TextStyle(fontSize: 14)),
            value: _isLoan,
            onChanged: (v) => setState(() => _isLoan = v),
          ),
        ],
        LabeledInput(
          label: widget.isExpense ? 'Description' : 'Notes',
          child: TextField(controller: _notes, maxLines: 3),
        ),
      ],
    );
  }
}

String _money(double v) {
  final neg = v < 0;
  final s = v.abs().toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},');
  return '${neg ? '-' : ''}TZS $s';
}
