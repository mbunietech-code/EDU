import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/product.dart';
import '../../widgets/async_value_view.dart';
import 'product_detail_screen.dart';
import 'products_repository.dart';

class ProductsScreen extends ConsumerWidget {
  const ProductsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(productsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('AI Tools')),
      body: AsyncValueView<List<Product>>(
        value: async,
        onRefresh: () async => ref.refresh(productsProvider.future),
        data: (products) {
          if (products.isEmpty) {
            return const Center(child: Text('No tools available yet.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemcount: products.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) => _ProductCard(product: products[i]),
          );
        },
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ProductDetailScreen(slug: product.slug, name: product.name),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              _Thumb(url: product.imageUrl, label: product.name),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(product.name,
                              style: theme.textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold)),
                        ),
                        if (product.isFeatured)
                          const Icon(Icons.star, size: 16, color: Colors.amber),
                      ],
                    ),
                    if (product.shortDescription != null) ...[
                      const SizedBox(height: 4),
                      Text(product.shortDescription!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall),
                    ],
                    const SizedBox(height: 8),
                    Text(
                      product.fromPriceLabel ?? '${product.plansCount} plan(s)',
                      style: theme.textTheme.labelLarge
                          ?.copyWith(color: theme.colorScheme.primary),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({this.url, required this.label});

  final String? url;
  final String label;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      alignment: Alignment.center,
      child: Text(
        label.isNotEmpty ? label[0].toUpperCase() : '?',
        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
      ),
    );

    if (url == null) return fallback;
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Image.network(
        url!,
        width: 56,
        height: 56,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => fallback,
      ),
    );
  }
}
