import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/research_request.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'new_research_request_screen.dart';
import 'research_request_detail_screen.dart';
import 'research_requests_repository.dart';

/// "Research requests": research work the customer asked the team to do,
/// with filters and a progress bar per request.
class ResearchRequestsScreen extends ConsumerWidget {
  const ResearchRequestsScreen({super.key});

  static const _filters = {'all': 'All', 'active': 'In progress', 'completed': 'Completed', 'cancelled': 'Cancelled'};

  Future<void> _new(BuildContext context, WidgetRef ref) async {
    final created = await Navigator.of(context).push<ResearchRequest>(
      MaterialPageRoute(builder: (_) => const NewResearchRequestScreen()),
    );
    ref.invalidate(researchRequestsProvider);
    if (created != null && context.mounted) {
      Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => ResearchRequestDetailScreen(requestId: created.id, title: created.reference),
      ));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(researchRequestsFilterProvider);
    final async = ref.watch(researchRequestsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research requests')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _new(context, ref),
        backgroundColor: AppColors.indigo600,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('New request'),
      ),
      body: Column(
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
              children: [
                for (final e in _filters.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(
                        async.valueOrNull?.counts[e.key] != null ? '${e.value} · ${async.valueOrNull!.counts[e.key]}' : e.value,
                      ),
                      selected: filter == e.key,
                      selectedColor: AppColors.indigo600,
                      labelStyle: TextStyle(color: filter == e.key ? Colors.white : AppColors.gray700, fontWeight: FontWeight.w500),
                      showCheckmark: false,
                      onSelected: (_) => ref.read(researchRequestsFilterProvider.notifier).state = e.key,
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: AsyncValueView(
              value: async,
              onRefresh: () async => ref.refresh(researchRequestsProvider.future),
              data: (result) {
                if (result.items.isEmpty) {
                  return ListView(
                    padding: const EdgeInsets.all(24),
                    children: [
                      const SizedBox(height: 40),
                      const Icon(Icons.assignment_outlined, size: 56, color: AppColors.indigo500),
                      const SizedBox(height: 16),
                      Text(
                        filter == 'all' ? 'No research requests yet' : 'Nothing here',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.gray900),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'Need a proposal, a thesis chapter, data analysis or editing? Describe it and our researchers take it from there.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: AppColors.gray500),
                      ),
                      const SizedBox(height: 20),
                      Center(child: MbuiButton(label: 'Ask for research work', icon: Icons.add, onPressed: () => _new(context, ref))),
                    ],
                  );
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                  itemCount: result.items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, i) => _RequestCard(
                    rr: result.items[i],
                    onTap: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => ResearchRequestDetailScreen(requestId: result.items[i].id, title: result.items[i].reference),
                      ));
                      ref.invalidate(researchRequestsProvider);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.rr, required this.onTap});

  final ResearchRequest rr;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      padding: const EdgeInsets.all(16),
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${rr.reference} · ${rr.typeLabel}',
                        style: const TextStyle(fontSize: 12, color: AppColors.gray500), maxLines: 1, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: 4),
                    Text(rr.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.gray900)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _StagePill(label: rr.stateLabel, color: rr.color),
                  if (rr.hasUpdate) ...[
                    const SizedBox(height: 6),
                    const Row(mainAxisSize: MainAxisSize.min, children: [
                      CircleAvatar(radius: 4, backgroundColor: AppColors.indigo600),
                      SizedBox(width: 4),
                      Text('New update', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.indigo600)),
                    ]),
                  ],
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(99),
                  child: LinearProgressIndicator(
                    value: rr.progress / 100,
                    minHeight: 8,
                    backgroundColor: AppColors.gray100,
                    valueColor: AlwaysStoppedAnimation(rr.color),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text('${rr.progress}%', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.gray900)),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 12,
            runSpacing: 4,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              if (rr.deadlineLabel != null)
                Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.schedule, size: 14, color: rr.overdue ? AppColors.red600 : (rr.dueSoon ? AppColors.amber700 : AppColors.gray500)),
                  const SizedBox(width: 3),
                  Text(rr.deadlineLabel!,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: rr.overdue || rr.dueSoon ? FontWeight.w600 : FontWeight.normal,
                        color: rr.overdue ? AppColors.red600 : (rr.dueSoon ? AppColors.amber700 : AppColors.gray500),
                      )),
                ]),
              Text(rr.levelLabel, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
              if (rr.quotePending) MbuiBadge('Price waiting: ${rr.quoteLabel ?? ''}', appearance: MbuiAppearance.warning),
              if (rr.createdAgo != null) Text(rr.createdAgo!, style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
            ],
          ),
        ],
      ),
    );
  }
}

class _StagePill extends StatelessWidget {
  const _StagePill({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(AppRadius.sm),
          border: Border.all(color: color.withValues(alpha: 0.25)),
        ),
        child: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: color)),
      );
}
