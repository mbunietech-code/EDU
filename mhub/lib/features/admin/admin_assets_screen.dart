import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/admin_assets_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

String _day(String? iso) {
  if (iso == null) return '—';
  final d = DateTime.tryParse(iso);
  return d == null ? iso : DateFormat('d MMM y').format(d);
}

String _ends(AdminAsset a) {
  final d = a.daysLeft;
  if (d == null) return 'Never ends';
  if (d < 0) return 'Ended ${-d} day${d == -1 ? '' : 's'} ago';
  if (d == 0) return 'Ends today';
  return 'Ends in $d day${d == 1 ? '' : 's'}';
}

MbuiAppearance _stateAppearance(String s) => switch (s) {
      'expired' => MbuiAppearance.danger,
      'soon' => MbuiAppearance.warning,
      'active' => MbuiAppearance.success,
      _ => MbuiAppearance.neutral,
    };

String _cost(AdminAsset a) => a.cost == null
    ? '—'
    : '${a.currency} ${NumberFormat(a.currency == 'TZS' ? '#,##0' : '#,##0.00').format(a.cost)}';

void _snack(BuildContext context, String msg) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));

// ============================================================== list

class AdminAssetsScreen extends ConsumerStatefulWidget {
  const AdminAssetsScreen({super.key});

  @override
  ConsumerState<AdminAssetsScreen> createState() => _AdminAssetsScreenState();
}

class _AdminAssetsScreenState extends ConsumerState<AdminAssetsScreen> {
  final _search = TextEditingController();
  final List<AdminAsset> _more = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _search.text = ref.read(adminAssetSearchProvider);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final next = await ref.read(adminAssetsRepositoryProvider).assets(
            status: ref.read(adminAssetStatusProvider),
            category: ref.read(adminAssetCategoryProvider),
            q: ref.read(adminAssetSearchProvider),
            page: _page + 1,
          );
      if (!mounted) return;
      setState(() {
        _more.addAll(next.items);
        _page = next.currentPage;
        _lastPage = next.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) _snack(context, e.message);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
    ref.invalidate(adminAssetsProvider);
  }

  Future<void> _pickCategory(Map<String, String> categories) async {
    final current = ref.read(adminAssetCategoryProvider);
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            for (final e in {'': 'All categories', ...categories}.entries)
              ListTile(
                title: Text(e.value),
                trailing: (current ?? '') == e.key
                    ? const Icon(Icons.check, color: AppColors.indigo600)
                    : null,
                onTap: () => Navigator.pop(ctx, e.key),
              ),
          ],
        ),
      ),
    );
    if (picked == null) return;
    ref.read(adminAssetCategoryProvider.notifier).state =
        picked.isEmpty ? null : picked;
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<AdminAssetPage>>(adminAssetsProvider, (_, next) {
      final p = next.valueOrNull;
      if (p == null) return;
      setState(() {
        _more.clear();
        _page = p.currentPage;
        _lastPage = p.lastPage;
      });
    });

    final async = ref.watch(adminAssetsProvider);
    final status = ref.watch(adminAssetStatusProvider);
    final category = ref.watch(adminAssetCategoryProvider);
    final search = ref.watch(adminAssetSearchProvider);
    final page = async.valueOrNull;
    final counts = page?.counts ?? const <String, int>{};
    final categories = page?.categories ?? const <String, String>{};

    String withCount(String key, String label) =>
        counts.containsKey(key) ? '$label ${counts[key]}' : label;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Company assets')),
      floatingActionButton: (page?.canManage ?? false)
          ? FloatingActionButton.extended(
              onPressed: () => _open(const AdminAssetFormScreen()),
              icon: const Icon(Icons.add),
              label: const Text('Add asset'),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _search,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (v) => ref
                        .read(adminAssetSearchProvider.notifier)
                        .state = v.trim(),
                    decoration: InputDecoration(
                      isDense: true,
                      hintText: 'Name, vendor or account',
                      prefixIcon: const Icon(Icons.search, size: 20),
                      suffixIcon: search.isEmpty
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.close, size: 18),
                              tooltip: 'Clear search',
                              onPressed: () {
                                _search.clear();
                                ref
                                    .read(adminAssetSearchProvider.notifier)
                                    .state = '';
                              },
                            ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                FilterChip(
                  label: Text(category == null
                      ? 'Category'
                      : (categories[category] ?? category)),
                  selected: category != null,
                  onSelected: (_) => _pickCategory(categories),
                ),
              ],
            ),
          ),
          AdminFilterBar(
            options: {
              'all': withCount('all', 'All'),
              'attention': 'Needs attention',
              'expired': withCount('expired', 'Ended'),
              'soon': withCount('soon', 'Ending soon'),
              'active': 'Active',
              'no_expiry': 'No end date',
            },
            selected: status,
            onSelected: (v) =>
                ref.read(adminAssetStatusProvider.notifier).state = v ?? 'all',
          ),
          Expanded(
            child: AsyncValueView<AdminAssetPage>(
              value: async,
              onRefresh: () async => ref.refresh(adminAssetsProvider.future),
              data: (p) {
                final list = [...p.items, ..._more];
                if (list.isEmpty) {
                  return ListView(children: const [
                    SizedBox(height: 120),
                    Center(
                      child: Text('No assets here.',
                          style: TextStyle(color: AppColors.gray500)),
                    ),
                  ]);
                }
                final hasMore = _page < _lastPage;
                return ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                  itemCount: list.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, i) {
                    if (i == list.length) {
                      return MbuiButton(
                        label: 'Load more',
                        variant: MbuiVariant.secondary,
                        fullWidth: true,
                        loading: _loadingMore,
                        onPressed: _loadMore,
                      );
                    }
                    final a = list[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        padding: const EdgeInsets.all(14),
                        onTap: () => _open(AdminAssetDetailScreen(assetId: a.id)),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(a.name,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                ),
                                MbuiBadge(_ends(a),
                                    appearance: _stateAppearance(a.state)),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [
                                a.categoryLabel,
                                if ((a.vendor ?? '').isNotEmpty) a.vendor!,
                                a.cycleLabel,
                                if (a.cost != null) _cost(a),
                              ].join(' · '),
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500),
                            ),
                            if (a.responsibleName != null)
                              Text('Looked after by ${a.responsibleName}',
                                  style: const TextStyle(
                                      fontSize: 12, color: AppColors.gray500)),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================== detail

class AdminAssetDetailScreen extends ConsumerWidget {
  const AdminAssetDetailScreen({super.key, required this.assetId});
  final int assetId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminAssetProvider(assetId));
    final canManage = ref.watch(adminAssetsProvider).valueOrNull?.canManage ?? true;
    final repo = ref.read(adminAssetsRepositoryProvider);
    void refresh() => ref.invalidate(adminAssetProvider(assetId));

    Future<void> run(Future<String> Function() action, {bool pop = false}) async {
      try {
        final msg = await action();
        if (!context.mounted) return;
        _snack(context, msg);
        if (pop) {
          Navigator.of(context).pop();
        } else {
          refresh();
        }
      } on ApiException catch (e) {
        if (context.mounted) _snack(context, e.message);
      }
    }

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Asset'),
        actions: [
          if (canManage && async.hasValue) ...[
            IconButton(
              tooltip: 'Edit asset',
              icon: const Icon(Icons.edit_outlined),
              onPressed: () async {
                await Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => AdminAssetFormScreen(asset: async.value),
                ));
                refresh();
              },
            ),
            IconButton(
              tooltip: 'Delete asset',
              icon: const Icon(Icons.delete_outline),
              onPressed: () async {
                final ok = await confirmAdminAction(
                  context,
                  title: 'Delete asset',
                  message: 'Delete ${async.value!.name}?',
                  actionLabel: 'Delete',
                  variant: MbuiVariant.danger,
                );
                if (ok) await run(() => repo.delete(assetId), pop: true);
              },
            ),
          ],
        ],
      ),
      body: AsyncValueView<AdminAsset>(
        value: async,
        onRefresh: () async => ref.refresh(adminAssetProvider(assetId).future),
        data: (a) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiTitle(a.name),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: MbuiBadge(_ends(a), appearance: _stateAppearance(a.state)),
            ),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Category', a.categoryLabel),
                  AdminRow('Bought from', a.vendor ?? '—'),
                  AdminRow('Account', a.account ?? '—'),
                  AdminRow('Bought on', _day(a.purchasedAt)),
                  AdminRow('Ends on', _day(a.expiresAt)),
                  AdminRow('Billing', a.cycleLabel),
                  AdminRow('Cost', _cost(a)),
                  AdminRow('Auto-renews', a.autoRenew ? 'Yes' : 'No'),
                  AdminRow(
                      'Looked after by',
                      a.responsibleName == null
                          ? 'Nobody set'
                          : '${a.responsibleName}${a.responsibleJobTitle != null ? ' (${a.responsibleJobTitle})' : ''}'),
                ],
              ),
            ),
            if ((a.notes ?? '').isNotEmpty) ...[
              const SizedBox(height: 12),
              MbuiCard(child: SelectableText(a.notes!)),
            ],
            const SizedBox(height: 16),
            if ((a.purchaseUrl ?? '').isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiButton(
                  label: 'Open where it is bought',
                  variant: MbuiVariant.secondary,
                  icon: Icons.open_in_new,
                  fullWidth: true,
                  onPressed: () {
                    final uri = Uri.tryParse(a.purchaseUrl!);
                    if (uri != null && (uri.scheme == 'https' || uri.scheme == 'http')) {
                      launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                ),
              ),
            if (canManage && a.canRenew)
              MbuiButton(
                label: 'Paid again: renew',
                icon: Icons.autorenew,
                fullWidth: true,
                onPressed: () async {
                  final ok = await confirmAdminAction(
                    context,
                    title: 'Renew ${a.name}',
                    message:
                        'Mark it as bought today. The end date moves one billing cycle (${a.cycleLabel.toLowerCase()}) later.',
                    actionLabel: 'Renew',
                  );
                  if (ok) await run(() => repo.renew(a.id));
                },
              ),
          ],
        ),
      ),
    );
  }
}

// ============================================================== form

class AdminAssetFormScreen extends ConsumerStatefulWidget {
  const AdminAssetFormScreen({super.key, this.asset});
  final AdminAsset? asset;

  @override
  ConsumerState<AdminAssetFormScreen> createState() =>
      _AdminAssetFormScreenState();
}

class _AdminAssetFormScreenState extends ConsumerState<AdminAssetFormScreen> {
  late final _name = TextEditingController(text: widget.asset?.name ?? '');
  late final _vendor = TextEditingController(text: widget.asset?.vendor ?? '');
  late final _url = TextEditingController(text: widget.asset?.purchaseUrl ?? '');
  late final _account = TextEditingController(text: widget.asset?.account ?? '');
  late final _cost = TextEditingController(
      text: widget.asset?.cost == null ? '' : '${widget.asset!.cost}');
  late final _currency =
      TextEditingController(text: widget.asset?.currency ?? 'TZS');
  late final _notes = TextEditingController(text: widget.asset?.notes ?? '');
  late String _category = widget.asset?.category ?? 'domain';
  late String _cycle = widget.asset?.billingCycle ?? 'yearly';
  late int? _responsible = widget.asset?.responsibleId;
  late bool _autoRenew = widget.asset?.autoRenew ?? false;
  late DateTime? _purchased = widget.asset?.purchasedAt == null
      ? null
      : DateTime.tryParse(widget.asset!.purchasedAt!);
  late DateTime? _expires = widget.asset?.expiresAt == null
      ? null
      : DateTime.tryParse(widget.asset!.expiresAt!);
  bool _saving = false;

  @override
  void dispose() {
    for (final c in [_name, _vendor, _url, _account, _cost, _currency, _notes]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<DateTime?> _pick(DateTime? current) {
    final now = DateTime.now();
    return showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 15),
      lastDate: DateTime(now.year + 15),
    );
  }

  String? _text(TextEditingController c) =>
      c.text.trim().isEmpty ? null : c.text.trim();

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      _snack(context, 'Give the asset a name.');
      return;
    }
    final f = DateFormat('yyyy-MM-dd');
    final body = {
      'name': _name.text.trim(),
      'category': _category,
      'vendor': _text(_vendor),
      'purchase_url': _text(_url),
      'account': _text(_account),
      'purchased_at': _purchased == null ? null : f.format(_purchased!),
      'expires_at': _expires == null ? null : f.format(_expires!),
      'billing_cycle': _cycle,
      'cost': _text(_cost) == null ? null : double.tryParse(_cost.text.trim()),
      'currency': _currency.text.trim().toUpperCase(),
      'auto_renew': _autoRenew,
      'responsible_id': _responsible,
      'notes': _text(_notes),
    };
    setState(() => _saving = true);
    final repo = ref.read(adminAssetsRepositoryProvider);
    try {
      final msg = widget.asset == null
          ? await repo.create(body)
          : await repo.update(widget.asset!.id, body);
      if (!mounted) return;
      _snack(context, msg);
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) {
        final first = e.errors?.values.expand((v) => v).firstOrNull;
        _snack(context, first ?? e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final options = ref.watch(adminAssetOptionsProvider);
    final df = DateFormat('d MMM y');
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
          title: Text(widget.asset == null ? 'Add asset' : 'Edit asset')),
      body: options.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (o) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextField(
              controller: _name,
              decoration: const InputDecoration(
                  labelText: 'Name', hintText: 'e.g. mbuniehub.com domain'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue:
                  o.categories.containsKey(_category) ? _category : null,
              decoration: const InputDecoration(labelText: 'Category'),
              items: [
                for (final e in o.categories.entries)
                  DropdownMenuItem(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) => setState(() => _category = v ?? _category),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _vendor,
              decoration: const InputDecoration(
                  labelText: 'Bought from (optional)',
                  hintText: 'e.g. Hostinger'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _url,
              keyboardType: TextInputType.url,
              decoration: const InputDecoration(
                  labelText: 'Link to where it is bought (optional)',
                  hintText: 'https://...'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _account,
              decoration: const InputDecoration(
                  labelText: 'Account used (optional)',
                  hintText: 'Login email or account name, never a password'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () async {
                      final d = await _pick(_purchased);
                      if (d != null) setState(() => _purchased = d);
                    },
                    child: Text(_purchased == null
                        ? 'Bought on: not set'
                        : 'Bought ${df.format(_purchased!)}'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () async {
                      final d = await _pick(_expires);
                      if (d != null) setState(() => _expires = d);
                    },
                    child: Text(_expires == null
                        ? 'Ends: never'
                        : 'Ends ${df.format(_expires!)}'),
                  ),
                ),
              ],
            ),
            if (_expires != null)
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () => setState(() => _expires = null),
                  child: const Text('It never ends'),
                ),
              ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: o.cycles.containsKey(_cycle) ? _cycle : null,
              decoration: const InputDecoration(labelText: 'Billing'),
              items: [
                for (final e in o.cycles.entries)
                  DropdownMenuItem(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) => setState(() => _cycle = v ?? _cycle),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextField(
                    controller: _cost,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    decoration:
                        const InputDecoration(labelText: 'Cost (optional)'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: _currency,
                    maxLength: 3,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                        labelText: 'Currency', counterText: ''),
                  ),
                ),
              ],
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Renews by itself'),
              value: _autoRenew,
              onChanged: (v) => setState(() => _autoRenew = v),
            ),
            DropdownButtonFormField<int?>(
              initialValue:
                  o.admins.containsKey(_responsible) ? _responsible : null,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Looked after by'),
              items: [
                const DropdownMenuItem<int?>(
                    value: null, child: Text('Nobody set')),
                for (final e in o.admins.entries)
                  DropdownMenuItem<int?>(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) => setState(() => _responsible = v),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _notes,
              minLines: 3,
              maxLines: 6,
              decoration: const InputDecoration(
                  labelText: 'Notes (optional)', alignLabelWithHint: true),
            ),
            const SizedBox(height: 24),
            MbuiButton(
              label: widget.asset == null ? 'Add asset' : 'Save changes',
              fullWidth: true,
              loading: _saving,
              onPressed: _save,
            ),
          ],
        ),
      ),
    );
  }
}
