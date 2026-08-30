import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_api.dart';
import '../../models/admin.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

class AdminUsersScreen extends ConsumerStatefulWidget {
  const AdminUsersScreen({super.key});

  @override
  ConsumerState<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends ConsumerState<AdminUsersScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      ref.read(adminUsersSearchProvider.notifier).state = value.trim();
    });
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminUsersProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Users')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              onChanged: _onSearch,
              decoration: const InputDecoration(
                hintText: 'Search name or email',
                prefixIcon: Icon(Icons.search, size: 20),
              ),
            ),
          ),
          Expanded(
            child: AsyncValueView<List<AdminUser>>(
              value: async,
              onRefresh: () async => ref.refresh(adminUsersProvider.future),
              data: (users) {
                if (users.isEmpty) {
                  return const Center(
                    child: Text('No users found.',
                        style: TextStyle(color: AppColors.gray500)),
                  );
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: users.length,
                  itemBuilder: (context, i) {
                    final u = users[i];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: MbuiCard(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => AdminUserDetailScreen(userId: u.id),
                          ),
                        ),
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 20,
                              backgroundColor: AppColors.indigo50,
                              child: Text(
                                u.name.isNotEmpty ? u.name[0].toUpperCase() : '?',
                                style: const TextStyle(
                                    color: AppColors.indigo700,
                                    fontWeight: FontWeight.w700),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Flexible(
                                        child: Text(u.name,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w700,
                                                color: AppColors.gray900)),
                                      ),
                                      if (u.isAdmin) ...[
                                        const SizedBox(width: 6),
                                        const MbuiBadge('Admin',
                                            appearance: MbuiAppearance.info),
                                      ],
                                    ],
                                  ),
                                  const SizedBox(height: 2),
                                  Text(u.email,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                          fontSize: 12,
                                          color: AppColors.gray500)),
                                ],
                              ),
                            ),
                            if (u.isSuspended)
                              const MbuiStatusBadge('suspended'),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class AdminUserDetailScreen extends ConsumerStatefulWidget {
  const AdminUserDetailScreen({super.key, required this.userId});
  final int userId;

  @override
  ConsumerState<AdminUserDetailScreen> createState() =>
      _AdminUserDetailScreenState();
}

class _AdminUserDetailScreenState extends ConsumerState<AdminUserDetailScreen> {
  bool _busy = false;

  Future<void> _toggleStatus(AdminUser u) async {
    final target = u.isSuspended ? 'active' : 'suspended';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(target == 'suspended' ? 'Suspend user?' : 'Reactivate user?'),
        content: Text(target == 'suspended'
            ? '${u.name} will not be able to sign in.'
            : '${u.name} will be able to sign in again.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(target == 'suspended' ? 'Suspend' : 'Reactivate'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _busy = true);
    try {
      await ref
          .read(adminRepositoryProvider)
          .setUserStatus(widget.userId, target);
      ref.invalidate(adminUserProvider(widget.userId));
      ref.invalidate(adminUsersProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('User updated.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(adminUserProvider(widget.userId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('User')),
      body: AsyncValueView<AdminUser>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminUserProvider(widget.userId).future),
        data: (u) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            MbuiCard(
              child: Column(
                children: [
                  AdminRow('Name', u.name),
                  AdminRow('Email', u.email),
                  AdminRow('Role', u.role ?? (u.isAdmin ? 'Admin' : 'Member')),
                  AdminRow('Status', '',
                      valueWidget: MbuiStatusBadge(u.status)),
                  AdminRow('Orders', '${u.ordersCount}'),
                  AdminRow('Active subs', '${u.activeSubscriptionsCount}'),
                ],
              ),
            ),
            if (u.subscriptions.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Subscriptions'),
              const SizedBox(height: 8),
              for (final s in u.subscriptions)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            s.endsAt == null
                                ? s.product
                                : '${s.product} · ends ${s.endsAt}',
                            style: const TextStyle(fontSize: 13),
                          ),
                        ),
                        MbuiStatusBadge(s.status),
                      ],
                    ),
                  ),
                ),
            ],
            if (u.orders.isNotEmpty) ...[
              const SizedBox(height: 16),
              const MbuiSectionLabel('Recent orders'),
              const SizedBox(height: 8),
              for (final o in u.orders)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: MbuiCard(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text('${o.title} · ${o.orderNumber}',
                              style: const TextStyle(fontSize: 13)),
                        ),
                        MbuiStatusBadge(o.status),
                      ],
                    ),
                  ),
                ),
            ],
            if (!u.isAdmin) ...[
              const SizedBox(height: 24),
              MbuiButton(
                label: u.isSuspended ? 'Reactivate user' : 'Suspend user',
                variant: u.isSuspended
                    ? MbuiVariant.success
                    : MbuiVariant.danger,
                icon: u.isSuspended ? Icons.lock_open : Icons.block,
                fullWidth: true,
                loading: _busy,
                onPressed: () => _toggleStatus(u),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
