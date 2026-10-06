import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/member_api.dart';
import '../../models/gateway_payment.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../auth/auth_controller.dart';
import 'gateway_wait_screen.dart';
import 'submit_payment_screen.dart';

/// Mirrors the web checkout: one tab per way to pay (Mobile money, Card,
/// PayPal, Pay manually). Only switched-on options get a tab.
class PayOrderScreen extends ConsumerWidget {
  const PayOrderScreen({
    super.key,
    required this.orderId,
    required this.orderTitle,
    required this.amountLabel,
  });

  final int orderId;
  final String orderTitle;
  final String amountLabel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final options = ref.watch(paymentOptionsProvider(orderId));

    // Loading / error: keep an app bar so the user can always go back.
    if (!options.hasValue) {
      return Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: const Text('Pay for order')),
        body: AsyncValueView<PaymentOptions>(
          value: options,
          onRefresh: () async => ref.refresh(paymentOptionsProvider(orderId).future),
          data: (_) => const SizedBox.shrink(),
        ),
      );
    }

    return Builder(
      builder: (context) {
        final o = options.requireValue;
        final tabs = <(String, Widget)>[
          if (o.mobile.isNotEmpty) ('Mobile money', _MobileMoneyTab(options: o)),
          if (o.card != null) ('Card', _CardTab(options: o)),
          if (o.payPal != null) ('PayPal', _PayPalTab(options: o)),
          if (o.manual || (o.mobile.isEmpty && o.card == null && o.payPal == null))
            (
              'Pay manually',
              SubmitPaymentScreen(
                orderId: orderId,
                orderTitle: orderTitle,
                amountLabel: amountLabel,
                embedded: true,
              ),
            ),
        ];

        return DefaultTabController(
          length: tabs.length,
          child: Scaffold(
            backgroundColor: AppColors.pageBackground,
            appBar: AppBar(
              title: const Text('Pay for order'),
              bottom: tabs.length > 1
                  ? TabBar(
                      isScrollable: true,
                      tabAlignment: TabAlignment.start,
                      labelColor: AppColors.indigo600,
                      unselectedLabelColor: AppColors.gray500,
                      indicatorColor: AppColors.indigo600,
                      tabs: [for (final t in tabs) Tab(text: t.$1)],
                    )
                  : null,
            ),
            body: Column(
              children: [
                _OrderSummary(title: orderTitle, amountLabel: o.amountLabel),
                Expanded(
                  child: TabBarView(children: [for (final t in tabs) t.$2]),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _OrderSummary extends StatelessWidget {
  const _OrderSummary({required this.title, required this.amountLabel});
  final String title;
  final String amountLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Row(
        children: [
          Expanded(
            child: Text(title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.gray900)),
          ),
          const SizedBox(width: 12),
          Text(amountLabel,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.indigo700)),
        ],
      ),
    );
  }
}

/// Starts a payment, then waits for it; closes the pay screen on success.
mixin _StartsPayment<T extends ConsumerStatefulWidget> on ConsumerState<T> {
  PaymentOptions get paymentOptions;

  bool busy = false;
  String? error;
  Map<String, List<String>>? fieldErrors;

  String? fieldError(String field) => fieldErrors?[field]?.first;

  Future<void> startAndWait(Future<GatewayPayment> Function() start, {bool openPage = false}) async {
    setState(() {
      busy = true;
      error = null;
      fieldErrors = null;
    });

    GatewayPayment? payment;
    try {
      payment = await start();
    } on ApiException catch (e) {
      // 409: a push for this order is already waiting; show that one.
      if (e.statusCode == 409) {
        payment = (await ref.refresh(paymentOptionsProvider(paymentOptions.orderId).future)).active;
      }
      if (payment == null) {
        if (mounted) {
          setState(() {
            busy = false;
            error = e.errors == null ? e.message : null;
            fieldErrors = e.errors;
          });
        }
        return;
      }
    }

    if (!mounted) return;
    setState(() => busy = false);

    final paid = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => GatewayWaitScreen(initial: payment!, openPageNow: openPage)),
    );

    if (!mounted) return;
    ref.invalidate(paymentOptionsProvider(paymentOptions.orderId));
    if (paid == true) Navigator.of(context).pop(true);
  }
}

class _MobileMoneyTab extends ConsumerStatefulWidget {
  const _MobileMoneyTab({required this.options});
  final PaymentOptions options;

  @override
  ConsumerState<_MobileMoneyTab> createState() => _MobileMoneyTabState();
}

class _MobileMoneyTabState extends ConsumerState<_MobileMoneyTab> with _StartsPayment {
  @override
  PaymentOptions get paymentOptions => widget.options;

  late MobileGatewayOption _gateway = widget.options.mobile.first;
  String? _network;
  final _phone = TextEditingController();

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  void _pay() {
    if (_gateway.networks.isNotEmpty && _network == null) {
      setState(() => error = 'Choose your mobile money network.');
      return;
    }
    startAndWait(() => ref.read(paymentsRepositoryProvider).startMobile(
          orderId: widget.options.orderId,
          gateway: _gateway.key,
          phone: _phone.text.trim(),
          network: _gateway.networks.isEmpty ? null : _network,
        ));
  }

  @override
  Widget build(BuildContext context) {
    final active = widget.options.active;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (active != null && !active.usesRedirect)
          _Notice(
            text: 'A payment request is already waiting on ${active.phone ?? 'your phone'}.',
            action: 'Open',
            onTap: () => startAndWait(() async => active),
          ),
        MbuiCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Pay instantly with mobile money',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
              const SizedBox(height: 6),
              const Text(
                'We send a payment request to your phone. Enter your PIN and your order is confirmed automatically.',
                style: TextStyle(fontSize: 13, height: 1.4, color: AppColors.gray500),
              ),
              if (widget.options.mobile.length > 1) ...[
                const SizedBox(height: 16),
                const _Label('Pay with'),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final g in widget.options.mobile)
                      ChoiceChip(
                        label: Text(g.label),
                        selected: g.key == _gateway.key,
                        onSelected: (_) => setState(() {
                          _gateway = g;
                          _network = null;
                        }),
                      ),
                  ],
                ),
              ],
              if (_gateway.networks.isNotEmpty) ...[
                const SizedBox(height: 16),
                const _Label('Network'),
                DropdownButtonFormField<String>(
                  initialValue: _network,
                  isExpanded: true,
                  hint: const Text('Choose network'),
                  items: [
                    for (final n in _gateway.networks) DropdownMenuItem(value: n.value, child: Text(n.label)),
                  ],
                  onChanged: (v) => setState(() => _network = v),
                  decoration: InputDecoration(errorText: fieldError('network')),
                ),
              ],
              const SizedBox(height: 16),
              const _Label('Mobile money number'),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(hintText: '0712 345 678', errorText: fieldError('phone')),
              ),
            ],
          ),
        ),
        _ErrorBox(error),
        const SizedBox(height: 20),
        MbuiButton(
          label: 'Pay ${widget.options.amountLabel}',
          icon: Icons.phone_android,
          loading: busy,
          fullWidth: true,
          onPressed: _pay,
        ),
      ],
    );
  }
}

class _CardTab extends ConsumerStatefulWidget {
  const _CardTab({required this.options});
  final PaymentOptions options;

  @override
  ConsumerState<_CardTab> createState() => _CardTabState();
}

class _CardTabState extends ConsumerState<_CardTab> with _StartsPayment {
  @override
  PaymentOptions get paymentOptions => widget.options;

  late final _name = TextEditingController(text: ref.read(authControllerProvider).user?.name ?? '');
  final _phone = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final card = widget.options.card!;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        MbuiCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Pay by card (Visa / Mastercard)',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
              const SizedBox(height: 6),
              Text(
                'You will be charged ${card.display} (${widget.options.amountLabel} at today\'s rate) on '
                'ClickPesa\'s secure card page. We never see your card number.',
                style: const TextStyle(fontSize: 13, height: 1.4, color: AppColors.gray500),
              ),
              const SizedBox(height: 16),
              const _Label('Name on card'),
              TextField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: InputDecoration(errorText: fieldError('card_name')),
              ),
              const SizedBox(height: 16),
              const _Label('Phone number'),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(hintText: '0712 345 678', errorText: fieldError('card_phone')),
              ),
            ],
          ),
        ),
        _ErrorBox(error),
        const SizedBox(height: 20),
        MbuiButton(
          label: 'Pay ${card.display} by card',
          icon: Icons.credit_card,
          loading: busy,
          fullWidth: true,
          onPressed: () => startAndWait(
            () => ref.read(paymentsRepositoryProvider).startCard(
                  orderId: widget.options.orderId,
                  nameOnCard: _name.text.trim(),
                  phone: _phone.text.trim(),
                ),
            openPage: true,
          ),
        ),
      ],
    );
  }
}

class _PayPalTab extends ConsumerStatefulWidget {
  const _PayPalTab({required this.options});
  final PaymentOptions options;

  @override
  ConsumerState<_PayPalTab> createState() => _PayPalTabState();
}

class _PayPalTabState extends ConsumerState<_PayPalTab> with _StartsPayment {
  @override
  PaymentOptions get paymentOptions => widget.options;

  @override
  Widget build(BuildContext context) {
    final payPal = widget.options.payPal!;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        MbuiCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Pay with PayPal or card',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
              const SizedBox(height: 6),
              Text(
                'You will be charged ${payPal.display} (${widget.options.amountLabel} at today\'s rate) on '
                'PayPal\'s secure page. Pay with your PayPal account or any card, then come back to the app.',
                style: const TextStyle(fontSize: 13, height: 1.4, color: AppColors.gray500),
              ),
            ],
          ),
        ),
        _ErrorBox(error),
        const SizedBox(height: 20),
        SizedBox(
          height: 48,
          child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFFFC439),
              foregroundColor: const Color(0xFF003087),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.md)),
            ),
            onPressed: busy
                ? null
                : () => startAndWait(
                      () => ref.read(paymentsRepositoryProvider).startPayPal(widget.options.orderId),
                      openPage: true,
                    ),
            child: busy
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Pay with PayPal', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
      ],
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: AppColors.gray700)),
      );
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox(this.error);
  final String? error;

  @override
  Widget build(BuildContext context) {
    if (error == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(top: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.red50,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.red600.withValues(alpha: 0.3)),
      ),
      child: Text(error!, style: const TextStyle(color: AppColors.red700, fontSize: 13)),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text, required this.action, required this.onTap});
  final String text;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
      decoration: BoxDecoration(
        color: AppColors.amber50,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Expanded(child: Text(text, style: const TextStyle(fontSize: 13, color: AppColors.amber700))),
          TextButton(onPressed: onTap, child: Text(action)),
        ],
      ),
    );
  }
}
