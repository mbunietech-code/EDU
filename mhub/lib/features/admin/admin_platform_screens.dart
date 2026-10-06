import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';

// Platform settings (same rules as the web): alert channels and the AI
// database optimization agent (super admin), email (SMTP) and the Finance PIN.

Map<String, dynamic> _data(Object? body) => (body as Map<String, dynamic>)['data'] as Map<String, dynamic>;
String _msg(Object? body, [String fallback = 'Saved.']) =>
    (body is Map<String, dynamic> ? body['message'] as String? : null) ?? fallback;
String _err(ApiException e) => e.errors?.values.expand((v) => v).firstOrNull ?? e.message;

void _toast(BuildContext context, String text) {
  if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
}

final _alertsProvider = FutureProvider.autoDispose((ref) async => _data(await ref.watch(apiClientProvider).get('/admin/alerts')));
final _optimizationProvider =
    FutureProvider.autoDispose((ref) async => _data(await ref.watch(apiClientProvider).get('/admin/optimization')));
final _mailProvider = FutureProvider.autoDispose((ref) async => _data(await ref.watch(apiClientProvider).get('/admin/settings/mail')));

// --- Alerts ----------------------------------------------------------------

class AdminAlertsScreen extends ConsumerStatefulWidget {
  const AdminAlertsScreen({super.key});

  @override
  ConsumerState<AdminAlertsScreen> createState() => _AdminAlertsScreenState();
}

class _AdminAlertsScreenState extends ConsumerState<AdminAlertsScreen> {
  final _c = <String, TextEditingController>{};
  bool _filled = false;
  bool _busy = false;

  static const _labels = {
    'alert_expired_min': 'Alert when expired items reach',
    'alert_expiring_min': 'Alert when items expiring soon reach',
    'alert_storage_mb': 'Alert when the database is larger than (MB)',
    'alert_errors_min': 'Alert when open errors reach',
  };

  TextEditingController _ctl(String key) => _c.putIfAbsent(key, TextEditingController.new);

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _fill(Map<String, dynamic> d) {
    if (_filled) return;
    _filled = true;
    for (final k in ['alert_emails', 'alert_phones', 'beem_api_key', 'beem_sender_id']) {
      _ctl(k).text = '${d[k] ?? ''}';
    }
    final t = d['thresholds'] as Map<String, dynamic>? ?? const {};
    for (final k in _labels.keys) {
      _ctl(k).text = '${t[k] ?? 0}';
    }
  }

  Future<void> _run(Future<Object?> Function(ApiClient api) call) async {
    setState(() => _busy = true);
    try {
      final body = await call(ref.read(apiClientProvider));
      if (mounted) _toast(context, _msg(body));
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save() => _run((api) => api.put('/admin/alerts', data: {
        for (final k in ['alert_emails', 'alert_phones', 'beem_api_key', 'beem_sender_id']) k: _ctl(k).text.trim(),
        if (_ctl('beem_secret_key').text.trim().isNotEmpty) 'beem_secret_key': _ctl('beem_secret_key').text.trim(),
        for (final k in _labels.keys) k: int.tryParse(_ctl(k).text.trim()) ?? 0,
      }));

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_alertsProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Alerts (email & SMS)')),
      body: AsyncValueView<Map<String, dynamic>>(
        value: async,
        data: (d) {
          _fill(d);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              MbuiBadge(
                d['sms_configured'] == true ? 'SMS ready (Beem)' : 'SMS not set up',
                appearance: d['sms_configured'] == true ? MbuiAppearance.success : MbuiAppearance.warning,
              ),
              const SizedBox(height: 12),
              TextField(controller: _ctl('alert_emails'), decoration: const InputDecoration(labelText: 'Emails (comma separated)')),
              const SizedBox(height: 10),
              TextField(controller: _ctl('alert_phones'), decoration: const InputDecoration(labelText: 'Phone numbers (comma separated)')),
              const SizedBox(height: 10),
              TextField(controller: _ctl('beem_api_key'), decoration: const InputDecoration(labelText: 'Beem API key')),
              const SizedBox(height: 10),
              TextField(
                controller: _ctl('beem_secret_key'),
                obscureText: true,
                decoration: InputDecoration(
                  labelText: 'Beem secret key',
                  helperText: d['has_beem_secret'] == true ? 'Saved. Leave blank to keep it.' : null,
                ),
              ),
              const SizedBox(height: 10),
              TextField(controller: _ctl('beem_sender_id'), decoration: const InputDecoration(labelText: 'Sender ID')),
              const SizedBox(height: 16),
              const MbuiSectionLabel('When to alert'),
              for (final e in _labels.entries) ...[
                const SizedBox(height: 10),
                TextField(
                  controller: _ctl(e.key),
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(labelText: e.value),
                ),
              ],
              const SizedBox(height: 16),
              MbuiButton(label: 'Save', fullWidth: true, loading: _busy, onPressed: _busy ? null : _save),
              const SizedBox(height: 8),
              MbuiButton(
                label: 'Send a test alert',
                variant: MbuiVariant.secondary,
                fullWidth: true,
                onPressed: _busy ? null : () => _run((api) => api.post('/admin/alerts/test')),
              ),
            ],
          );
        },
      ),
    );
  }
}

// --- AI optimization ---------------------------------------------------------

class AdminOptimizationScreen extends ConsumerStatefulWidget {
  const AdminOptimizationScreen({super.key});

  @override
  ConsumerState<AdminOptimizationScreen> createState() => _AdminOptimizationScreenState();
}

class _AdminOptimizationScreenState extends ConsumerState<AdminOptimizationScreen> {
  bool _busy = false;

  Future<void> _run(String path, [Map<String, dynamic>? body]) async {
    setState(() => _busy = true);
    try {
      final res = await ref.read(apiClientProvider).post(path, data: body ?? const {});
      if (mounted) _toast(context, _msg(res));
      ref.invalidate(_optimizationProvider);
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _execute(Map<String, dynamic> r) async {
    final ok = await confirmAdminAction(
      context,
      title: 'Run on ${r['table']}?',
      message: '${r['action']}\n\nA backup of the affected rows is saved first.\n\n${r['sql_preview'] ?? ''}',
      actionLabel: 'Run',
      variant: MbuiVariant.danger,
    );
    if (ok) await _run('/admin/optimization/${r['id']}/execute');
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_optimizationProvider);
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('AI database optimization')),
      body: Column(
        children: [
          if (_busy) const LinearProgressIndicator(),
          Expanded(
            child: AsyncValueView<Map<String, dynamic>>(
              value: async,
              onRefresh: () async => ref.refresh(_optimizationProvider.future),
              data: (d) {
                final scan = d['scan'] as Map<String, dynamic>?;
                final recs = (scan?['recommendations'] as List? ?? const []).cast<Map<String, dynamic>>();
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (d['supported'] != true)
                      const MbuiCard(child: Text('The scan needs the MySQL database (it works on the live server).'))
                    else
                      MbuiButton(
                        label: 'Run a new scan',
                        icon: Icons.manage_search,
                        fullWidth: true,
                        onPressed: _busy ? null : () => _run('/admin/optimization/scan'),
                      ),
                    const SizedBox(height: 12),
                    if (scan != null)
                      MbuiCard(
                        padding: const EdgeInsets.all(14),
                        child: Text(
                          '${scan['total_tables']} tables · ${scan['total_rows']} rows · ${scan['total_size_mb']} MB\n'
                          'Could free about ${scan['estimated_recovery_mb']} MB',
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                    const SizedBox(height: 12),
                    if (scan != null && recs.isEmpty) const Text('No recommendations. The database looks healthy.'),
                    for (final r in recs)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: MbuiCard(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(children: [
                                Expanded(
                                  child: Text('${r['table']} · ${r['category']}',
                                      style: const TextStyle(fontWeight: FontWeight.w700)),
                                ),
                                MbuiBadge('${r['status']}', appearance: switch (r['status']) {
                                  'executed' => MbuiAppearance.success,
                                  'approved' => MbuiAppearance.info,
                                  'rejected' => MbuiAppearance.danger,
                                  _ => MbuiAppearance.neutral,
                                }),
                              ]),
                              const SizedBox(height: 4),
                              Text('${r['action']}', style: const TextStyle(fontSize: 13)),
                              Text(
                                '${r['affected_count']} rows · risk ${r['risk_level'] ?? '—'}'
                                '${r['executed_count'] != null ? ' · done: ${r['executed_count']} rows' : ''}',
                                style: const TextStyle(fontSize: 12, color: AppColors.gray500),
                              ),
                              if (r['can_run'] == true && (r['status'] == 'pending' || r['status'] == 'approved'))
                                Wrap(
                                  spacing: 8,
                                  children: [
                                    if (r['status'] == 'pending')
                                      TextButton(
                                        onPressed: _busy ? null : () => _run('/admin/optimization/${r['id']}/approve'),
                                        child: const Text('Approve'),
                                      ),
                                    if (r['status'] == 'approved')
                                      TextButton(onPressed: _busy ? null : () => _execute(r), child: const Text('Run now')),
                                    TextButton(
                                      onPressed: _busy
                                          ? null
                                          : () async {
                                              final reason = await promptReason(context,
                                                  title: 'Reject recommendation', actionLabel: 'Reject', required: false);
                                              if (reason != null) {
                                                await _run('/admin/optimization/${r['id']}/reject', {'rejection_reason': reason});
                                              }
                                            },
                                      child: const Text('Reject', style: TextStyle(color: AppColors.red600)),
                                    ),
                                  ],
                                )
                              else if (r['can_run'] != true)
                                const Text('Look into this one by hand (detection only).',
                                    style: TextStyle(fontSize: 12, color: AppColors.gray500)),
                            ],
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// --- Email (SMTP) ------------------------------------------------------------

class AdminMailSettingsScreen extends ConsumerStatefulWidget {
  const AdminMailSettingsScreen({super.key});

  @override
  ConsumerState<AdminMailSettingsScreen> createState() => _AdminMailSettingsScreenState();
}

class _AdminMailSettingsScreenState extends ConsumerState<AdminMailSettingsScreen> {
  final _c = <String, TextEditingController>{};
  String _mailer = 'smtp';
  String _encryption = '';
  bool _filled = false;
  bool _busy = false;

  TextEditingController _ctl(String key) => _c.putIfAbsent(key, TextEditingController.new);

  @override
  void dispose() {
    for (final c in _c.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _fill(Map<String, dynamic> d) {
    if (_filled) return;
    _filled = true;
    _mailer = d['mail_mailer'] as String? ?? 'smtp';
    _encryption = d['mail_encryption'] as String? ?? '';
    for (final k in ['mail_host', 'mail_port', 'mail_username', 'mail_from_address', 'mail_from_name']) {
      _ctl(k).text = '${d[k] ?? ''}';
    }
  }

  Future<void> _run(Future<Object?> Function(ApiClient api) call) async {
    setState(() => _busy = true);
    try {
      final body = await call(ref.read(apiClientProvider));
      if (mounted) _toast(context, _msg(body));
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Email settings')),
      body: AsyncValueView<Map<String, dynamic>>(
        value: ref.watch(_mailProvider),
        data: (d) {
          _fill(d);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DropdownButtonFormField<String>(
                initialValue: _mailer,
                decoration: const InputDecoration(labelText: 'Send with'),
                items: const [
                  DropdownMenuItem(value: 'smtp', child: Text('SMTP (real emails)')),
                  DropdownMenuItem(value: 'log', child: Text('Log only (no emails sent)')),
                ],
                onChanged: (v) => setState(() => _mailer = v ?? 'smtp'),
              ),
              const SizedBox(height: 10),
              TextField(controller: _ctl('mail_host'), decoration: const InputDecoration(labelText: 'SMTP host')),
              const SizedBox(height: 10),
              TextField(
                controller: _ctl('mail_port'),
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Port (e.g. 465 or 587)'),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _encryption,
                decoration: const InputDecoration(labelText: 'Encryption'),
                items: const [
                  DropdownMenuItem(value: '', child: Text('None')),
                  DropdownMenuItem(value: 'tls', child: Text('TLS')),
                  DropdownMenuItem(value: 'ssl', child: Text('SSL')),
                ],
                onChanged: (v) => setState(() => _encryption = v ?? ''),
              ),
              const SizedBox(height: 10),
              TextField(controller: _ctl('mail_username'), decoration: const InputDecoration(labelText: 'Username')),
              const SizedBox(height: 10),
              TextField(
                controller: _ctl('mail_password'),
                obscureText: true,
                decoration: InputDecoration(
                  labelText: 'Password',
                  helperText: d['has_password'] == true ? 'Saved. Leave blank to keep it.' : null,
                ),
              ),
              const SizedBox(height: 10),
              TextField(controller: _ctl('mail_from_address'), decoration: const InputDecoration(labelText: 'From address')),
              const SizedBox(height: 10),
              TextField(controller: _ctl('mail_from_name'), decoration: const InputDecoration(labelText: 'From name')),
              const SizedBox(height: 16),
              MbuiButton(
                label: 'Save',
                fullWidth: true,
                loading: _busy,
                onPressed: _busy
                    ? null
                    : () => _run((api) => api.put('/admin/settings/mail', data: {
                          'mail_mailer': _mailer,
                          'mail_encryption': _encryption,
                          'mail_port': int.tryParse(_ctl('mail_port').text.trim()),
                          for (final k in ['mail_host', 'mail_username', 'mail_from_address', 'mail_from_name'])
                            k: _ctl(k).text.trim(),
                          if (_ctl('mail_password').text.isNotEmpty) 'mail_password': _ctl('mail_password').text,
                        })),
              ),
              const SizedBox(height: 8),
              MbuiButton(
                label: 'Send me a test email',
                variant: MbuiVariant.secondary,
                fullWidth: true,
                onPressed: _busy ? null : () => _run((api) => api.post('/admin/settings/mail/test')),
              ),
            ],
          );
        },
      ),
    );
  }
}

// --- Finance PIN ---------------------------------------------------------------

class AdminFinancePinScreen extends ConsumerStatefulWidget {
  const AdminFinancePinScreen({super.key});

  @override
  ConsumerState<AdminFinancePinScreen> createState() => _AdminFinancePinScreenState();
}

class _AdminFinancePinScreenState extends ConsumerState<AdminFinancePinScreen> {
  final _current = TextEditingController();
  final _pin = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _current.dispose();
    _pin.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final body = await ref.read(apiClientProvider).post('/admin/settings/finance-pin', data: {
        'current_pin': _current.text.trim(),
        'pin': _pin.text.trim(),
        'pin_confirmation': _confirm.text.trim(),
      });
      if (!mounted) return;
      _toast(context, _msg(body));
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) _toast(context, _err(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  InputDecoration _dec(String label) => InputDecoration(labelText: label, counterText: '');

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: const Text('Finance PIN')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text('The 6-digit PIN unlocks Finance on the web and in the app. Only a super admin can change it.',
                style: TextStyle(color: AppColors.gray600)),
            const SizedBox(height: 16),
            TextField(
              controller: _current,
              obscureText: true,
              maxLength: 6,
              keyboardType: TextInputType.number,
              decoration: _dec('Current PIN (leave blank if none is set)'),
            ),
            const SizedBox(height: 10),
            TextField(controller: _pin, obscureText: true, maxLength: 6, keyboardType: TextInputType.number, decoration: _dec('New PIN')),
            const SizedBox(height: 10),
            TextField(
              controller: _confirm,
              obscureText: true,
              maxLength: 6,
              keyboardType: TextInputType.number,
              decoration: _dec('Repeat the new PIN'),
            ),
            const SizedBox(height: 16),
            MbuiButton(label: 'Save PIN', fullWidth: true, loading: _busy, onPressed: _busy ? null : _save),
          ],
        ),
      );
}
