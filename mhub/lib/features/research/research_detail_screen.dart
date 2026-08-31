import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_reader_screen.dart';

class ResearchDetailScreen extends ConsumerWidget {
  const ResearchDetailScreen({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(researchDetailProvider(slug));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research')),
      body: AsyncValueView<ResearchDetail>(
        value: async,
        onRefresh: () async => ref.refresh(researchDetailProvider(slug).future),
        data: (r) {
          final first = r.chapters.isNotEmpty ? r.chapters.first : null;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (r.category != null)
                Text(r.category!,
                    style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.indigo600)),
              const SizedBox(height: 4),
              Text(r.title,
                  style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: AppColors.gray900,
                      height: 1.3)),
              const SizedBox(height: 4),
              Text(
                'by ${r.author ?? "Contributor"}'
                '${r.publishedAt != null ? " · ${r.publishedAt}" : ""}'
                ' · ${r.views} ${r.views == 1 ? "view" : "views"}',
                style: const TextStyle(fontSize: 12, color: AppColors.gray400),
              ),
              if (r.summary != null) ...[
                const SizedBox(height: 14),
                Text(r.summary!,
                    style: const TextStyle(
                        fontSize: 14, color: AppColors.gray700, height: 1.55)),
              ],
              if (r.percent > 0) ...[
                const SizedBox(height: 16),
                Row(
                  children: [
                    const Text('Your progress',
                        style: TextStyle(fontSize: 12, color: AppColors.gray500)),
                    const Spacer(),
                    Text('${r.percent}%',
                        style: const TextStyle(
                            fontSize: 12, color: AppColors.gray500)),
                  ],
                ),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: r.percent / 100,
                    minHeight: 8,
                    backgroundColor: AppColors.gray100,
                    valueColor:
                        const AlwaysStoppedAnimation(AppColors.indigo600),
                  ),
                ),
              ],
              if (first != null) ...[
                const SizedBox(height: 20),
                MbuiButton(
                  label: r.percent > 0 ? 'Continue reading' : 'Start reading',
                  icon: Icons.menu_book_outlined,
                  fullWidth: true,
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => ResearchReaderScreen(
                      slug: r.slug,
                      chapterId: first.id,
                    ),
                  )),
                ),
              ],
              const SizedBox(height: 24),
              const MbuiSectionLabel('Contents'),
              const SizedBox(height: 8),
              for (final ch in r.chapters)
                MbuiCard(
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) =>
                        ResearchReaderScreen(slug: r.slug, chapterId: ch.id),
                  )),
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(ch.title,
                          style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: AppColors.gray900)),
                      if (ch.sections.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        for (final s in ch.sections)
                          Padding(
                            padding: const EdgeInsets.only(top: 2, left: 8),
                            child: Text('· ${s.heading}',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.gray500)),
                          ),
                      ],
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
