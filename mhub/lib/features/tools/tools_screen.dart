import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/currency_repository.dart';
import '../../data/member_api.dart';
import '../../models/tool.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../../widgets/price_label.dart';
import '../orders/order_detail_screen.dart';
import '../orders/orders_repository.dart';
import '../payments/pay_order_screen.dart';

class ToolsScreen extends ConsumerWidget {
  const ToolsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(toolsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research Tools')),
      body: AsyncValueView<List<Tool>>(
        value: async,
        onRefresh: () async => ref.refresh(toolsProvider.future),
        data: (tools) {
          if (tools.isEmpty) {
            return const Center(child: Text('No tools available yet.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tools.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, i) => _ToolCard(tool: tools[i]),
          );
        },
      ),
    );
  }
}

class _ToolCard extends ConsumerWidget {
  const _ToolCard({required this.tool});
  final Tool tool;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rates = ref.watch(currencyRatesValueProvider);
    return MbuiCard(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => ToolDetailScreen(slug: tool.slug, name: tool.name)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: AppColors.indigo50,
              borderRadius: BorderRadius.circular(10),
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.science_outlined, color: AppColors.indigo600),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tool.name,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                if (tool.shortDescription != null) ...[
                  const SizedBox(height: 4),
                  Text(tool.shortDescription!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12.5, color: AppColors.gray500)),
                ],
                const SizedBox(height: 6),
                PriceLabel(amountTzs: tool.price, rates: rates),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AppColors.gray400),
        ],
      ),
    );
  }
}

class ToolDetailScreen extends ConsumerWidget {
  const ToolDetailScreen({super.key, required this.slug, required this.name});
  final String slug;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(toolDetailProvider(slug));
    final rates = ref.watch(currencyRatesValueProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(name)),
      body: AsyncValueView<Tool>(
        value: async,
        onRefresh: () async => ref.refresh(toolDetailProvider(slug).future),
        data: (t) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(t.name,
                style: const TextStyle(
                    fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.gray900)),
            if (t.version != null) ...[
              const SizedBox(height: 4),
              Text('Version ${t.version}',
                  style: const TextStyle(color: AppColors.gray500)),
            ],
            const SizedBox(height: 12),
            PriceLabel(amountTzs: t.price, rates: rates, tzsSize: 15),
            const SizedBox(height: 16),
            if (t.description != null)
              Text(t.description!,
                  style: const TextStyle(color: AppColors.gray700, height: 1.5)),
            const SizedBox(height: 24),
            _BuyToolButton(tool: t),
            const SizedBox(height: 8),
            const Text(
              'After payment your product key and download appear on the order.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppColors.gray500),
            ),
          ],
        ),
      ),
    );
  }
}

/// Buy a research tool: create the order, then go straight to payment.
class _BuyToolButton extends ConsumerStatefulWidget {
  const _BuyToolButton({required this.tool});
  final Tool tool;

  @override
  ConsumerState<_BuyToolButton> createState() => _BuyToolButtonState();
}

class _BuyToolButtonState extends ConsumerState<_BuyToolButton> {
  bool _busy = false;

  Future<void> _buy() async {
    setState(() => _busy = true);
    try {
      final order = await ref.read(ordersRepositoryProvider).createForTool(widget.tool.id);
      ref.invalidate(ordersProvider);
      if (!mounted) return;
      final paid = await Navigator.of(context).push<bool>(MaterialPageRoute(
        builder: (_) => PayOrderScreen(orderId: order.id, orderTitle: widget.tool.name, amountLabel: order.amountLabel),
      ));
      if (mounted) {
        // Paid or not, the order page shows its status, key and download.
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: order.id, title: widget.tool.name)));
        if (paid == true) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Payment received. Your key is on the order.')));
        }
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => MbuiButton(
        label: 'Buy now · ${widget.tool.priceLabel}',
        icon: Icons.shopping_cart_checkout,
        fullWidth: true,
        loading: _busy,
        onPressed: _buy,
      );
}
