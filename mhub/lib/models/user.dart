class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.isAdmin,
    required this.status,
    this.emailVerified = false,
  });

  final int id;
  final String name;
  final String email;
  final bool isAdmin;
  final String status;
  final bool emailVerified;

  bool get isActive => status == 'active';

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      isAdmin: json['is_admin'] == true || json['is_admin'] == 1,
      status: json['status'] as String? ?? 'active',
      emailVerified: json['email_verified_at'] != null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'is_admin': isAdmin,
        'status': status,
        'email_verified_at': emailVerified ? 'verified' : null,
      };
}
