import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

const _orderStatuses = <String?, String>{
  null: 'All',
  'pending': 'Pending',
  'paid': 'Paid',
  'active': 'Active',
  'expired': 'Expired',
  'renewed': 'Renewed',
  'confirmed': 'Confirmed',
  'rejected': 'Rejected',
  'cancelled': 'Cancelled',
};

const _orderKinds = <String?, String>{
  null: 'All types',
  'subscription': 'Subscription',
  'software': 'Software',
  'vpn': 'Mbunie VPN',
  'tool': 'Tool',
};

const _orderTypes = <String?, String>{
  null: 'New and renewals',
  'new': 'New orders',
  'renewal': 'Renewals',
};

MbuiAppearance _kindAppearance(String? kind) => switch (kind) {
      'software' => MbuiAppearance.info,
      'vpn' => MbuiAppearance.success,
      'tool' => MbuiAppearance.warning,
      _ => MbuiAppearance.neutral,
    };

/// Status as the web badge shows it (active / expired / renewed when known).
Widget _orderStatusBadge(AdminOrder o) {
  final status = o.displayStatus ?? o.status;
  final label = o.displayStatusLabel;
  if (label == null) return MbuiStatusBadge(status);
  final appearance = switch (status) {
    'active' || 'confirmed' || 'paid' => MbuiAppearance.success,
    'pending' => MbuiAppearance.warning,
    'renewed' => MbuiAppearance.info,
    'expired' || 'rejected' || 'cancelled' => MbuiAppearance.danger,
    _ => MbuiAppearance.neutral,
  };
  return MbuiBadge(label, appearance: appearance);
}

class AdminOrdersScreen extends ConsumerStatefulWidget {
  const AdminOrdersScreen({super.key});

  @override
  ConsumerState<AdminOrdersScreen> createState() => _AdminOrdersScreenState();
}

class _AdminOrdersScreenState extends ConsumerState<AdminOrdersScreen> {
  final _search = TextEditingController();

  /// Pages 2, 3... loaded with "Load more"; page 1 comes from the provider.
  final List<AdminOrder> _more = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _search.text = ref.read(adminOrderSearchProvider);
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final next = await ref.read(adminRepositoryProvider).orders(
            status: ref.read(adminOrderFilterProvider),
            kind: ref.read(adminOrderKindProvider),
            type: ref.read(adminOrderTypeProvider),
            search: ref.read(adminOrderSearchProvider),
            page: _page + 1,
          );
      if (!mounted) return;
      setState(() {
        _more.addAll(next.items);
        _page = next.currentPage;
        _lastPage = next.lastPage;
      });
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _pick(
    String title,
    Map<String?, String> options,
    StateProvider<String?> provider,
  ) async {
    final current = ref.read(provider);
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: MbuiSectionLabel(title),
              ),
            ),
            for (final e in options.entries)
              ListTile(
                title: Text(e.value),
                trailing: current == e.key
                    ? const Icon(Icons.check, color: AppColors.indigo600)
                    : null,
                onTap: () => Navigator.of(context).pop(e.key ?? ''),
              ),
          ],
        ),
      ),
    );
    if (picked == null) return;
    ref.read(provider.notifier).state = picked.isEmpty ? null : picked;
  }

  void _applySearch(String value) {
    ref.read(adminOrderSearchProvider.notifier).state = value.trim();
  }

  void _clearAll() {
    _search.clear();
    ref.read(adminOrderFilterProvider.notifier).state = null;
    ref.read(adminOrderKindProvider.notifier).state = null;
    ref.read(adminOrderTypeProvider.notifier).state = null;
    ref.read(adminOrderSearchProvider.notifier).state = '';
  }

  @override
  Widget build(BuildContext context) {
    // A new first page (filter change, refresh, edit) starts the list over.
    ref.listen<AsyncValue<AdminList<AdminOrder>>>(adminOrdersProvider,
        (_, next) {
      final list = next.valueOrNull;
      if (list == null) return;
      setState(() {
        _more.clear();
        _page = list.currentPage;
        _lastPage = list.lastPage;
      });
    });

    final async = ref.watch(adminOrdersProvider);
    final status = ref.watch(adminOrderFilterProvider);
    final kind = ref.watch(adminOrderKindProvider);
    final type = ref.watch(adminOrderTypeProvider);
    final search = ref.watch(adminOrderSearchProvider);
    final filtered =
        status != null || kind != null || type != null || search.isNotEmpty;
    final total = async.valueOrNull?.total;

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Orders')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: _applySearch,
              decoration: InputDecoration(
                isDense: true,
                hintText: 'Order number, customer name or email',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: search.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close, size: 18),
                        tooltip: 'Clear search',
                        onPressed: () {
                          _search.clear();
                          _applySearch('');
                        },
                      ),
              ),
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    avatar: const Icon(Icons.category_outlined, size: 16),
                    label: Text(_orderKinds[kind] ?? 'All types'),
                    selected: kind != null,
                    onSelected: (_) =>
                        _pick('Type', _orderKinds, adminOrderKindProvider),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    avatar: const Icon(Icons.autorenew, size: 16),
                    label: Text(_orderTypes[type] ?? 'New and renewals'),
                    selected: type != null,
                    onSelected: (_) =>
                        _pick('Orders', _orderTypes, adminOrderTypeProvider),
                  ),
                ),
                if (filtered)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ActionChip(
                      label: const Text('Clear'),
                      onPressed: _clearAll,
                    ),
                  ),
              ],
            ),
          ),
          AdminFilterBar(
            options: _orderStatuses,
            selected: status,
            onSelected: (v) =>
                ref.read(adminOrderFilterProvider.notifier).state = v,
          ),
          if (total != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  '$total ${total == 1 ? 'order' : 'orders'}',
                  style:
                      const TextStyle(fontSize: 12, color: AppColors.gray500),
                ),
              ),
            ),
          Expanded(
            child: AsyncValueView<AdminList<AdminOrder>>(
              value: async,
              onRefresh: () async => ref.refresh(adminOrdersProvider.future),
              data: (page) {
                final list = [...page.items, ..._more];
                if (list.isEmpty) {
                  return ListView(
                    children: [
                      const SizedBox(height: 120),
                      Center(
                        child: Text(
                          filtered
                              ? 'No orders match these filters.'
                              : 'No orders here.',
                          style: const TextStyle(color: AppColors.gray500),
                        ),
                      ),
                    ],
                  );
                }
                final hasMore = _page < _lastPage;
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: list.length + (hasMore ? 1 : 0),
                  itemBuilder: (context, i) {
                    if (i == list.length) {
                      return Padding(
                        padding: const EdgeInsets.only(top: 4, bottom: 16),
                        child: MbuiButton(
                          label: 'Load more',
                          variant: MbuiVariant.secondary,
                          fullWidth: true,
                          loading: _loadingMore,
                          onPressed: _loadMore,
                        ),
                      );
                    }
                    return _OrderTile(order: list[i]);
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

class _OrderTile extends StatelessWidget {
  const _OrderTile({required this.order});
  final AdminOrder order;

  @override
  Widget build(BuildContext context) {
    final o = order;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => AdminOrderDetailScreen(orderId: o.id),
          ),
        ),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    o.title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: AppColors.gray900,
                    ),
                  ),
                ),
                _orderStatusBadge(o),
              ],
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 4,
              children: [
                if (o.typeLabel != null)
                  MbuiBadge(o.typeLabel!, appearance: _kindAppearance(o.kind)),
                if (o.isRenewal)
                  const MbuiBadge('Renewal', appearance: MbuiAppearance.info),
                if (o.plan != null) MbuiBadge(o.plan!),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '${o.customerName} · ${o.orderNumber}',
              style: const TextStyle(fontSize: 12, color: AppColors.gray500),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Text(
                  o.amountLabel,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.gray700,
                  ),
                ),
                const Spacer(),
                if (o.createdAgo != null)
                  Text(
                    o.createdAgo!,
                    style:
                        const TextStyle(fontSize: 12, color: AppColors.gray400),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class AdminOrderDetailScreen extends ConsumerWidget {
  const AdminOrderDetailScreen({super.key, required this.orderId});
  final int orderId;

  Future<void> _edit(BuildContext context, WidgetRef ref, AdminOrder o) async {
    final newValue = await promptAdminText(
      context,
      title: 'Edit payment instructions',
      actionLabel: 'Save',
      initialValue: o.paymentInstructions ?? '',
      hint: 'Shown to the customer',
    );
    if (newValue == null) return;

    try {
      await ref.read(adminRepositoryProvider).updateOrder(o.id, newValue);
      ref.invalidate(adminOrderProvider(o.id));
      ref.invalidate(adminOrdersProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Order updated.')));
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _downloadReceipt(
    BuildContext context,
    WidgetRef ref,
    int id,
  ) async {
    try {
      final url = await ref.read(adminRepositoryProvider).orderReceiptUrl(id);
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminOrderProvider(orderId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Order'),
        actions: [
          async.maybeWhen(
            data: (o) => IconButton(
              icon: const Icon(Icons.edit_outlined),
              tooltip: 'Edit payment instructions',
              onPressed: () => _edit(context, ref, o),
            ),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: AsyncValueView<AdminOrder>(
        value: async,
        onRefresh: () async => ref.refresh(adminOrderProvider(orderId).future),
        data: (o) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(child: MbuiTitle(o.title)),
                _orderStatusBadge(o),
              ],
            ),
            if (o.isRenewal || o.wasRenewed) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.sky50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  o.isRenewal
                      ? 'Renewal of order ${o.renewsOrderNumber ?? ''}. Approving its '
                          'payment extends the customer\'s existing '
                          '${o.kind == 'vpn' ? 'VPN' : 'subscription'}.'
                      : 'This order was renewed by a newer paid order.',
                  style: const TextStyle(fontSize: 13, color: AppColors.sky700),
                ),
              ),
            ],
            const SizedBox(height: 16),
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Order number', o.orderNumber),
                  if (o.typeLabel != null) AdminRow('Type', o.typeLabel!),
                  AdminRow('Customer', o.customerName),
                  if (o.customerEmail != null)
                    AdminRow('Email', o.customerEmail!),
                  if (o.plan != null) AdminRow('Plan', o.plan!),
                  AdminRow('Amount', o.amountLabel),
                  if (o.createdAgo != null) AdminRow('Placed', o.createdAgo!),
                ],
              ),
            ),
            if (o.rejectionReason != null) ...[
              const SizedBox(height: 12),
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const MbuiSectionLabel('Rejection reason'),
                    const SizedBox(height: 6),
                    Text(
                      o.rejectionReason!,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.red700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (o.payments.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Payments'),
              const SizedBox(height: 8),
              for (final p in o.payments)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${p.amountLabel} · ${p.method}',
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              if (p.reference != null)
                                Text(
                                  'Ref: ${p.reference}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColors.gray500,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        MbuiStatusBadge(p.status),
                      ],
                    ),
                  ),
                ),
            ],
            if (o.isConfirmed) ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: 'Download receipt',
                variant: MbuiVariant.secondary,
                icon: Icons.receipt_long_outlined,
                fullWidth: true,
                onPressed: () => _downloadReceipt(context, ref, o.id),
              ),
            ],
            if (!o.isConfirmed &&
                o.status != 'rejected' &&
                o.status != 'cancelled') ...[
              const SizedBox(height: 24),
              _RejectOrderButton(orderId: o.id),
              const SizedBox(height: 6),
              const Text(
                'Approving happens by approving the customer\'s payment on the '
                'Payments screen. Use this only to disapprove the order.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
            ],
            const SizedBox(height: 12),
            _DeleteOrderButton(orderId: o.id),
          ],
        ),
      ),
    );
  }
}

class _RejectOrderButton extends ConsumerStatefulWidget {
  const _RejectOrderButton({required this.orderId});
  final int orderId;

  @override
  ConsumerState<_RejectOrderButton> createState() => _RejectOrderButtonState();
}

class _RejectOrderButtonState extends ConsumerState<_RejectOrderButton> {
  bool _busy = false;

  Future<void> _reject() async {
    final reason = await promptReason(
      context,
      title: 'Disapprove order',
      actionLabel: 'Disapprove',
      hint: 'Explain why (the customer sees this)',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      await ref
          .read(adminRepositoryProvider)
          .rejectOrder(widget.orderId, reason);
      ref.invalidate(adminOrderProvider(widget.orderId));
      ref.invalidate(adminOrdersProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Order disapproved.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MbuiButton(
      label: 'Disapprove order',
      variant: MbuiVariant.danger,
      icon: Icons.cancel_outlined,
      fullWidth: true,
      loading: _busy,
      onPressed: _reject,
    );
  }
}

class _DeleteOrderButton extends ConsumerStatefulWidget {
  const _DeleteOrderButton({required this.orderId});
  final int orderId;

  @override
  ConsumerState<_DeleteOrderButton> createState() => _DeleteOrderButtonState();
}

class _DeleteOrderButtonState extends ConsumerState<_DeleteOrderButton> {
  bool _busy = false;

  Future<void> _delete() async {
    final reason = await promptReason(
      context,
      title: 'Delete order',
      actionLabel: 'Delete',
      hint: 'Why are you deleting this order? This cannot be undone.',
    );
    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      await ref
          .read(adminRepositoryProvider)
          .deleteOrder(widget.orderId, reason);
      ref.invalidate(adminOrdersProvider);
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Order deleted.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MbuiButton(
      label: 'Delete order',
      variant: MbuiVariant.danger,
      icon: Icons.delete_outline,
      fullWidth: true,
      loading: _busy,
      onPressed: _delete,
    );
  }
}
