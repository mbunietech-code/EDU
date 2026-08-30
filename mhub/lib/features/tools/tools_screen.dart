import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/member_api.dart';
import '../../models/tool.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class ToolsScreen extends ConsumerWidget {
  const ToolsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(toolsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Research Tools')),
      body: AsyncValueView<List<Tool>>(
        value: async,
        onRefresh: () async => ref.refresh(toolsProvider.future),
        data: (tools) {
          if (tools.isEmpty) {
            return const Center(child: Text('No tools available yet.'));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tools.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, i) => _ToolCard(tool: tools[i]),
          );
        },
      ),
    );
  }
}

class _ToolCard extends StatelessWidget {
  const _ToolCard({required this.tool});
  final Tool tool;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => ToolDetailScreen(slug: tool.slug, name: tool.name)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: AppColors.indigo50,
              borderRadius: BorderRadius.circular(10),
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.science_outlined, color: AppColors.indigo600),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tool.name,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900)),
                if (tool.shortDescription != null) ...[
                  const SizedBox(height: 4),
                  Text(tool.shortDescription!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12.5, color: AppColors.gray500)),
                ],
                const SizedBox(height: 6),
                Text(tool.priceLabel,
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.indigo600)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AppColors.gray400),
        ],
      ),
    );
  }
}

class ToolDetailScreen extends ConsumerWidget {
  const ToolDetailScreen({super.key, required this.slug, required this.name});
  final String slug;
  final String name;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(toolDetailProvider(slug));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: Text(name)),
      body: AsyncValueView<Tool>(
        value: async,
        onRefresh: () async => ref.refresh(toolDetailProvider(slug).future),
        data: (t) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(t.name,
                style: const TextStyle(
                    fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.gray900)),
            if (t.version != null) ...[
              const SizedBox(height: 4),
              Text('Version ${t.version}',
                  style: const TextStyle(color: AppColors.gray500)),
            ],
            const SizedBox(height: 12),
            MbuiBadge(t.priceLabel, appearance: MbuiAppearance.neutral),
            const SizedBox(height: 16),
            if (t.description != null)
              Text(t.description!,
                  style: const TextStyle(color: AppColors.gray700, height: 1.5)),
            const SizedBox(height: 24),
            MbuiButton(
              label: 'How to get this tool',
              icon: Icons.info_outline,
              variant: MbuiVariant.secondary,
              fullWidth: true,
              onPressed: () => showDialog(
                context: context,
                builder: (_) => AlertDialog(
                  title: const Text('Order on the web'),
                  content: const Text(
                      'Ordering research tools is available on the MbunieEduHub website for now.'),
                  actions: [
                    TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('OK')),
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
