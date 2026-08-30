import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../models/product.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import '../orders/order_detail_screen.dart';
import '../orders/orders_repository.dart';
import 'products_repository.dart';

class ProductDetailScreen extends ConsumerStatefulWidget {
  const ProductDetailScreen({super.key, required this.slug, required this.name});

  final String slug;
  final String name;

  @override
  ConsumerState<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends ConsumerState<ProductDetailScreen> {
  int? _planId;
  bool _placing = false;

  Future<void> _placeOrder(Product product) async {
    final planId = _planId ?? (product.plans.isNotEmpty ? product.plans.first.id : null);
    if (planId == null) return;

    setState(() => _placing = true);
    try {
      final order = await ref
          .read(ordersRepositoryProvider)
          .create(productId: product.id, planId: planId);
      ref.invalidate(ordersProvider);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => OrderDetailScreen(orderId: order.id, title: order.title),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _placing = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(productDetailProvider(widget.slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(widget.name)),
      body: AsyncValueView<Product>(
        value: async,
        onRefresh: () async => ref.refresh(productDetailProvider(widget.slug).future),
        data: (product) {
          final selected = _planId ??
              (product.plans.isNotEmpty ? product.plans.first.id : null);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (product.imageUrl != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                  child: Image.network(product.imageUrl!,
                      height: 160,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => const SizedBox.shrink()),
                ),
              const SizedBox(height: 16),
              MbuiTitle(product.name),
              if (product.description != null) ...[
                const SizedBox(height: 8),
                Text(product.description!,
                    style: const TextStyle(fontSize: 14, color: AppColors.gray600, height: 1.5)),
              ],
              if (product.features.isNotEmpty) ...[
                const SizedBox(height: 16),
                MbuiCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (final f in product.features)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.check_circle,
                                  size: 18, color: AppColors.emerald600),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(f,
                                    style: const TextStyle(
                                        fontSize: 13.5, color: AppColors.gray700)),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 24),
              const MbuiSectionLabel('Choose a plan'),
              const SizedBox(height: 8),
              if (product.plans.isEmpty)
                const MbuiCard(child: Text('No plans available right now.'))
              else
                for (final plan in product.plans)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: MbuiCard(
                      onTap: () => setState(() => _planId = plan.id),
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Icon(
                            selected == plan.id
                                ? Icons.radio_button_checked
                                : Icons.radio_button_off,
                            color: selected == plan.id
                                ? AppColors.indigo600
                                : AppColors.gray400,
                            size: 20,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(plan.name,
                                    style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.gray900)),
                                if (plan.duration.isNotEmpty ||
                                    plan.description != null) ...[
                                  const SizedBox(height: 2),
                                  Text(
                                    [
                                      if (plan.duration.isNotEmpty) plan.duration,
                                      if (plan.description != null) plan.description!,
                                    ].join(' · '),
                                    style: const TextStyle(
                                        fontSize: 12, color: AppColors.gray500),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(plan.priceLabel,
                              style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.gray900)),
                        ],
                      ),
                    ),
                  ),
              const SizedBox(height: 20),
              if (product.plans.isNotEmpty)
                MbuiButton(
                  label: 'Continue to payment',
                  icon: Icons.shopping_bag_outlined,
                  fullWidth: true,
                  loading: _placing,
                  onPressed: () => _placeOrder(product),
                ),
              const SizedBox(height: 8),
              const Text(
                'Placing an order creates a pending order. You then submit your '
                'payment for review — same as the website.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500),
              ),
            ],
          );
        },
      ),
    );
  }
}
