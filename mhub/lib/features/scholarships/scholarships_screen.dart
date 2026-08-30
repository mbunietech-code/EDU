import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/member_api.dart';
import '../../models/scholarship.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class ScholarshipsScreen extends ConsumerWidget {
  const ScholarshipsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(scholarshipsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Scholarships')),
      body: AsyncValueView<List<Scholarship>>(
        value: async,
        onRefresh: () async => ref.refresh(scholarshipsProvider.future),
        data: (items) {
          if (items.isEmpty) {
            return const Center(child: Text('No open scholarships right now.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, i) => _Card(item: items[i]),
          );
        },
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.item});
  final Scholarship item;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => ScholarshipDetailScreen(slug: item.slug, title: item.title)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(item.title,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
              ),
              if (item.isFeatured)
                const Icon(Icons.star, size: 15, color: Color(0xFFF59E0B)),
            ],
          ),
          if (item.country != null) ...[
            const SizedBox(height: 2),
            Text(item.country!,
                style: const TextStyle(fontSize: 12.5, color: AppColors.gray500)),
          ],
          if (item.shortDescription != null) ...[
            const SizedBox(height: 8),
            Text(item.shortDescription!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13, color: AppColors.gray700)),
          ],
          const SizedBox(height: 10),
          MbuiBadge(
            item.deadlineLabel ?? '—',
            appearance: item.isExpired ? MbuiAppearance.danger : MbuiAppearance.info,
          ),
        ],
      ),
    );
  }
}

class ScholarshipDetailScreen extends ConsumerWidget {
  const ScholarshipDetailScreen({super.key, required this.slug, required this.title});
  final String slug;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(scholarshipDetailProvider(slug));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(title)),
      body: AsyncValueView<Scholarship>(
        value: async,
        onRefresh: () async => ref.refresh(scholarshipDetailProvider(slug).future),
        data: (s) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(s.title,
                style: const TextStyle(
                    fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.gray900)),
            if (s.country != null) ...[
              const SizedBox(height: 4),
              Text(s.country!, style: const TextStyle(color: AppColors.gray500)),
            ],
            const SizedBox(height: 12),
            MbuiBadge(s.deadlineLabel ?? '—',
                appearance: s.isExpired ? MbuiAppearance.danger : MbuiAppearance.info),
            const SizedBox(height: 16),
            if (s.description != null)
              Text(s.description!, style: const TextStyle(color: AppColors.gray700, height: 1.5)),
            const SizedBox(height: 24),
            if (s.applyUrl != null && !s.isExpired)
              MbuiButton(
                label: 'Apply now',
                icon: Icons.open_in_new,
                fullWidth: true,
                onPressed: () => launchUrl(Uri.parse(s.applyUrl!),
                    mode: LaunchMode.externalApplication),
              ),
          ],
        ),
      ),
    );
  }
}
