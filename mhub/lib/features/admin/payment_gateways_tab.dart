import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

/// Automatic payments (AzamPay, ClickPesa, card, PayPal) — the app version
/// of Admin → Payment Methods → Automatic.

class GatewayField {
  GatewayField(Map<String, dynamic> j)
      : key = j['key'] as String,
        label = j['label'] as String? ?? '',
        type = j['type'] as String? ?? 'text',
        value = j['value'],
        isSet = j['is_set'] as bool? ?? false;

  final String key;
  final String label;

  /// toggle | mode | text | secret
  final String type;
  final dynamic value;
  final bool isSet;
}

class GatewaySetting {
  GatewaySetting(Map<String, dynamic> j)
      : key = j['key'] as String,
        label = j['label'] as String? ?? '',
        ready = j['ready'] as bool? ?? false,
        enabled = j['enabled'] as bool? ?? false,
        callbackUrl = j['callback_url'] as String?,
        fields = ((j['fields'] as List?) ?? const []).map((e) => GatewayField(e as Map<String, dynamic>)).toList();

  final String key;
  final String label;
  final bool ready;
  final bool enabled;
  final String? callbackUrl;
  final List<GatewayField> fields;
}

final paymentGatewaysProvider = FutureProvider.autoDispose<List<GatewaySetting>>((ref) async {
  final body = await ref.watch(apiClientProvider).get('/admin/payment-gateways') as Map<String, dynamic>;
  return ((body['data'] as List?) ?? const []).map((e) => GatewaySetting(e as Map<String, dynamic>)).toList();
});

class PaymentGatewaysTab extends ConsumerWidget {
  const PaymentGatewaysTab({super.key});

  static const _hints = {
    'azampay': 'M-Pesa, Mixx by Yas, Airtel, Halopesa, AzamPesa (USSD push)',
    'clickpesa': 'All Tanzanian mobile money (USSD push)',
    'clickpesa_card': 'Visa / Mastercard via ClickPesa, in USD. Uses the ClickPesa keys; needs ClickPesa KYC.',
    'paypal': 'PayPal balance or card, charged in USD',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(paymentGatewaysProvider);

    return AsyncValueView<List<GatewaySetting>>(
      value: async,
      onRefresh: () async => ref.refresh(paymentGatewaysProvider.future),
      data: (list) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'The customer pays online; the order is verified, marked paid and receipted by email automatically.',
            style: TextStyle(fontSize: 13, color: AppColors.gray500),
          ),
          const SizedBox(height: 12),
          for (final g in list)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                padding: const EdgeInsets.all(14),
                onTap: () async {
                  await Navigator.of(context).push(MaterialPageRoute(builder: (_) => _GatewayScreen(gateway: g)));
                  ref.invalidate(paymentGatewaysProvider);
                },
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(g.label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 2),
                          Text(_hints[g.key] ?? '', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    MbuiBadge(
                      g.ready ? 'Live' : (g.enabled ? 'Missing keys' : 'Off'),
                      appearance: g.ready
                          ? MbuiAppearance.success
                          : (g.enabled ? MbuiAppearance.warning : MbuiAppearance.neutral),
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

class _GatewayScreen extends ConsumerStatefulWidget {
  const _GatewayScreen({required this.gateway});
  final GatewaySetting gateway;

  @override
  ConsumerState<_GatewayScreen> createState() => _GatewayScreenState();
}

class _GatewayScreenState extends ConsumerState<_GatewayScreen> {
  late final Map<String, TextEditingController> _text = {
    for (final f in widget.gateway.fields)
      if (f.type == 'text' || f.type == 'secret')
        f.key: TextEditingController(text: f.type == 'text' ? (f.value as String? ?? '') : ''),
  };
  late bool _enabled = widget.gateway.enabled;
  late String _mode = (widget.gateway.fields.where((f) => f.type == 'mode').firstOrNull?.value as String?) ?? 'sandbox';
  bool _busy = false;

  ApiClient get _api => ref.read(apiClientProvider);

  void _toast(String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

  Future<void> _save() async {
    setState(() => _busy = true);
    final body = <String, dynamic>{};
    for (final f in widget.gateway.fields) {
      switch (f.type) {
        case 'toggle':
          body[f.key] = _enabled;
        case 'mode':
          body[f.key] = _mode;
        default:
          final v = _text[f.key]!.text.trim();
          // Blank secret = keep the saved one.
          if (f.type == 'text' || v.isNotEmpty) body[f.key] = v;
      }
    }
    try {
      final res = await _api.put('/admin/payment-gateways/${widget.gateway.key}', data: body) as Map<String, dynamic>;
      _toast(res['message'] as String? ?? 'Saved.');
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      _toast(e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _test() async {
    setState(() => _busy = true);
    try {
      final res = await _api.post('/admin/payment-gateways/${widget.gateway.key}/test') as Map<String, dynamic>;
      _toast(res['message'] as String? ?? 'Connected.');
    } on ApiException catch (e) {
      _toast(e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final g = widget.gateway;

    return Scaffold(
      appBar: AppBar(title: Text(g.label)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final f in g.fields)
            switch (f.type) {
              'toggle' => SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Show at checkout'),
                  value: _enabled,
                  onChanged: (v) => setState(() => _enabled = v),
                ),
              'mode' => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: DropdownButtonFormField<String>(
                    initialValue: _mode,
                    decoration: InputDecoration(labelText: f.label),
                    items: const [
                      DropdownMenuItem(value: 'sandbox', child: Text('Sandbox (testing)')),
                      DropdownMenuItem(value: 'live', child: Text('Live (real money)')),
                    ],
                    onChanged: (v) => setState(() => _mode = v ?? 'sandbox'),
                  ),
                ),
              _ => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: TextField(
                    controller: _text[f.key],
                    obscureText: f.type == 'secret',
                    autocorrect: false,
                    decoration: InputDecoration(
                      labelText: f.label,
                      hintText: f.type == 'secret' ? (f.isSet ? 'Saved. Leave blank to keep' : 'Paste here') : null,
                    ),
                  ),
                ),
            },
          if (g.callbackUrl != null) ...[
            const SizedBox(height: 8),
            MbuiCard(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Callback URL (paste on the ${g.label} dashboard)',
                      style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                  const SizedBox(height: 4),
                  SelectableText(g.callbackUrl!, style: const TextStyle(fontSize: 12)),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      icon: const Icon(Icons.copy, size: 16),
                      label: const Text('Copy'),
                      onPressed: () async {
                        await Clipboard.setData(ClipboardData(text: g.callbackUrl!));
                        _toast('Callback URL copied.');
                      },
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          MbuiButton(label: 'Save', fullWidth: true, loading: _busy, onPressed: _save),
          const SizedBox(height: 8),
          MbuiButton(
            label: 'Test connection',
            variant: MbuiVariant.secondary,
            fullWidth: true,
            onPressed: _busy ? null : _test,
          ),
        ],
      ),
    );
  }
}
