import 'package:flutter/material.dart';

import '../features/dashboard/dashboard_repository.dart';
import '../theme/tokens.dart';
import 'mbui/mbui.dart';

/// Grid of headline metric cards, styled like the site's dashboard tiles.
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.stats});

  final List<StatCard> stats;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = (constraints.maxWidth ~/ 260).clamp(1, 4);
        return GridView.count(
          crossAxisCount: columns,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: columns == 1 ? 3.6 : 1.9,
          children: [for (final s in stats) _StatTile(stat: s)],
        );
      },
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.stat});

  final StatCard stat;

  @override
  Widget build(BuildContext context) {
    return MbuiCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            stat.value,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: AppColors.gray900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            stat.label,
            style: const TextStyle(fontSize: 13, color: AppColors.gray500),
          ),
          if (stat.hint != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(stat.hint!,
                  style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
            ),
        ],
      ),
    );
  }
}
