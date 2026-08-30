import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/product.dart';
import '../../widgets/async_value_view.dart';
import 'products_repository.dart';

class ProductDetailScreen extends ConsumerWidget {
  const ProductDetailScreen({super.key, required this.slug, required this.name});

  final String slug;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(productDetailProvider(slug));

    return Scaffold(
      appBar: AppBar(title: Text(name)),
      body: AsyncValueView<Product>(
        value: async,
        onRefresh: () async => ref.refresh(productDetailProvider(slug).future),
        data: (product) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (product.imageUrl != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Image.network(product.imageUrl!,
                    height: 160, width: double.infinity, fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const SizedBox.shrink()),
              ),
            const SizedBox(height: 16),
            Text(product.name,
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.bold)),
            if (product.description != null) ...[
              const SizedBox(height: 8),
              Text(product.description!),
            ],
            if (product.features.isNotEmpty) ...[
              const SizedBox(height: 16),
              for (final f in product.features)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.check_circle, size: 18, color: Colors.green),
                      const SizedBox(width: 8),
                      Expanded(child: Text(f)),
                    ],
                  ),
                ),
            ],
            const SizedBox(height: 24),
            Text('Plans', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (product.plans.isEmpty)
              const Text('No plans available right now.')
            else
              for (final plan in product.plans)
                Card(
                  child: ListTile(
                    title: Text(plan.name),
                    subtitle: Text(
                      [plan.duration, if (plan.description != null) plan.description!]
                          .join(' · '),
                    ),
                    trailing: Text(plan.priceLabel,
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: () => showDialog(
                context: context,
                builder: (_) => AlertDialog(
                  title: const Text('Order on the web'),
                  content: const Text(
                    'Placing an order and paying is available on the MbunieEduHub '
                    'website for now. Open it in your browser to continue.',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('OK'),
                    ),
                  ],
                ),
              ),
              icon: const Icon(Icons.shopping_cart_outlined),
              label: const Text('How to order'),
            ),
          ],
        ),
      ),
    );
  }
}
