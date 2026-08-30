import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_accounts_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';
import 'admin_form_kit.dart';

class AdminAccountsScreen extends ConsumerWidget {
  const AdminAccountsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(vaultAccountsProvider);
    final filter = ref.watch(vaultStatusFilterProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Shared accounts')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const AccountFormScreen(),
        )),
        icon: const Icon(Icons.add),
        label: const Text('New'),
      ),
      body: Column(
        children: [
          const SizedBox(height: 8),
          AdminFilterBar(
            options: const {
              null: 'All',
              'available': 'Available',
              'assigned': 'Assigned',
              'suspended': 'Suspended',
              'maintenance': 'Maintenance',
              'archived': 'Archived',
            },
            selected: filter,
            onSelected: (v) =>
                ref.read(vaultStatusFilterProvider.notifier).state = v,
          ),
          const SizedBox(height: 4),
          Expanded(
            child: AsyncValueView<List<VaultAccount>>(
              value: async,
              onRefresh: () async => ref.refresh(vaultAccountsProvider.future),
              data: (items) {
                if (items.isEmpty) {
                  return const Center(
                      child: Text('No accounts here.',
                          style: TextStyle(color: AppColors.gray500)));
                }
                return ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
                  itemCount: items.length,
                  itemBuilder: (context, i) {
                    final a = items[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => AccountDetailScreen(id: a.id),
                        )),
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(a.name,
                                      style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.gray900)),
                                  Text(
                                    '${a.product}${a.hasCredentials ? "" : " · no credentials"}',
                                    style: const TextStyle(
                                        fontSize: 12, color: AppColors.gray500),
                                  ),
                                ],
                              ),
                            ),
                            MbuiStatusBadge(a.status),
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

class AccountDetailScreen extends ConsumerStatefulWidget {
  const AccountDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<AccountDetailScreen> createState() =>
      _AccountDetailScreenState();
}

class _AccountDetailScreenState extends ConsumerState<AccountDetailScreen> {
  String? _revealed;
  bool _busy = false;

  Future<void> _reveal() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Show credentials?'),
        content: const Text(
            'The decrypted credentials will be shown on screen. This action '
            'is logged in the activity log.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Show')),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      final value =
          await ref.read(adminAccountsRepositoryProvider).reveal(widget.id);
      setState(() => _revealed = value ?? '(empty)');
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _archive() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Archive account?'),
        content: const Text('It will no longer be used for new assignments.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.red600),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Archive'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(adminAccountsRepositoryProvider).archive(widget.id);
      ref.invalidate(vaultAccountProvider(widget.id));
      ref.invalidate(vaultAccountsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Account archived.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(vaultAccountProvider(widget.id));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Account'),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            onPressed: () {
              final acc = async.asData?.value;
              if (acc != null) {
                Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => AccountFormScreen(account: acc),
                ));
              }
            },
          ),
        ],
      ),
      body: AsyncValueView<VaultAccount>(
        value: async,
        onRefresh: () async =>
            ref.refresh(vaultAccountProvider(widget.id).future),
        data: (a) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: MbuiTitle(a.name)),
                MbuiStatusBadge(a.status),
              ],
            ),
            const SizedBox(height: 12),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Product', a.product),
                  if (a.description != null) AdminRow('Notes', a.description!),
                  AdminRow('Credentials',
                      a.hasCredentials ? 'Stored (encrypted)' : 'None'),
                ],
              ),
            ),
            if (a.hasCredentials) ...[
              const SizedBox(height: 16),
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const MbuiSectionLabel('Credentials'),
                    const SizedBox(height: 8),
                    if (_revealed != null)
                      SelectableText(_revealed!,
                          style: const TextStyle(
                              fontSize: 13, fontFamily: 'monospace'))
                    else
                      MbuiButton(
                        label: 'Show credentials',
                        variant: MbuiVariant.secondary,
                        icon: Icons.visibility_outlined,
                        loading: _busy,
                        onPressed: _reveal,
                      ),
                  ],
                ),
              ),
            ],
            if (a.subscriptions.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('In use by'),
              const SizedBox(height: 8),
              for (final s in a.subscriptions)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(child: Text(s.user,
                            style: const TextStyle(fontSize: 13))),
                        MbuiStatusBadge(s.status),
                      ],
                    ),
                  ),
                ),
            ],
            if (a.status != 'archived' && a.status != 'assigned') ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: 'Archive account',
                variant: MbuiVariant.danger,
                fullWidth: true,
                onPressed: _archive,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class AccountFormScreen extends ConsumerStatefulWidget {
  const AccountFormScreen({super.key, this.account});
  final VaultAccount? account;

  @override
  ConsumerState<AccountFormScreen> createState() => _AccountFormScreenState();
}

class _AccountFormScreenState extends ConsumerState<AccountFormScreen> {
  final _name = TextEditingController();
  final _desc = TextEditingController();
  final _credentials = TextEditingController();
  int? _productId;
  String _status = 'available';
  bool _saving = false;

  VaultAccount? get a => widget.account;
  bool get isNew => a == null;

  @override
  void initState() {
    super.initState();
    _name.text = a?.name ?? '';
    _desc.text = a?.description ?? '';
    _productId = a?.productId;
    _status = a?.status ?? 'available';
  }

  @override
  void dispose() {
    for (final c in [_name, _desc, _credentials]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_productId == null) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Choose a product.')));
      return;
    }
    final body = <String, dynamic>{
      'product_id': _productId,
      'name': _name.text.trim(),
      'description': _desc.text.trim(),
      'status': _status,
      if (_credentials.text.trim().isNotEmpty)
        'credentials': _credentials.text.trim(),
    };
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminAccountsRepositoryProvider);
      if (isNew) {
        await repo.create(body);
      } else {
        await repo.update(a!.id, body);
        ref.invalidate(vaultAccountProvider(a!.id));
      }
      ref.invalidate(vaultAccountsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(isNew ? 'Account created.' : 'Saved.')));
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
    final products = ref.watch(vaultProductsProvider);
    return AdminFormScaffold(
      title: isNew ? 'New account' : a!.name,
      saveLabel: isNew ? 'Create account' : 'Save changes',
      saving: _saving,
      onSave: _save,
      children: [
        LabeledInput(
          label: 'Product',
          child: products.when(
            data: (list) => DropdownButtonFormField<int>(
              initialValue: _productId,
              items: [
                for (final p in list)
                  DropdownMenuItem(value: p.id, child: Text(p.name)),
              ],
              onChanged: (v) => setState(() => _productId = v),
            ),
            loading: () => const LinearProgressIndicator(),
            error: (_, _) => const Text('—'),
          ),
        ),
        LabeledInput(label: 'Name / label', child: TextField(controller: _name)),
        LabeledInput(
            label: 'Notes',
            child: TextField(controller: _desc, maxLines: 2)),
        LabeledInput(
          label: isNew ? 'Credentials' : 'Replace credentials',
          hint: isNew
              ? 'Stored encrypted. e.g. email + password, or a JSON blob.'
              : 'Leave blank to keep the current credentials.',
          child: TextField(controller: _credentials, maxLines: 3),
        ),
        LabeledInput(
          label: 'Status',
          child: StatusDropdown(
            value: _status,
            options: isNew
                ? const ['available', 'suspended', 'maintenance', 'archived']
                : const [
                    'available',
                    'assigned',
                    'suspended',
                    'maintenance',
                    'expired',
                    'archived',
                  ],
            onChanged: (v) => setState(() => _status = v),
          ),
        ),
      ],
    );
  }
}
