import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/config.dart';
import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

/// Contributors track their submissions here. Writing/editing happens on the
/// website (a phone is a poor place to write long-form research).
class MyResearchScreen extends ConsumerWidget {
  const MyResearchScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(myResearchProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('My Research')),
      body: AsyncValueView<List<MyResearchRow>>(
        value: async,
        onRefresh: () async => ref.refresh(myResearchProvider.future),
        data: (rows) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  const Icon(Icons.edit_note, color: AppColors.indigo600),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text(
                      'Create and edit research on the website. Track review status here.',
                      style: TextStyle(fontSize: 12.5, color: AppColors.gray600),
                    ),
                  ),
                  TextButton(
                    onPressed: () => launchUrl(
                      Uri.parse('${AppConfig.apiBase}/my-research'),
                      mode: LaunchMode.externalApplication,
                    ),
                    child: const Text('Open web'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            if (rows.isEmpty)
              const MbuiCard(
                child: Text('You have no research yet.',
                    style: TextStyle(fontSize: 13, color: AppColors.gray500)),
              )
            else
              for (final r in rows)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(14),
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
