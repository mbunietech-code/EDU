import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/app_update.dart';
import '../../core/config.dart';
import '../../models/user.dart';
import '../../widgets/brand_logo.dart';

import '../learning/learning_home_screen.dart';
import '../../theme/tokens.dart';
import '../admin/admin_catalogue_screen.dart';
import '../admin/admin_chat_screen.dart';
import '../admin/admin_finance_screen.dart';
import '../admin/admin_orders_screen.dart';
import '../admin/admin_payments_screen.dart';
import '../admin/admin_reports_screen.dart';
import '../admin/admin_learning_screen.dart';
import '../admin/admin_research_screen.dart';
import '../admin/admin_system_screen.dart';
import '../admin/admin_users_screen.dart';
import '../admin/admin_assets_screen.dart';
import '../admin/admin_notes_screen.dart';
import '../admin/admin_tasks_screen.dart';
import '../admin/admin_vpn_screen.dart';
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
import '../support/help_screen.dart';
import '../admin/ai_assistant_screen.dart';
import '../admin/team_chat_screen.dart';
import '../studio/studio_screen.dart';

/// Sidebar groups, in the same order as the website's admin sidebar.
abstract final class _Sec {
  static const company = 'Company';
  static const management = 'Management';
  static const learning = 'Learning';
  static const operations = 'Operations';
  static const insights = 'Insights';
  static const system = 'System';
  static const account = 'Account';
}

class _Destination {
  const _Destination(
    this.label,
    this.icon,
    this.screen, {
    this.primary = false,
    this.section,
  });
  final String label;
  final IconData icon;
  final Widget screen;
  final bool primary;

  /// Sidebar group; null = shown at the top level.
  final String? section;
}

/// Width from which the app uses the website layout (dark sidebar).
const double _desktopBreakpoint = 900;

/// Which sidebar / bottom-bar page is open.
final shellIndexProvider = StateProvider<int>((ref) => 0);

/// True on a PC-sized window. Uses the real window size (not MediaQuery,
/// which the desktop frame narrows for the page next to the sidebar).
bool useDesktopLayout(BuildContext context) {
  final view = View.of(context);
  return view.physicalSize.width / view.devicePixelRatio >= _desktopBreakpoint;
}

List<_Destination> _destinationsFor(AppUser? user) {
  final isAdmin = user?.isAdmin ?? false;
  final canWriteResearch = user?.canWriteResearch ?? false;
  final canAccessStudio = user?.canAccessStudio ?? false;
  {
    if (isAdmin) {
      return const [
        _Destination('Dashboard', Icons.dashboard_outlined, AdminDashboardScreen(), primary: true),
        // Company
        _Destination('Team tasks', Icons.task_alt_outlined, AdminTasksScreen(), section: _Sec.company),
        _Destination('Company assets', Icons.inventory_outlined, AdminAssetsScreen(), section: _Sec.company),
        // Management
        _Destination('Users', Icons.group_outlined, AdminUsersScreen(), section: _Sec.management),
        _Destination('Catalogue', Icons.inventory_2_outlined, AdminCatalogueScreen(), section: _Sec.management),
        _Destination('Research review', Icons.rate_review_outlined, AdminResearchScreen(), section: _Sec.management),
        // Learning
        _Destination('Learning admin', Icons.cast_for_education_outlined, AdminLearningScreen(), section: _Sec.learning),
        _Destination('Teaching Studio', Icons.video_settings_outlined, StudioScreen(), section: _Sec.learning),
        _Destination('Learning library', Icons.school_outlined, LearningHomeScreen(), section: _Sec.learning),
        // Operations
        _Destination('Orders', Icons.receipt_long_outlined, AdminOrdersScreen(), primary: true, section: _Sec.operations),
        _Destination('Payments', Icons.payments_outlined, AdminPaymentsScreen(), primary: true, section: _Sec.operations),
        _Destination('Mbunie VPN', Icons.vpn_lock_outlined, AdminVpnScreen(), section: _Sec.operations),
        _Destination('Messages', Icons.chat_bubble_outline, AdminChatScreen(), primary: true, section: _Sec.operations),
        _Destination('Team chat', Icons.forum_outlined, TeamChatScreen(), section: _Sec.operations),
        // Insights
        _Destination('Reports', Icons.insights_outlined, AdminReportsScreen(), section: _Sec.insights),
        _Destination('Notifications', Icons.notifications_none, NotificationsScreen(), section: _Sec.insights),
        // System (always open, like the website)
        _Destination('Settings', Icons.tune, AdminSystemScreen(), section: _Sec.system),
        _Destination('Finance', Icons.account_balance_outlined, AdminFinanceScreen(), section: _Sec.system),
        _Destination('AI Assistant', Icons.smart_toy_outlined, AiAssistantScreen(), section: _Sec.system),
        _Destination('Team notes', Icons.sticky_note_2_outlined, AdminNotesScreen(), section: _Sec.system),
        // Account
        _Destination('Profile', Icons.person_outline, ProfileScreen(), section: _Sec.account),
        _Destination('Help & contact', Icons.support_agent, HelpScreen(), section: _Sec.account),
      ];
    }
    // Customer: a flat list, in the website's order.
    return [
      const _Destination('Dashboard', Icons.home_outlined, UserDashboardScreen(), primary: true),
      const _Destination('AI Tools', Icons.smart_toy_outlined, ProductsScreen(), primary: true),
      const _Destination('Research Tools', Icons.science_outlined, ToolsScreen()),
      const _Destination('Scholarships', Icons.workspace_premium_outlined, ScholarshipsScreen()),
      const _Destination('Research', Icons.menu_book_outlined, ResearchScreen()),
      if (canWriteResearch) const _Destination('My Research', Icons.edit_note, MyResearchScreen()),
      const _Destination('Learning', Icons.school_outlined, LearningHomeScreen()),
      if (canAccessStudio)
        const _Destination('Teaching Studio', Icons.video_settings_outlined, StudioScreen()),
      const _Destination('My Subscriptions', Icons.autorenew, SubscriptionsScreen()),
      const _Destination('My Orders', Icons.shopping_bag_outlined, OrdersScreen(), primary: true),
      const _Destination('Payments', Icons.payments_outlined, PaymentsScreen()),
      const _Destination('Messages', Icons.chat_bubble_outline, ChatScreen(), primary: true),
      const _Destination('Notifications', Icons.notifications_none, NotificationsScreen()),
      const _Destination('Profile & settings', Icons.person_outline, ProfileScreen()),
      const _Destination('Help & contact', Icons.support_agent, HelpScreen()),
    ];
  }
}

/// Wraps the whole app navigator (MaterialApp.builder). On a PC window and
/// once signed in, it puts the website's dark sidebar on the left; every
/// page, detail page and dialog then opens to the right of it, like the
/// website, instead of covering the sidebar.
class DesktopFrame extends ConsumerWidget {
  const DesktopFrame({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    if (auth.status != AuthStatus.authenticated || !useDesktopLayout(context)) {
      return child;
    }
    final user = auth.user;
    final all = _destinationsFor(user);
    final index = ref.watch(shellIndexProvider).clamp(0, all.length - 1);
    final mq = MediaQuery.of(context);

    return Material(
      color: AppColors.gray100,
      child: Row(
        children: [
          _Sidebar(
            all: all,
            selected: index,
            isAdmin: user?.isAdmin ?? false,
            userName: user?.name ?? '',
            userEmail: user?.email ?? '',
            onSelect: (i) {
              // Close any open detail page, then switch section.
              appNavigatorKey.currentState?.popUntil((r) => r.isFirst);
              ref.read(shellIndexProvider.notifier).state = i;
            },
            onSignOut: () {
              appNavigatorKey.currentState?.popUntil((r) => r.isFirst);
              ref.read(shellIndexProvider.notifier).state = 0;
              ref.read(authControllerProvider.notifier).logout();
            },
          ),
          Expanded(
            child: MediaQuery(
              data: mq.copyWith(size: Size(mq.size.width - _Sidebar.width, mq.size.height)),
              child: Theme(
                data: Theme.of(context).copyWith(scaffoldBackgroundColor: AppColors.gray50),
                child: child,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  /// Screens are built the first time they are opened (not all at start-up)
  /// and then kept alive, so switching back is instant.
  final Set<int> _opened = {0};

  int get _index => ref.read(shellIndexProvider);

  void _select(int i) => ref.read(shellIndexProvider.notifier).state = i;

  /// Mobile: everything that is not on the bottom bar, grouped.
  void _openMore(List<_Destination> all) {
    final grouped = <String, List<int>>{};
    for (var i = 0; i < all.length; i++) {
      if (all[i].primary) continue;
      grouped.putIfAbsent(all[i].section ?? 'Menu', () => []).add(i);
    }
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (sheet) => SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(sheet).height * .85),
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            children: [
              for (final entry in grouped.entries) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 14, 4, 8),
                  child: Text(
                    entry.key.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      letterSpacing: .8,
                      color: AppColors.gray500,
                    ),
                  ),
                ),
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    border: Border.all(color: AppColors.gray200),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      for (final i in entry.value)
                        ListTile(
                          leading: Icon(all[i].icon),
                          title: Text(all[i].label),
                          trailing: i == _index
                              ? const Icon(Icons.check_circle, color: AppColors.indigo600)
                              : const Icon(Icons.chevron_right),
                          onTap: () {
                            Navigator.pop(sheet);
                            _select(i);
                          },
                        ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;
    final all = _destinationsFor(user);
    final safeIndex = ref.watch(shellIndexProvider).clamp(0, all.length - 1);
    _opened.add(safeIndex);

    final body = IndexedStack(
      index: safeIndex,
      children: [
        for (var i = 0; i < all.length; i++)
          _opened.contains(i) ? all[i].screen : const SizedBox.shrink(),
      ],
    );

    // PC: the sidebar comes from DesktopFrame around the navigator.
    if (useDesktopLayout(context)) return body;

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
            _select(primary[i]);
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

/// The website's sidebar: dark, 256 px wide, scrollable, with collapsible
/// groups and "Public site" / "Sign out" at the bottom.
class _Sidebar extends StatefulWidget {
  const _Sidebar({
    required this.all,
    required this.selected,
    required this.isAdmin,
    required this.userName,
    required this.userEmail,
    required this.onSelect,
    required this.onSignOut,
  });

  final List<_Destination> all;
  final int selected;
  final bool isAdmin;
  final String userName;
  final String userEmail;
  final ValueChanged<int> onSelect;
  final VoidCallback onSignOut;

  static const double width = 256;

  @override
  State<_Sidebar> createState() => _SidebarState();
}

class _SidebarState extends State<_Sidebar> {
  /// The one open group (like the website, opening one closes the other).
  String? _open;
  final _scroll = ScrollController();

  /// Groups that are always open and have no header.
  static const _flat = {_Sec.system, _Sec.account};

  @override
  void initState() {
    super.initState();
    _open = widget.all[widget.selected].section;
  }

  @override
  void didUpdateWidget(covariant _Sidebar old) {
    super.didUpdateWidget(old);
    if (old.selected != widget.selected && widget.selected < widget.all.length) {
      final s = widget.all[widget.selected].section;
      if (s != null && !_flat.contains(s)) _open = s;
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final children = <Widget>[];
    String? current;
    for (var i = 0; i < widget.all.length; i++) {
      final d = widget.all[i];
      final section = d.section;
      final flat = section == null || _flat.contains(section);
      if (section != current) {
        current = section;
        if (section != null) {
          children.add(const SizedBox(height: 6));
          if (flat) {
            children.add(const Padding(
              padding: EdgeInsets.symmetric(vertical: 6, horizontal: 4),
              child: Divider(height: 1, color: AppColors.gray800),
            ));
          } else {
            children.add(_GroupHeader(
              label: section,
              open: _open == section,
              onTap: () => setState(() => _open = _open == section ? null : section),
            ));
          }
        }
      }
      if (!flat && _open != section) continue;
      children.add(Padding(
        padding: EdgeInsets.only(left: flat ? 0 : 4, top: 2),
        child: _SideLink(
          label: d.label,
          icon: d.icon,
          active: i == widget.selected,
          activeColor: widget.isAdmin ? AppColors.indigo600 : AppColors.gray800,
          onTap: () => widget.onSelect(i),
        ),
      ));
    }

    return Container(
      width: _Sidebar.width,
      color: AppColors.gray900,
      child: Column(
        children: [
          // Logo row, the same height as the page header.
          Container(
            height: 64,
            padding: const EdgeInsets.symmetric(horizontal: 18),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppColors.gray800)),
            ),
            child: Row(
              children: [
                const BrandLogo(size: 34, radius: 8),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text(
                    'MbunieEduHub',
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                ),
                if (widget.isAdmin)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.gray800,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: const Text('Admin',
                        style: TextStyle(color: AppColors.gray300, fontSize: 11, fontWeight: FontWeight.w600)),
                  ),
              ],
            ),
          ),
          Expanded(
            child: ScrollConfiguration(
              behavior: ScrollConfiguration.of(context).copyWith(scrollbars: false),
              child: RawScrollbar(
                controller: _scroll,
                thumbColor: AppColors.gray700,
                radius: const Radius.circular(8),
                thickness: 6,
                child: ListView(
                  controller: _scroll,
                  padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
                  children: children,
                ),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: AppColors.gray800)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (widget.userName.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(4, 0, 4, 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(widget.userName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
                        Text(widget.userEmail,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: AppColors.gray400, fontSize: 12)),
                      ],
                    ),
                  ),
                Row(
                  children: [
                    Expanded(
                      child: _FooterButton(
                        icon: Icons.public,
                        label: 'Public site',
                        onTap: () => launchUrl(Uri.parse(AppConfig.defaultBase),
                            mode: LaunchMode.externalApplication),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: _FooterButton(icon: Icons.logout, label: 'Sign out', onTap: widget.onSignOut),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GroupHeader extends StatelessWidget {
  const _GroupHeader({required this.label, required this.open, required this.onTap});
  final String label;
  final bool open;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        hoverColor: AppColors.gray800,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  label.toUpperCase(),
                  style: const TextStyle(
                    color: AppColors.gray400,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1,
                  ),
                ),
              ),
              AnimatedRotation(
                turns: open ? .5 : 0,
                duration: const Duration(milliseconds: 150),
                child: const Icon(Icons.expand_more, size: 18, color: AppColors.gray400),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SideLink extends StatelessWidget {
  const _SideLink({
    required this.label,
    required this.icon,
    required this.active,
    required this.activeColor,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final Color activeColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = active ? Colors.white : AppColors.gray300;
    return Material(
      color: active ? activeColor : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        hoverColor: AppColors.gray800,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              Icon(icon, size: 20, color: fg),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: fg, fontSize: 14, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FooterButton extends StatelessWidget {
  const _FooterButton({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        hoverColor: AppColors.gray800,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 15, color: AppColors.gray300),
              const SizedBox(width: 6),
              Flexible(
                child: Text(label,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.gray300, fontSize: 12, fontWeight: FontWeight.w500)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
