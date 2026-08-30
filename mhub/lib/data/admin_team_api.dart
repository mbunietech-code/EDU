import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';

class TeamMember {
  const TeamMember({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.roleLabel,
    required this.isSuperAdmin,
    required this.permissions,
    required this.isSelf,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final String roleLabel;
  final bool isSuperAdmin;
  final List<String> permissions;
  final bool isSelf;

  factory TeamMember.fromJson(Map<String, dynamic> j) => TeamMember(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? '',
        email: j['email'] as String? ?? '',
        role: j['role'] as String? ?? 'admin',
        roleLabel: j['role_label'] as String? ?? '',
        isSuperAdmin: j['is_super_admin'] == true,
        permissions:
            (j['permissions'] as List?)?.map((e) => e.toString()).toList() ??
                const [],
        isSelf: j['is_self'] == true,
      );
}

class PermissionOption {
  const PermissionOption(this.key, this.label);
  final String key;
  final String label;
}

class PermissionGroup {
  const PermissionGroup(this.group, this.permissions);
  final String group;
  final List<PermissionOption> permissions;
}

class TeamData {
  const TeamData(this.members, this.groups, this.roles);
  final List<TeamMember> members;
  final List<PermissionGroup> groups;
  final Map<String, String> roles;
}

class AdminTeamRepository {
  AdminTeamRepository(this._api);
  final ApiClient _api;

  Future<TeamData> team() async {
    final res = await _api.get('/admin/team') as Map<String, dynamic>;
    final meta = res['meta'] as Map<String, dynamic>;
    return TeamData(
      (res['data'] as List)
          .map((e) => TeamMember.fromJson(e as Map<String, dynamic>))
          .toList(),
      (meta['groups'] as List).map((g) {
        final gm = g as Map<String, dynamic>;
        return PermissionGroup(
          gm['group'] as String,
          (gm['permissions'] as List)
              .map((p) => PermissionOption(
                  (p as Map<String, dynamic>)['key'] as String,
                  p['label'] as String))
              .toList(),
        );
      }).toList(),
      (meta['roles'] as Map).map((k, v) => MapEntry(k.toString(), v.toString())),
    );
  }

  Future<void> create(Map<String, dynamic> body) =>
      _api.post('/admin/team', data: body);

  Future<void> update(int id, String role, List<String> permissions) =>
      _api.put('/admin/team/$id',
          data: {'role': role, 'permissions': permissions});

  Future<void> revoke(int id) => _api.delete('/admin/team/$id');
}

final adminTeamRepositoryProvider =
    Provider((ref) => AdminTeamRepository(ref.watch(apiClientProvider)));

final adminTeamProvider = FutureProvider.autoDispose<TeamData>(
    (ref) => ref.watch(adminTeamRepositoryProvider).team());
