import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../data/admin_vpn_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

// ============================================================== helpers

String _vpnMoney(dynamic cents, dynamic currency) {
  final cur = '${currency ?? ''}'.toLowerCase();
  final amount = ((cents as num?) ?? 0) / 100;
  final f = NumberFormat(cur == 'tzs' ? '#,##0' : '#,##0.00');
  return '${cur.toUpperCase()} ${f.format(amount)}';
}

DateTime? _date(dynamic iso) =>
    iso == null ? null : DateTime.tryParse('$iso')?.toLocal();

String _vpnDate(dynamic iso, {bool time = true}) {
  final d = _date(iso);
  if (d == null) return '—';
  return DateFormat(time ? 'd MMM y HH:mm' : 'd MMM y').format(d);
}

/// "3 hours ago" / "in 5 days".
String _vpnAgo(dynamic iso, {String never = 'never'}) {
  final d = _date(iso);
  if (d == null) return never;
  final diff = DateTime.now().difference(d);
  final future = diff.isNegative;
  final a = diff.abs();
  String unit(int n, String w) => '$n $w${n == 1 ? '' : 's'}';
  final text = a.inDays >= 365
      ? unit(a.inDays ~/ 365, 'year')
      : a.inDays >= 30
          ? unit(a.inDays ~/ 30, 'month')
          : a.inDays >= 1
              ? unit(a.inDays, 'day')
              : a.inHours >= 1
                  ? unit(a.inHours, 'hour')
                  : a.inMinutes >= 1
                      ? unit(a.inMinutes, 'minute')
                      : 'moments';
  if (text == 'moments') return future ? 'in a moment' : 'just now';
  return future ? 'in $text' : '$text ago';
}

String _cap(dynamic s) {
  final t = '${s ?? ''}';
  return t.isEmpty ? t : '${t[0].toUpperCase()}${t.substring(1)}';
}

Future<void> _runVpnAction(
  BuildContext context,
  Future<String> Function() action, {
  VoidCallback? onDone,
}) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    final msg = await action();
    messenger.showSnackBar(SnackBar(content: Text(msg)));
    onDone?.call();
  } on ApiException catch (e) {
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  }
}

Future<int?> _promptDays(BuildContext context) async {
  final controller = TextEditingController(text: '30');
  final result = await showModalBottomSheet<int>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          bottom: MediaQuery.viewInsetsOf(ctx).bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Add days',
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: AppColors.gray900)),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              autofocus: true,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(hintText: 'Days (1 - 3650)'),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () {
                final n = int.tryParse(controller.text.trim());
                if (n == null || n < 1 || n > 3650) return;
                Navigator.pop(ctx, n);
              },
              child: const Text('Add days'),
            ),
          ],
        ),
      ),
    ),
  );
  controller.dispose();
  return result;
}

class _Note extends StatelessWidget {
  const _Note(this.text, {this.color = AppColors.gray500});
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) =>
      Text(text, style: TextStyle(fontSize: 12, color: color));
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox(this.message);
  final String message;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.amber50,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(message,
            style: const TextStyle(fontSize: 13, color: AppColors.amber700)),
      );
}

// ============================================================== main screen

class AdminVpnScreen extends StatelessWidget {
  const AdminVpnScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 5,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Mbunie VPN'),
          bottom: const TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              Tab(text: 'Overview'),
              Tab(text: 'Customers'),
              Tab(text: 'Payments'),
              Tab(text: 'Devices'),
              Tab(text: 'Plans'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _OverviewTab(),
            _CustomersTab(),
            _PaymentsTab(),
            _DevicesTab(),
            _PlansTab(),
          ],
        ),
      ),
    );
  }
}

// ============================================================== overview

class _OverviewTab extends ConsumerWidget {
  const _OverviewTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminVpnOverviewProvider);
    return AsyncValueView<Map<String, dynamic>>(
      value: async,
      onRefresh: () async => ref.refresh(adminVpnOverviewProvider.future),
      data: (d) {
        final stats = d['stats'] as Map<String, dynamic>?;
        final error = d['error'] as String?;
        final rates = d['rates'] as Map<String, dynamic>? ?? const {};
        final orders = (d['orders'] as List? ?? const []).cast<Map>();
        final canManage = d['can_manage'] == true;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (error != null) ...[_ErrorBox(error), const SizedBox(height: 12)],
            if (stats != null) ..._statsSection(stats, rates),
            const SizedBox(height: 16),
            const MbuiSectionLabel('VPN orders paid on EduHub'),
            const SizedBox(height: 8),
            if (orders.isEmpty)
              const _Note('No VPN orders yet.')
            else
              for (final o in orders)
                _EduhubOrderCard(
                  order: Map<String, dynamic>.from(o),
                  canManage: canManage,
                ),
          ],
        );
      },
    );
  }

  List<Widget> _statsSection(
      Map<String, dynamic> stats, Map<String, dynamic> rates) {
    final online = stats['online_now'] as Map<String, dynamic>? ?? const {};
    final subs = stats['subscriptions'] as Map<String, dynamic>? ?? const {};
    final payments = stats['payments'] as Map<String, dynamic>? ?? const {};
    final revenue =
        payments['revenue_this_month'] as Map<String, dynamic>? ?? const {};

    // Every currency taken this month, plus one total in TSh at today's rate.
    final perTzs = {
      'tzs': 1.0,
      'usd': (rates['USD'] as num?)?.toDouble() ?? 0,
      'cny': (rates['CNY'] as num?)?.toDouble() ?? 0,
    };
    var totalTzs = 0.0;
    var paidCount = 0;
    final parts = <String>[];
    revenue.forEach((cur, r) {
      final m = r as Map<String, dynamic>;
      final amount = ((m['cents'] as num?) ?? 0) / 100;
      paidCount += (m['count'] as num?)?.toInt() ?? 0;
      final rate = perTzs[cur] ?? 0;
      if (rate > 0) totalTzs += amount / rate;
      parts.add(_vpnMoney(m['cents'], cur));
    });
    final onlyTzs = revenue.isEmpty || revenue.keys.every((k) => k == 'tzs');

    final onlineList = (online['list'] as List? ?? const []).cast<Map>();
    final recent = (stats['recent_payments'] as List? ?? const []).cast<Map>();
    final nodes = (stats['nodes'] as List? ?? const []).cast<Map>();

    return [
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 1.45,
        children: [
          _StatCard('Using the VPN now', '${online['devices'] ?? 0}',
              '${online['customers'] ?? 0} customers'),
          _StatCard('Active subscriptions', '${subs['active'] ?? 0}',
              '${subs['expiring_3d'] ?? 0} end within 3 days'),
          _StatCard('All customers', '${stats['customers'] ?? 0}',
              '${subs['expired'] ?? 0} expired · ${subs['suspended'] ?? 0} suspended'),
          _StatCard(
              'Revenue this month',
              '${onlyTzs ? '' : '≈ '}TSh ${NumberFormat('#,##0').format(totalTzs.round())}',
              '$paidCount paid · ${payments['pending'] ?? 0} waiting'),
        ],
      ),
      if (parts.isNotEmpty) ...[
        const SizedBox(height: 6),
        _Note(parts.join(' + ')),
      ],
      const SizedBox(height: 16),
      const MbuiSectionLabel('Online right now'),
      const SizedBox(height: 8),
      if (onlineList.isEmpty)
        const _Note('Nobody is connected right now.')
      else
        MbuiCard(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          child: Column(
            children: [
              for (final d in onlineList)
                AdminRow(
                  '${d['customer'] ?? '—'}',
                  '${_cap(d['platform'] ?? '—')} · ${d['protocol'] ?? '—'} · ${_vpnAgo(d['connected_since'], never: '—')}',
                ),
            ],
          ),
        ),
      const SizedBox(height: 16),
      const MbuiSectionLabel('Recent VPN payments'),
      const SizedBox(height: 8),
      if (recent.isEmpty)
        const _Note('No payments yet.')
      else
        for (final p in recent)
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
                        Text('${p['customer'] ?? '—'}',
                            style: const TextStyle(
                                fontSize: 13, fontWeight: FontWeight.w600)),
                        _Note(
                            '${p['plan'] ?? ''} · ${_vpnMoney(p['amount_cents'], p['currency'])} · ${p['provider'] ?? ''}'),
                      ],
                    ),
                  ),
                  MbuiBadge(
                    '${p['status']}',
                    appearance: switch (p['status']) {
                      'paid' => MbuiAppearance.success,
                      'failed' || 'expired' => MbuiAppearance.danger,
                      _ => MbuiAppearance.warning,
                    },
                  ),
                ],
              ),
            ),
          ),
      if (nodes.isNotEmpty) ...[
        const SizedBox(height: 16),
        const MbuiSectionLabel('Nodes'),
        const SizedBox(height: 8),
        MbuiCard(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Column(
            children: [
              for (final n in nodes)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Expanded(child: Text('${n['name']}')),
                      MbuiBadge(
                        '${n['status']}',
                        appearance: n['status'] == 'online'
                            ? MbuiAppearance.success
                            : MbuiAppearance.danger,
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ],
    ];
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard(this.title, this.value, this.trend);
  final String title;
  final String value;
  final String trend;

  @override
  Widget build(BuildContext context) => MbuiCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(title,
                maxLines: 2,
                style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(value,
                  style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: AppColors.gray900)),
            ),
            Text(trend,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
          ],
        ),
      );
}

class _EduhubOrderCard extends ConsumerWidget {
  const _EduhubOrderCard({required this.order, required this.canManage});
  final Map<String, dynamic> order;
  final bool canManage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final o = order;
    final activated = o['activated'] == true;
    final confirmed = o['status'] == 'confirmed';
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('#${o['order_number']}',
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w700)),
                ),
                if (activated)
                  const MbuiBadge('Active', appearance: MbuiAppearance.success)
                else if (confirmed)
                  const MbuiBadge('Not activated',
                      appearance: MbuiAppearance.danger)
                else
                  const MbuiBadge('Waiting for payment'),
              ],
            ),
            const SizedBox(height: 4),
            _Note('${o['customer_email'] ?? '—'}'),
            _Note(
                '${o['plan'] ?? '—'} (${o['plan_code'] ?? 'no VPN plan code!'})'),
            if (activated && o['expires_at'] != null)
              _Note('Until ${_vpnDate(o['expires_at'])}'),
            if (!activated && confirmed && o['activation_error'] != null)
              _Note(
                  '${o['activation_error']} (${o['activation_attempts']} attempts)',
                  color: AppColors.red700),
            if (canManage && o['can_retry'] == true) ...[
              const SizedBox(height: 8),
              MbuiButton(
                label: 'Retry activation',
                variant: MbuiVariant.secondary,
                icon: Icons.refresh,
                onPressed: () => _runVpnAction(
                  context,
                  () => ref
                      .read(adminVpnRepositoryProvider)
                      .retryOrder((o['id'] as num).toInt()),
                  onDone: () => ref.invalidate(adminVpnOverviewProvider),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ============================================================== paged list

typedef _VpnLoader = Future<VpnPage> Function(
    WidgetRef ref, String filter, String q, int page);

/// Search + filter chips + "Load more" over a partner-API list.
class _VpnList extends ConsumerStatefulWidget {
  const _VpnList({
    required this.load,
    required this.itemBuilder,
    required this.searchHint,
    this.filters = const {},
    this.initialFilter = 'all',
    this.emptyText = 'Nothing here.',
  });

  final _VpnLoader load;
  final Widget Function(BuildContext context, VpnRow row, VoidCallback reload)
      itemBuilder;
  final String searchHint;
  final Map<String, String> filters;
  final String initialFilter;
  final String emptyText;

  @override
  ConsumerState<_VpnList> createState() => _VpnListState();
}

class _VpnListState extends ConsumerState<_VpnList>
    with AutomaticKeepAliveClientMixin {
  final _search = TextEditingController();
  late String _filter = widget.initialFilter;
  String _q = '';
  final List<VpnRow> _items = [];
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final p = await widget.load(ref, _filter, _q, 1);
      if (!mounted) return;
      setState(() {
        _items
          ..clear()
          ..addAll(p.items);
        _page = p.page;
        _lastPage = p.lastPage;
        _total = p.total;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _more() async {
    setState(() => _loadingMore = true);
    try {
      final p = await widget.load(ref, _filter, _q, _page + 1);
      if (!mounted) return;
      setState(() {
        _items.addAll(p.items);
        _page = p.page;
        _lastPage = p.lastPage;
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

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: TextField(
            controller: _search,
            textInputAction: TextInputAction.search,
            onSubmitted: (v) {
              _q = v.trim();
              _reload();
            },
            decoration: InputDecoration(
              isDense: true,
              hintText: widget.searchHint,
              prefixIcon: const Icon(Icons.search, size: 20),
              suffixIcon: _q.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.close, size: 18),
                      tooltip: 'Clear search',
                      onPressed: () {
                        _search.clear();
                        _q = '';
                        _reload();
                      },
                    ),
            ),
          ),
        ),
        if (widget.filters.isNotEmpty)
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final e in widget.filters.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(e.value),
                      selected: _filter == e.key,
                      onSelected: (_) {
                        _filter = e.key;
                        _reload();
                      },
                    ),
                  ),
              ],
            ),
          ),
        if (!_loading && _error == null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 2, 16, 0),
            child: Align(
              alignment: Alignment.centerLeft,
              child: _Note('$_total total'),
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _reload,
            child: _loading
                ? ListView(children: const [
                    SizedBox(height: 120),
                    Center(child: CircularProgressIndicator()),
                  ])
                : _error != null
                    ? ListView(
                        padding: const EdgeInsets.all(16),
                        children: [_ErrorBox(_error!)],
                      )
                    : _items.isEmpty
                        ? ListView(children: [
                            const SizedBox(height: 120),
                            Center(child: _Note(widget.emptyText)),
                          ])
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount:
                                _items.length + (_page < _lastPage ? 1 : 0),
                            itemBuilder: (context, i) {
                              if (i == _items.length) {
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 16),
                                  child: MbuiButton(
                                    label: 'Load more',
                                    variant: MbuiVariant.secondary,
                                    fullWidth: true,
                                    loading: _loadingMore,
                                    onPressed: _more,
                                  ),
                                );
                              }
                              return widget.itemBuilder(
                                  context, _items[i], _reload);
                            },
                          ),
          ),
        ),
      ],
    );
  }
}

// ============================================================== customers

class _CustomersTab extends StatelessWidget {
  const _CustomersTab();

  @override
  Widget build(BuildContext context) {
    return _VpnList(
      searchHint: 'Search email, phone or name',
      emptyText: 'No customers found.',
      load: (ref, _, q, page) =>
          ref.read(adminVpnRepositoryProvider).customers(q: q, page: page),
      itemBuilder: (context, c, reload) {
        final s = c['subscription'] as Map<String, dynamic>?;
        final status = '${c['status'] ?? 'active'}';
        return Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: MbuiCard(
            padding: const EdgeInsets.all(14),
            onTap: () async {
              await Navigator.of(context).push(MaterialPageRoute(
                builder: (_) =>
                    VpnCustomerScreen(customerId: (c['id'] as num).toInt()),
              ));
              reload();
            },
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${c['email'] ?? c['phone'] ?? 'Customer #${c['id']}'}',
                        style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: AppColors.gray900),
                      ),
                    ),
                    if (s != null)
                      MbuiStatusBadge('${s['status']}')
                    else
                      const MbuiBadge('No plan'),
                  ],
                ),
                const SizedBox(height: 4),
                _Note([
                  '${c['name'] ?? ''}'.isEmpty ? '—' : '${c['name']}',
                  if (c['eduhub_user_id'] != null) 'EduHub #${c['eduhub_user_id']}',
                  if (status != 'active') _cap(status),
                ].join(' · ')),
                if (s != null)
                  _Note(
                      '${'${s['plan']}'.toUpperCase()}${s['expires_at'] != null ? ' · until ${_vpnDate(s['expires_at'], time: false)}' : ''} · ${s['online'] == true ? '● ' : ''}${s['devices']} / ${s['max_devices']} devices'),
              ],
            ),
          ),
        );
      },
    );
  }
}

class VpnCustomerScreen extends ConsumerWidget {
  const VpnCustomerScreen({super.key, required this.customerId});
  final int customerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminVpnCustomerProvider(customerId));
    final repo = ref.read(adminVpnRepositoryProvider);
    void refresh() => ref.invalidate(adminVpnCustomerProvider(customerId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('VPN customer')),
      body: AsyncValueView<Map<String, dynamic>>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminVpnCustomerProvider(customerId).future),
        data: (d) {
          final cust = d['customer'] as Map<String, dynamic>? ?? const {};
          final subs = (d['subscriptions'] as List? ?? const []).cast<Map>();
          final devices = (d['devices'] as List? ?? const []).cast<Map>();
          final invoices = (d['invoices'] as List? ?? const []).cast<Map>();
          final plans = (d['plans'] as List? ?? const []).cast<Map>();
          final active = '${cust['status'] ?? 'active'}' == 'active';

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiTitle('${cust['email'] ?? cust['phone'] ?? 'Customer #${cust['id']}'}'),
              const SizedBox(height: 12),
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    AdminRow('Name', '${cust['name'] ?? ''}'.isEmpty ? '—' : '${cust['name']}'),
                    AdminRow('Email', '${cust['email'] ?? '—'}'),
                    AdminRow('Phone', '${cust['phone'] ?? '—'}'),
                    AdminRow(
                        'EduHub account',
                        cust['eduhub_user_id'] != null
                            ? '#${cust['eduhub_user_id']}'
                            : 'not linked'),
                    AdminRow('Status', '',
                        valueWidget: Align(
                          alignment: Alignment.centerLeft,
                          child: MbuiStatusBadge('${cust['status'] ?? 'active'}'),
                        )),
                    AdminRow('Joined', _vpnDate(cust['created_at'], time: false)),
                    const SizedBox(height: 10),
                    if (active)
                      MbuiButton(
                        label: 'Suspend customer',
                        variant: MbuiVariant.danger,
                        icon: Icons.block,
                        fullWidth: true,
                        onPressed: () async {
                          final reason = await promptReason(
                            context,
                            title: 'Suspend customer',
                            actionLabel: 'Suspend',
                            required: false,
                            hint:
                                'Reason (optional). Turns off all their VPN plans and signs them out.',
                          );
                          if (reason == null || !context.mounted) return;
                          await _runVpnAction(
                            context,
                            () => repo.setCustomerStatus(customerId, 'suspended',
                                reason: reason),
                            onDone: refresh,
                          );
                        },
                      )
                    else
                      MbuiButton(
                        label: 'Restore customer',
                        icon: Icons.check_circle_outline,
                        fullWidth: true,
                        onPressed: () => _runVpnAction(
                          context,
                          () => repo.setCustomerStatus(customerId, 'active'),
                          onDone: refresh,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              MbuiCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const MbuiSectionLabel('Give a plan'),
                    const SizedBox(height: 6),
                    const _Note(
                        'For a gift, compensation or a payment received outside the system. Recorded as a zero-amount payment.'),
                    const SizedBox(height: 10),
                    MbuiButton(
                      label: 'Give plan',
                      variant: MbuiVariant.secondary,
                      icon: Icons.card_giftcard,
                      onPressed: () async {
                        final code = await showModalBottomSheet<String>(
                          context: context,
                          showDragHandle: true,
                          builder: (ctx) => SafeArea(
                            child: ListView(
                              shrinkWrap: true,
                              children: [
                                for (final p in plans)
                                  ListTile(
                                    enabled: p['is_active'] == true,
                                    title: Text('${p['name']}'),
                                    subtitle: Text(
                                        '${p['days']} days${p['is_active'] == true ? '' : ' · inactive'}'),
                                    onTap: () =>
                                        Navigator.pop(ctx, '${p['code']}'),
                                  ),
                              ],
                            ),
                          ),
                        );
                        if (code == null || !context.mounted) return;
                        final note = await promptReason(
                          context,
                          title: 'Give $code to ${cust['email'] ?? 'this customer'}?',
                          actionLabel: 'Give plan',
                          required: false,
                          variant: MbuiVariant.primary,
                          hint: 'Note (optional)',
                        );
                        if (note == null || !context.mounted) return;
                        await _runVpnAction(
                          context,
                          () => repo.grantPlan(customerId, code, note: note),
                          onDone: refresh,
                        );
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Subscriptions'),
              const SizedBox(height: 8),
              if (subs.isEmpty) const _Note('No subscriptions yet.'),
              for (final s in subs)
                _SubscriptionCard(
                  sub: Map<String, dynamic>.from(s),
                  onChanged: refresh,
                ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Devices'),
              const SizedBox(height: 8),
              if (devices.isEmpty) const _Note('No devices.'),
              for (final dv in devices)
                _DeviceCard(
                  device: Map<String, dynamic>.from(dv),
                  showCustomer: false,
                  onChanged: refresh,
                ),
              const SizedBox(height: 16),
              const MbuiSectionLabel('Payments'),
              const SizedBox(height: 8),
              if (invoices.isEmpty) const _Note('No payments.'),
              for (final i in invoices)
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
                              Text(_vpnMoney(i['amount_cents'], i['currency']),
                                  style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w600)),
                              _Note(
                                  '${'${i['plan']}'.toUpperCase()} · ${i['method'] ?? i['provider'] ?? ''} · ${_vpnDate(i['paid_at'] ?? i['created_at'])}'),
                              if (i['review_note'] != null)
                                _Note('${i['review_note']}'),
                            ],
                          ),
                        ),
                        MbuiStatusBadge(i['status'] == 'pending_review'
                            ? 'pending'
                            : '${i['status']}'),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _SubscriptionCard extends ConsumerWidget {
  const _SubscriptionCard({required this.sub, required this.onChanged});
  final Map<String, dynamic> sub;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = sub;
    final id = (s['id'] as num).toInt();
    final repo = ref.read(adminVpnRepositoryProvider);
    final suspended = s['status'] == 'suspended';
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('${s['plan']}'.toUpperCase(),
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w700)),
                ),
                MbuiStatusBadge('${s['status']}'),
              ],
            ),
            const SizedBox(height: 4),
            _Note(
                'Until ${_vpnDate(s['expires_at'])} (${_vpnAgo(s['expires_at'], never: '—')})'),
            _Note(
                '${s['online'] == true ? '● online · ' : ''}${s['devices']} / ${s['max_devices']} devices'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  icon: const Icon(Icons.add, size: 18),
                  label: const Text('Add days'),
                  onPressed: () async {
                    final days = await _promptDays(context);
                    if (days == null || !context.mounted) return;
                    await _runVpnAction(context, () => repo.extend(id, days),
                        onDone: onChanged);
                  },
                ),
                if (suspended)
                  OutlinedButton.icon(
                    icon: const Icon(Icons.play_arrow, size: 18),
                    label: const Text('Restore'),
                    onPressed: () => _runVpnAction(
                        context, () => repo.restore(id),
                        onDone: onChanged),
                  )
                else
                  OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.red600),
                    icon: const Icon(Icons.pause, size: 18),
                    label: const Text('Suspend'),
                    onPressed: () async {
                      final reason = await promptReason(
                        context,
                        title: 'Suspend subscription',
                        actionLabel: 'Suspend',
                        required: false,
                        hint:
                            'Reason (optional). The VPN turns off now; Restore gives it back with the days left.',
                      );
                      if (reason == null || !context.mounted) return;
                      await _runVpnAction(
                          context, () => repo.suspend(id, reason: reason),
                          onDone: onChanged);
                    },
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================== devices

class _DeviceCard extends ConsumerWidget {
  const _DeviceCard({
    required this.device,
    required this.showCustomer,
    required this.onChanged,
  });
  final Map<String, dynamic> device;
  final bool showCustomer;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final d = device;
    final id = (d['id'] as num).toInt();
    final repo = ref.read(adminVpnRepositoryProvider);
    final state = '${d['state'] ?? ''}';
    final model = [d['model'], d['os_version']]
        .where((v) => v != null && '$v'.isNotEmpty)
        .join(' · ');
    final name = model.isEmpty ? _cap(d['platform'] ?? 'device') : model;
    final when = switch (state) {
      'removed' => 'removed ${_vpnAgo(d['revoked_at'])}',
      'online' => d['connected_at'] != null
          ? 'connected ${_vpnAgo(d['connected_at'])}'
          : 'online',
      _ => d['last_seen_at'] != null
          ? 'seen ${_vpnAgo(d['last_seen_at'])}'
          : 'never seen',
    };
    final customer = d['customer'] as Map<String, dynamic>?;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    showCustomer && customer != null
                        ? '${customer['email'] ?? customer['phone']}'
                        : name,
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                ),
                MbuiBadge(
                  _cap(state),
                  appearance: switch (state) {
                    'online' => MbuiAppearance.success,
                    'removed' => MbuiAppearance.danger,
                    _ => MbuiAppearance.neutral,
                  },
                ),
              ],
            ),
            const SizedBox(height: 4),
            if (showCustomer && customer != null) _Note(name),
            _Note(
                '$when · ${_cap(d['platform'] ?? '—')} ${d['app_version'] ?? ''}${d['protocol'] != null ? ' · protocol ${d['protocol']}' : ''}'),
            if (d['last_error'] != null)
              _Note('${d['last_error']} (${_vpnAgo(d['last_error_at'])})',
                  color: AppColors.red700),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (showCustomer && customer != null)
                  OutlinedButton(
                    onPressed: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => VpnCustomerScreen(
                            customerId: (customer['id'] as num).toInt()),
                      ));
                      onChanged();
                    },
                    child: const Text('Open customer'),
                  ),
                if (state == 'removed')
                  OutlinedButton(
                    onPressed: () => _runVpnAction(
                        context, () => repo.allowDevice(id),
                        onDone: onChanged),
                    child: const Text('Allow again'),
                  )
                else
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.red600),
                    onPressed: () async {
                      final reason = await promptReason(
                        context,
                        title: 'Remove device',
                        actionLabel: 'Remove device',
                        required: false,
                        hint:
                            'Reason (optional). It is blocked for good, the customer is signed out everywhere and their VPN keys change.',
                      );
                      if (reason == null || !context.mounted) return;
                      await _runVpnAction(
                          context, () => repo.removeDevice(id, reason: reason),
                          onDone: onChanged);
                    },
                    child: const Text('Remove'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DevicesTab extends StatelessWidget {
  const _DevicesTab();

  @override
  Widget build(BuildContext context) {
    return _VpnList(
      searchHint: 'Customer email, phone or device',
      emptyText: 'No devices.',
      filters: const {
        'all': 'All',
        'online': 'Online now',
        'removed': 'Removed',
        'errors': 'With errors',
      },
      load: (ref, filter, q, page) => ref
          .read(adminVpnRepositoryProvider)
          .devices(filter: filter, q: q, page: page),
      itemBuilder: (context, d, reload) =>
          _DeviceCard(device: d, showCustomer: true, onChanged: reload),
    );
  }
}

// ============================================================== payments

class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab();

  @override
  Widget build(BuildContext context) {
    return _VpnList(
      searchHint: 'Customer email or phone',
      emptyText: 'No payments.',
      initialFilter: 'review',
      filters: const {
        'review': 'To review',
        'pending': 'All pending',
        'paid': 'Paid',
        'failed': 'Rejected / failed',
      },
      load: (ref, filter, q, page) => ref
          .read(adminVpnRepositoryProvider)
          .payments(status: filter, q: q, page: page),
      itemBuilder: (context, i, reload) =>
          _InvoiceCard(invoice: i, onChanged: reload),
    );
  }
}

class _InvoiceCard extends ConsumerWidget {
  const _InvoiceCard({required this.invoice, required this.onChanged});
  final Map<String, dynamic> invoice;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final i = invoice;
    final id = (i['id'] as num).toInt();
    final repo = ref.read(adminVpnRepositoryProvider);
    final open = i['status'] == 'pending' || i['status'] == 'pending_review';
    final customer = i['customer'] as Map<String, dynamic>?;
    final amount = _vpnMoney(i['amount_cents'], i['currency']);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: MbuiCard(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    customer != null
                        ? '${customer['email'] ?? customer['phone']}'
                        : 'Payment #$id',
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                ),
                MbuiStatusBadge(i['status'] == 'pending_review'
                    ? 'pending'
                    : '${i['status']}'),
              ],
            ),
            const SizedBox(height: 4),
            Text(amount,
                style:
                    const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
            _Note(
                '${'${i['plan']}'.toUpperCase()} · ${_cap(i['method'] ?? i['provider'] ?? '')} · ${_vpnDate(i['paid_at'] ?? i['created_at'])}'),
            if (i['user_note'] != null) _Note('“${i['user_note']}”'),
            if (i['review_note'] != null) _Note('${i['review_note']}'),
            if (i['has_proof'] == true) ...[
              const SizedBox(height: 8),
              _VpnProof(invoiceId: id),
            ],
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (customer != null)
                  OutlinedButton(
                    onPressed: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => VpnCustomerScreen(
                            customerId: (customer['id'] as num).toInt()),
                      ));
                      onChanged();
                    },
                    child: const Text('Open customer'),
                  ),
                if (open) ...[
                  FilledButton(
                    style: FilledButton.styleFrom(
                        backgroundColor: AppColors.emerald600),
                    onPressed: () async {
                      final ok = await confirmAdminAction(
                        context,
                        title: 'Approve payment',
                        message: 'Approve $amount and switch the VPN on?',
                        actionLabel: 'Approve',
                      );
                      if (!ok || !context.mounted) return;
                      await _runVpnAction(context, () => repo.approvePayment(id),
                          onDone: onChanged);
                    },
                    child: const Text('Approve'),
                  ),
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.red600),
                    onPressed: () async {
                      final reason = await promptReason(
                        context,
                        title: 'Reject payment',
                        actionLabel: 'Reject',
                        hint:
                            'Reason (the customer sees this), e.g. Amount does not match',
                      );
                      if (reason == null || reason.isEmpty || !context.mounted) {
                        return;
                      }
                      await _runVpnAction(
                          context, () => repo.rejectPayment(id, reason),
                          onDone: onChanged);
                    },
                    child: const Text('Reject'),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _VpnProof extends ConsumerStatefulWidget {
  const _VpnProof({required this.invoiceId});
  final int invoiceId;

  @override
  ConsumerState<_VpnProof> createState() => _VpnProofState();
}

class _VpnProofState extends ConsumerState<_VpnProof> {
  Uint8List? _bytes;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data =
          await ref.read(adminVpnRepositoryProvider).proofBytes(widget.invoiceId);
      if (mounted) setState(() => _bytes = Uint8List.fromList(data));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return _Note('Could not load the screenshot: $_error',
          color: AppColors.red700);
    }
    if (_bytes == null) {
      return const SizedBox(
        height: 120,
        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
      );
    }
    return GestureDetector(
      onTap: () => showDialog(
        context: context,
        builder: (_) => Dialog(
          child: InteractiveViewer(
            child: Image.memory(_bytes!,
                errorBuilder: (_, _, _) =>
                    const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('This proof is not an image (PDF?).'),
                    )),
          ),
        ),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Image.memory(
          _bytes!,
          height: 180,
          width: double.infinity,
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => const _Note(
              'Proof attached (not an image). Open it on the web admin.'),
        ),
      ),
    );
  }
}

// ============================================================== plans

class _PlansTab extends ConsumerWidget {
  const _PlansTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminVpnPlansProvider);
    return AsyncValueView<Map<String, dynamic>>(
      value: async,
      onRefresh: () async => ref.refresh(adminVpnPlansProvider.future),
      data: (d) {
        final plans = (d['data'] as List? ?? const []).cast<Map>();
        final products = (d['eduhub_products'] as List? ?? const []).cast<dynamic>();
        final rates = d['rates'] as Map<String, dynamic>? ?? const {};
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Note(products.isNotEmpty
                      ? 'Sold on EduHub as ${products.join(', ')}: saving a plan here updates its name, days and TZS price there too. Plans off sale or without a TZS price are hidden on EduHub.'
                      : 'No EduHub product of type "vpn" yet, so these plans are only sold in the VPN app.'),
                  if (products.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    MbuiButton(
                      label: 'Update EduHub now',
                      variant: MbuiVariant.secondary,
                      icon: Icons.sync,
                      onPressed: () => _runVpnAction(
                        context,
                        () => ref.read(adminVpnRepositoryProvider).syncPlans(),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 12),
            for (final p in plans)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: MbuiCard(
                  padding: const EdgeInsets.all(14),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => _VpnPlanEditScreen(
                      plan: Map<String, dynamic>.from(p),
                      rates: rates,
                    ),
                  )),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text('${p['name']}',
                                style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700)),
                          ),
                          MbuiBadge(
                            p['is_active'] == true ? 'On sale' : 'Off sale',
                            appearance: p['is_active'] == true
                                ? MbuiAppearance.success
                                : MbuiAppearance.neutral,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      _Note(
                          '${'${p['code']}'.toUpperCase()} · ${p['days']} days · ${p['max_devices']} devices'),
                      _Note(
                          '\$${p['price_usd']} · ¥${p['price_cny']} · TSh ${p['price_tzs']}'),
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

class _VpnPlanEditScreen extends ConsumerStatefulWidget {
  const _VpnPlanEditScreen({required this.plan, required this.rates});
  final Map<String, dynamic> plan;
  final Map<String, dynamic> rates;

  @override
  ConsumerState<_VpnPlanEditScreen> createState() => _VpnPlanEditScreenState();
}

class _VpnPlanEditScreenState extends ConsumerState<_VpnPlanEditScreen> {
  late final _name = TextEditingController(text: '${widget.plan['name'] ?? ''}');
  late final _days = TextEditingController(text: '${widget.plan['days'] ?? ''}');
  late final _devices =
      TextEditingController(text: '${widget.plan['max_devices'] ?? ''}');
  late final Map<String, TextEditingController> _price = {
    'usd': TextEditingController(text: '${widget.plan['price_usd'] ?? 0}'),
    'cny': TextEditingController(text: '${widget.plan['price_cny'] ?? 0}'),
    'tzs': TextEditingController(text: '${widget.plan['price_tzs'] ?? 0}'),
  };
  late bool _active = widget.plan['is_active'] == true;
  bool _auto = true;
  bool _saving = false;

  /// 1 TZS in USD / CNY.
  late final Map<String, double> _perTzs = {
    'usd': (widget.rates['USD'] as num?)?.toDouble() ?? 0,
    'cny': (widget.rates['CNY'] as num?)?.toDouble() ?? 0,
    'tzs': 1,
  };

  bool get _canConvert => _perTzs['usd']! > 0 && _perTzs['cny']! > 0;

  @override
  void dispose() {
    _name.dispose();
    _days.dispose();
    _devices.dispose();
    for (final c in _price.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _convertFrom(String from) {
    if (!_auto || !_canConvert) return;
    final v = double.tryParse(_price[from]!.text.trim());
    if (v == null || v <= 0) return;
    final tzs = v / _perTzs[from]!;
    for (final to in ['usd', 'cny', 'tzs'].where((c) => c != from)) {
      final raw = tzs * _perTzs[to]!;
      _price[to]!.text = switch (to) {
        'usd' => ((raw * 100).round() / 100).toStringAsFixed(2),
        'cny' => '${raw.round() < 1 ? 1 : raw.round()}',
        _ => '${(raw / 100).round() * 100}',
      };
    }
  }

  Future<void> _save() async {
    final body = {
      'name': _name.text.trim(),
      'days': int.tryParse(_days.text.trim()),
      'max_devices': int.tryParse(_devices.text.trim()),
      'price_usd': double.tryParse(_price['usd']!.text.trim()),
      'price_cny': double.tryParse(_price['cny']!.text.trim()),
      'price_tzs': double.tryParse(_price['tzs']!.text.trim()),
      'is_active': _active,
    };
    if (body.values.any((v) => v == null) || (body['name'] as String).isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Fill in every field with a number.')));
      return;
    }
    setState(() => _saving = true);
    try {
      final msg = await ref
          .read(adminVpnRepositoryProvider)
          .updatePlan((widget.plan['id'] as num).toInt(), body);
      ref.invalidate(adminVpnPlansProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _field(String label, TextEditingController c,
      {bool number = false, ValueChanged<String>? onChanged}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: c,
        onChanged: onChanged,
        keyboardType: number
            ? const TextInputType.numberWithOptions(decimal: true)
            : TextInputType.text,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final usd = _perTzs['usd']!;
    final cny = _perTzs['cny']!;
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text('${widget.plan['code']}'.toUpperCase())),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('On sale'),
            value: _active,
            onChanged: (v) => setState(() => _active = v),
          ),
          _field('Name', _name),
          _field('Days', _days, number: true),
          _field('Devices allowed', _devices, number: true),
          if (_canConvert) ...[
            _Note(
                "Today's rate: \$1 = ¥${(cny / usd).toStringAsFixed(2)} = TSh ${NumberFormat('#,##0').format(1 / usd)}"),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Convert prices automatically'),
              value: _auto,
              onChanged: (v) => setState(() => _auto = v),
            ),
          ],
          _field('Price USD (\$)', _price['usd']!,
              number: true, onChanged: (_) => _convertFrom('usd')),
          _field('Price CNY (¥)', _price['cny']!,
              number: true, onChanged: (_) => _convertFrom('cny')),
          _field('Price TZS (TSh), 0 hides TZS', _price['tzs']!,
              number: true, onChanged: (_) => _convertFrom('tzs')),
          const SizedBox(height: 8),
          MbuiButton(
            label: 'Save plan',
            fullWidth: true,
            loading: _saving,
            onPressed: _save,
          ),
        ],
      ),
    );
  }
}
