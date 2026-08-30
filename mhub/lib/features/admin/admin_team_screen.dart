import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_team_api.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_form_kit.dart';

class AdminTeamScreen extends ConsumerWidget {
  const AdminTeamScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(adminTeamProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Team')),
      floatingActionButton: async.asData == null
          ? null
          : FloatingActionButton.extended(
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => TeamMemberFormScreen(data: async.asData!.value),
              )),
              icon: const Icon(Icons.person_add_alt),
              label: const Text('Add admin'),
            ),
      body: AsyncValueView<TeamData>(
        value: async,
        onRefresh: () async => ref.refresh(adminTeamProvider.future),
        data: (data) => ListView.builder(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
          itemCount: data.members.length,
          itemBuilder: (context, i) {
            final m = data.members[i];
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: MbuiCard(
                onTap: m.isSelf
                    ? null
                    : () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) =>
                              TeamMemberFormScreen(data: data, member: m),
                        )),
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 20,
                      backgroundColor: AppColors.indigo50,
                      child: Text(
                        m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
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
                                child: Text(m.name,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.gray900)),
                              ),
                              if (m.isSelf) ...[
                                const SizedBox(width: 6),
                                const MbuiBadge('You',
                                    appearance: MbuiAppearance.neutral),
                              ],
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(m.email,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500)),
                          const SizedBox(height: 4),
                          Text(
                            m.isSuperAdmin
                                ? 'Super admin · full access'
                                : '${m.permissions.length} permissions',
                            style: const TextStyle(
                                fontSize: 11, color: AppColors.gray400),
                          ),
                        ],
                      ),
                    ),
                    MbuiBadge(
                      m.roleLabel,
                      appearance: m.isSuperAdmin
                          ? MbuiAppearance.info
                          : MbuiAppearance.neutral,
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class TeamMemberFormScreen extends ConsumerStatefulWidget {
  const TeamMemberFormScreen({super.key, required this.data, this.member});
  final TeamData data;
  final TeamMember? member;

  @override
  ConsumerState<TeamMemberFormScreen> createState() =>
      _TeamMemberFormScreenState();
}

class _TeamMemberFormScreenState extends ConsumerState<TeamMemberFormScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirm = TextEditingController();
  late String _role;
  late Set<String> _perms;
  bool _saving = false;

  TeamMember? get m => widget.member;
  bool get isNew => m == null;

  @override
  void initState() {
    super.initState();
    _role = m?.role ?? 'admin';
    _perms = {...(m?.permissions ?? const [])};
  }

  @override
  void dispose() {
    for (final c in [_name, _email, _password, _passwordConfirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminTeamRepositoryProvider);
      if (isNew) {
        await repo.create({
          'name': _name.text.trim(),
          'email': _email.text.trim(),
          'password': _password.text,
          'password_confirmation': _passwordConfirm.text,
          'role': _role,
          'permissions': _role == 'super_admin' ? [] : _perms.toList(),
        });
      } else {
        await repo.update(
            m!.id, _role, _role == 'super_admin' ? [] : _perms.toList());
      }
      ref.invalidate(adminTeamProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(isNew ? 'Admin added.' : 'Saved.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _revoke() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Revoke admin access?'),
        content: Text('${m!.name} becomes a normal member.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.red600),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Revoke'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _saving = true);
    try {
      await ref.read(adminTeamRepositoryProvider).revoke(m!.id);
      ref.invalidate(adminTeamProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Admin access revoked.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final superAdmin = _role == 'super_admin';
    return AdminFormScaffold(
      title: isNew ? 'Add admin' : m!.name,
      saveLabel: isNew ? 'Create admin' : 'Save permissions',
      saving: _saving,
      onSave: _save,
      onDelete: isNew ? null : _revoke,
      children: [
        if (isNew) ...[
          LabeledInput(label: 'Name', child: TextField(controller: _name)),
          LabeledInput(
            label: 'Email',
            child: TextField(
                controller: _email,
                keyboardType: TextInputType.emailAddress),
          ),
          LabeledInput(
            label: 'Password',
            child: TextField(controller: _password, obscureText: true),
          ),
          LabeledInput(
            label: 'Confirm password',
            child:
                TextField(controller: _passwordConfirm, obscureText: true),
          ),
        ],
        LabeledInput(
          label: 'Role',
          child: StatusDropdown(
            value: _role,
            options: widget.data.roles.keys.toList(),
            onChanged: (v) => setState(() => _role = v),
          ),
        ),
        if (superAdmin)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Text('Super admins have every permission automatically.',
                style: TextStyle(fontSize: 12, color: AppColors.gray500)),
          )
        else
          for (final group in widget.data.groups) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: MbuiSectionLabel(group.group),
            ),
            for (final perm in group.permissions)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(perm.label, style: const TextStyle(fontSize: 13)),
                value: _perms.contains(perm.key),
                onChanged: (v) => setState(() {
                  if (v == true) {
                    _perms.add(perm.key);
                  } else {
                    _perms.remove(perm.key);
                  }
                }),
              ),
          ],
      ],
    );
  }
}
