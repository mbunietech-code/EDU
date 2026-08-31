import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../theme/tokens.dart';
import '../admin/admin_catalogue_screen.dart';
import '../admin/admin_chat_screen.dart';
import '../admin/admin_finance_screen.dart';
import '../admin/admin_orders_screen.dart';
import '../admin/admin_payments_screen.dart';
import '../admin/admin_reports_screen.dart';
import '../admin/admin_system_screen.dart';
import '../admin/admin_users_screen.dart';
import '../auth/auth_controller.dart';
import '../chat/chat_screen.dart';
import '../dashboard/admin_dashboard_screen.dart';
import '../dashboard/user_dashboard_screen.dart';
import '../notifications/notifications_screen.dart';
import '../orders/orders_screen.dart';
import '../payments/payments_screen.dart';
import '../products/products_screen.dart';
import '../profile/profile_screen.dart';
import '../research/my_research_screen.dart';
import '../research/research_screen.dart';
import '../scholarships/scholarships_screen.dart';
import '../subscriptions/subscriptions_screen.dart';
import '../tools/tools_screen.dart';

class _Destination {
  const _Destination(this.label, this.icon, this.screen, {this.primary = false});
  final String label;
  final IconData icon;
  final Widget screen;
  final bool primary;
}

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  List<_Destination> _destinationsFor({
    required bool isAdmin,
    bool canWriteResearch = false,
  }) {
    if (isAdmin) {
      return const [
        _Destination('Overview', Icons.dashboard_outlined, AdminDashboardScreen(), primary: true),
        _Destination('Payments', Icons.payments_outlined, AdminPaymentsScreen(), primary: true),
        _Destination('Orders', Icons.receipt_long_outlined, AdminOrdersScreen(), primary: true),
        _Destination('Messages', Icons.chat_bubble_outline, AdminChatScreen(), primary: true),
        _Destination('Catalogue', Icons.inventory_2_outlined, AdminCatalogueScreen()),
        _Destination('Users', Icons.group_outlined, AdminUsersScreen()),
        _Destination('Reports', Icons.insights_outlined, AdminReportsScreen()),
        _Destination('Finance', Icons.account_balance_outlined, AdminFinanceScreen()),
        _Destination('System', Icons.tune, AdminSystemScreen()),
        _Destination('Notifications', Icons.notifications_none, NotificationsScreen()),
        _Destination('Profile', Icons.person_outline, ProfileScreen()),
      ];
    }
    return [
      const _Destination('Home', Icons.home_outlined, UserDashboardScreen(), primary: true),
      const _Destination('AI Tools', Icons.smart_toy_outlined, ProductsScreen(), primary: true),
      const _Destination('My Orders', Icons.shopping_bag_outlined, OrdersScreen(), primary: true),
      const _Destination('Messages', Icons.chat_bubble_outline, ChatScreen(), primary: true),
      const _Destination('Research', Icons.menu_book_outlined, ResearchScreen()),
      if (canWriteResearch)
        const _Destination('My Research', Icons.edit_note, MyResearchScreen()),
      const _Destination('Payments', Icons.payments_outlined, PaymentsScreen()),
      const _Destination('Subscriptions', Icons.autorenew, SubscriptionsScreen()),
      const _Destination('Research Tools', Icons.science_outlined, ToolsScreen()),
      const _Destination('Scholarships', Icons.school_outlined, ScholarshipsScreen()),
      const _Destination('Notifications', Icons.notifications_none, NotificationsScreen()),
      const _Destination('Profile', Icons.person_outline, ProfileScreen()),
    ];
  }

  void _openMore(List<_Destination> all) {
    final extra = <int>[
      for (var i = 0; i < all.length; i++)
        if (!all[i].primary) i,
    ];
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final i in extra)
              ListTile(
                leading: Icon(all[i].icon),
                title: Text(all[i].label),
                selected: i == _index,
                onTap: () {
                  Navigator.pop(context);
                  setState(() => _index = i);
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;
    final isAdmin = user?.isAdmin ?? false;
    final all = _destinationsFor(
      isAdmin: isAdmin,
      canWriteResearch: user?.canWriteResearch ?? false,
    );
    final safeIndex = _index.clamp(0, all.length - 1);

    final body = IndexedStack(
      index: safeIndex,
      children: [for (final d in all) d.screen],
    );
    final wide = MediaQuery.sizeOf(context).width >= 800;

    if (wide) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: safeIndex,
              onDestinationSelected: (i) => setState(() => _index = i),
              labelType: NavigationRailLabelType.all,
              leading: const Padding(
                padding: EdgeInsets.symmetric(vertical: 14),
                child: _RailBadge(),
              ),
              destinations: [
                for (final d in all)
                  NavigationRailDestination(icon: Icon(d.icon), label: Text(d.label)),
              ],
            ),
            const VerticalDivider(width: 1),
            Expanded(child: body),
          ],
        ),
      );
    }

    // Mobile: bottom bar with the primary destinations + a "More" entry.
    final primary = <int>[
      for (var i = 0; i < all.length; i++)
        if (all[i].primary) i,
    ];
    final onMore = !primary.contains(safeIndex);
    final selectedBar = onMore ? primary.length : primary.indexOf(safeIndex);

    return Scaffold(
      body: body,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedBar,
        onDestinationSelected: (i) {
          if (i == primary.length) {
            _openMore(all);
          } else {
            setState(() => _index = primary[i]);
          }
        },
        destinations: [
          for (final i in primary)
            NavigationDestination(icon: Icon(all[i].icon), label: all[i].label),
          NavigationDestination(
            icon: Icon(onMore ? Icons.more_horiz : Icons.more_horiz_outlined),
            label: 'More',
          ),
        ],
      ),
    );
  }
}

class _RailBadge extends StatelessWidget {
  const _RailBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        color: AppColors.indigo600,
      ),
      alignment: Alignment.center,
      child: const Text('M',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 20)),
    );
  }
}
