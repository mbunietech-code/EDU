import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/admin_catalogue_api.dart';
import '../../models/admin_catalogue.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_catalogue_forms.dart';

class AdminCatalogueScreen extends ConsumerWidget {
  const AdminCatalogueScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          title: const Text('Catalogue'),
          bottom: const TabBar(tabs: [
            Tab(text: 'Products'),
            Tab(text: 'Tools'),
            Tab(text: 'Scholarships'),
          ]),
        ),
        body: const TabBarView(children: [
          _ProductsTab(),
          _ToolsTab(),
          _ScholarshipsTab(),
        ]),
      ),
    );
  }
}

class _Fab extends StatelessWidget {
  const _Fab({required this.onPressed});
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => FloatingActionButton.extended(
        onPressed: onPressed,
        icon: const Icon(Icons.add),
        label: const Text('New'),
      );
}

// --------------------------------------------------------------------- Products
class _ProductsTab extends ConsumerWidget {
  const _ProductsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminCatProductsProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: _Fab(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const ProductFormScreen(),
        )),
      ),
      body: AsyncValueView<List<CatProduct>>(
        value: async,
        onRefresh: () async => ref.refresh(adminCatProductsProvider.future),
        data: (items) => _CatList(
          count: items.length,
          empty: 'No products yet.',
          itemBuilder: (i) {
            final p = items[i];
            return _CatCard(
              title: p.name,
              subtitle: '${p.type} · ${p.priceLabel} · '
                  '${p.plansCount} plans · ${p.ordersCount} orders',
              status: p.status,
              featured: p.isFeatured,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => ProductFormScreen(productId: p.id),
              )),
            );
          },
        ),
      ),
    );
  }
}

// ------------------------------------------------------------------------ Tools
class _ToolsTab extends ConsumerWidget {
  const _ToolsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminCatToolsProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: _Fab(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const ToolFormScreen(),
        )),
      ),
      body: AsyncValueView<List<CatTool>>(
        value: async,
        onRefresh: () async => ref.refresh(adminCatToolsProvider.future),
        data: (items) => _CatList(
          count: items.length,
          empty: 'No research tools yet.',
          itemBuilder: (i) {
            final t = items[i];
            return _CatCard(
              title: t.name,
              subtitle: [
                t.priceLabel,
                if (t.version != null) 'v${t.version}',
                '${t.ordersCount} orders',
              ].join(' · '),
              status: t.status,
              featured: t.isFeatured,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => ToolFormScreen(toolId: t.id),
              )),
            );
          },
        ),
      ),
    );
  }
}

// ----------------------------------------------------------------- Scholarships
class _ScholarshipsTab extends ConsumerWidget {
  const _ScholarshipsTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminCatScholarshipsProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: _Fab(
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const ScholarshipFormScreen(),
        )),
      ),
      body: AsyncValueView<List<CatScholarship>>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminCatScholarshipsProvider.future),
        data: (items) => _CatList(
          count: items.length,
          empty: 'No scholarships yet.',
          itemBuilder: (i) {
            final s = items[i];
            return _CatCard(
              title: s.title,
              subtitle: [
                if (s.country != null) s.country!,
                if (s.deadline != null)
                  s.isExpired ? 'closed ${s.deadline}' : 'deadline ${s.deadline}',
              ].join(' · '),
              status: s.status,
              featured: s.isFeatured,
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => ScholarshipFormScreen(scholarshipId: s.id),
              )),
            );
          },
        ),
      ),
    );
  }
}

// ------------------------------------------------------------------ shared bits
class _CatList extends StatelessWidget {
  const _CatList({
    required this.count,
    required this.empty,
    required this.itemBuilder,
  });

  final int count;
  final String empty;
  final Widget Function(int index) itemBuilder;

  @override
  Widget build(BuildContext context) {
    if (count == 0) {
      return ListView(
        children: [
          const SizedBox(height: 120),
          Center(
            child: Text(empty,
                style: const TextStyle(color: AppColors.gray500)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
      itemCount: count,
      itemBuilder: (context, i) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: itemBuilder(i),
      ),
    );
  }
}

class _CatCard extends StatelessWidget {
  const _CatCard({
    required this.title,
    required this.subtitle,
    required this.status,
    required this.featured,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final String status;
  final bool featured;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(title,
                    style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.gray900)),
              ),
              if (featured) ...[
                const Icon(Icons.star, size: 15, color: AppColors.amber700),
                const SizedBox(width: 6),
              ],
              MbuiStatusBadge(status),
            ],
          ),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(subtitle,
                style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
          ],
        ],
      ),
    );
  }
}
