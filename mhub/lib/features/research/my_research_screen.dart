import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_editor_screen.dart';

/// Contributors write research here: create, edit chapters and sections,
/// import a document and submit for review (same rules as the website).
class MyResearchScreen extends ConsumerWidget {
  const MyResearchScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(myResearchProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('My Research')),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(Icons.add),
        label: const Text('New research'),
        onPressed: () => startNewResearch(context, ref),
      ),
      body: AsyncValueView<List<MyResearchRow>>(
        value: async,
        onRefresh: () async => ref.refresh(myResearchProvider.future),
        data: (rows) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (rows.isEmpty)
              const MbuiCard(
                child: Text('You have no research yet. Tap New research to start.',
                    style: TextStyle(fontSize: 13, color: AppColors.gray500)),
              )
            else
              for (final r in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(14),
                    onTap: r.id == 0
                        ? null
                        : () => Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) => ResearchEditorScreen(id: r.id),
                            )),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(r.title,
                                  style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.gray900)),
                            ),
                            MbuiStatusBadge(r.status),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${r.category ?? "Uncategorised"} · '
                          '${r.chaptersCount} ${r.chaptersCount == 1 ? "chapter" : "chapters"} · '
                          '${r.updatedAgo ?? ""}',
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.gray400),
                        ),
                        if (r.reviewNote != null) ...[
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: AppColors.amber50,
                              borderRadius: BorderRadius.circular(AppRadius.md),
                            ),
                            child: Text('Changes requested: ${r.reviewNote}',
                                style: const TextStyle(
                                    fontSize: 12, color: AppColors.amber700)),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}
