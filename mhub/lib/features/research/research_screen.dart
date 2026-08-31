import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_category_screen.dart';
import 'research_detail_screen.dart';

class ResearchScreen extends ConsumerWidget {
  const ResearchScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(researchHomeProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research & Consultancy')),
      body: AsyncValueView<ResearchHome>(
        value: async,
        onRefresh: () async => ref.refresh(researchHomeProvider.future),
        data: (home) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (home.continueReading.isNotEmpty) ...[
              const MbuiSectionLabel('Continue reading'),
              const SizedBox(height: 8),
              for (final r in home.continueReading)
                _ContinueCard(card: r),
              const SizedBox(height: 20),
            ],

            const MbuiSectionLabel('Browse by area'),
            const SizedBox(height: 8),
            if (home.categories.isEmpty)
              const MbuiCard(
                child: Text('Nothing published yet. Approved research appears here.',
                    style: TextStyle(fontSize: 13, color: AppColors.gray500)),
              )
            else
              for (final c in home.categories)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: MbuiCard(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ResearchCategoryScreen(slug: c.slug, name: c.name),
                    )),
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: AppColors.indigo50,
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          alignment: Alignment.center,
                          child: const Icon(Icons.menu_book_outlined,
                              size: 20, color: AppColors.indigo600),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(c.name,
                                  style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.gray900)),
                              if (c.description != null)
                                Text(c.description!,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontSize: 12, color: AppColors.gray500)),
                              const SizedBox(height: 2),
                              Text('${c.count} ${c.count == 1 ? "paper" : "papers"}',
                                  style: const TextStyle(
                                      fontSize: 11, color: AppColors.gray400)),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right, color: AppColors.gray400),
                      ],
                    ),
                  ),
                ),

            if (home.recent.isNotEmpty) ...[
              const SizedBox(height: 20),
              const MbuiSectionLabel('Recently published'),
              const SizedBox(height: 8),
              for (final r in home.recent)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ResearchDetailScreen(slug: r.slug),
                    )),
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (r.category != null)
                          Text(r.category!,
                              style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.indigo600)),
                        Text(r.title,
                            style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.gray900)),
                        const SizedBox(height: 2),
                        Text('by ${r.author ?? "Contributor"} · ${r.publishedAgo ?? ""}',
                            style: const TextStyle(
                                fontSize: 11, color: AppColors.gray400)),
                      ],
                    ),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ContinueCard extends StatelessWidget {
  const _ContinueCard({required this.card});
  final ResearchCard card;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: MbuiCard(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => ResearchDetailScreen(slug: card.slug),
        )),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (card.category != null)
              Text(card.category!,
                  style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.indigo600)),
            Text(card.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.gray900)),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: (card.percent ?? 0) / 100,
                minHeight: 6,
                backgroundColor: AppColors.gray100,
                valueColor: const AlwaysStoppedAnimation(AppColors.indigo600),
              ),
            ),
            const SizedBox(height: 3),
            Text('${card.percent ?? 0}% complete',
                style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
          ],
        ),
      ),
    );
  }
}
