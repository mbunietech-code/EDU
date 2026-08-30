import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/auth_controller.dart';
import '../dashboard/admin_dashboard_screen.dart';
import '../dashboard/user_dashboard_screen.dart';
import '../misc/placeholder_screen.dart';
import '../orders/orders_screen.dart';
import '../products/products_screen.dart';
import '../profile/profile_screen.dart';

class _Destination {
  const _Destination(this.label, this.icon, this.screen);
  final String label;
  final IconData icon;
  final Widget screen;
}

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  List<_Destination> _destinationsFor({required bool isAdmin}) {
    if (isAdmin) {
      return const [
        _Destination('Overview', Icons.dashboard_outlined, AdminDashboardScreen()),
        _Destination('Orders', Icons.receipt_long_outlined,
            PlaceholderScreen(title: 'Orders', icon: Icons.receipt_long)),
        _Destination('Payments', Icons.payments_outlined,
            PlaceholderScreen(title: 'Payments', icon: Icons.payments)),
        _Destination('Users', Icons.group_outlined,
            PlaceholderScreen(title: 'Users', icon: Icons.group)),
        _Destination('Messages', Icons.chat_bubble_outline,
            PlaceholderScreen(title: 'Messages', icon: Icons.chat_bubble)),
        _Destination('Profile', Icons.person_outline, ProfileScreen()),
      ];
    }
    return const [
      _Destination('Home', Icons.home_outlined, UserDashboardScreen()),
      _Destination('AI Tools', Icons.smart_toy_outlined, ProductsScreen()),
      _Destination('My Orders', Icons.shopping_bag_outlined, OrdersScreen()),
      _Destination('Messages', Icons.chat_bubble_outline,
          PlaceholderScreen(title: 'Messages', icon: Icons.chat_bubble)),
      _Destination('Profile', Icons.person_outline, ProfileScreen()),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;
    final isAdmin = user?.isAdmin ?? false;
    final destinations = _destinationsFor(isAdmin: isAdmin);
    final safeIndex = _index.clamp(0, destinations.length - 1);

    final body = IndexedStack(
      index: safeIndex,
      children: [for (final d in destinations) d.screen],
    );
    final wide = MediaQuery.sizeOf(context).width >= 720;

    if (wide) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: safeIndex,
              onDestinationSelected: (i) => setState(() => _index = i),
              labelType: NavigationRailLabelType.all,
              leading: const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: _RailBadge(),
              ),
              destinations: [
                for (final d in destinations)
                  NavigationRailDestination(
                    icon: Icon(d.icon),
                    label: Text(d.label),
                  ),
              ],
            ),
            const VerticalDivider(width: 1),
            Expanded(child: body),
          ],
        ),
      );
    }

    return Scaffold(
      body: body,
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeIndex,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          for (final d in destinations)
            NavigationDestination(icon: Icon(d.icon), label: d.label),
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
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        gradient: const LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Color(0xFF0B2A5B), Color(0xFF2C9CFF)],
        ),
      ),
      alignment: Alignment.center,
      child: const Text('M',
          style: TextStyle(
              color: Colors.white, fontWeight: FontWeight.w900, fontSize: 22)),
    );
  }
}
