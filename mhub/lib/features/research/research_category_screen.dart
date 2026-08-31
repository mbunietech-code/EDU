import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_detail_screen.dart';

class ResearchCategoryScreen extends ConsumerWidget {
  const ResearchCategoryScreen({super.key, required this.slug, required this.name});
  final String slug;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(researchCategoryProvider(slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(name)),
      body: AsyncValueView<({String name, String? description, List<ResearchCard> items})>(
        value: async,
        onRefresh: () async => ref.refresh(researchCategoryProvider(slug).future),
        data: (data) {
          if (data.items.isEmpty) {
            return const Center(
              child: Text('Nothing here yet.',
                  style: TextStyle(color: AppColors.gray500)),
            );
          }
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (data.description != null) ...[
                Text(data.description!,
                    style: const TextStyle(fontSize: 13, color: AppColors.gray600)),
                const SizedBox(height: 12),
              ],
              for (final r in data.items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: MbuiCard(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ResearchDetailScreen(slug: r.slug),
                    )),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(r.title,
                            style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: AppColors.gray900)),
                        if (r.summary != null) ...[
                          const SizedBox(height: 4),
                          Text(r.summary!,
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 13, color: AppColors.gray500)),
                        ],
                        const SizedBox(height: 6),
                        Text('by ${r.author ?? "Contributor"} · ${r.publishedAgo ?? ""}',
                            style: const TextStyle(
                                fontSize: 11, color: AppColors.gray400)),
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
