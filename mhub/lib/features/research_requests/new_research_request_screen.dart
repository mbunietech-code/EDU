import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../models/research_request.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_requests_repository.dart';

/// Ask the team for research work: type, title, level, a detailed
/// description, deadline, length, citation style, budget, priority and
/// attachments. Pops with the created request.
class NewResearchRequestScreen extends ConsumerStatefulWidget {
  const NewResearchRequestScreen({super.key, this.presetType});

  final String? presetType;

  @override
  ConsumerState<NewResearchRequestScreen> createState() => _NewResearchRequestScreenState();
}

class _NewResearchRequestScreenState extends ConsumerState<NewResearchRequestScreen> {
  final _form = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _field = TextEditingController();
  final _purpose = TextEditingController();
  final _description = TextEditingController();
  final _pages = TextEditingController();
  final _budget = TextEditingController();

  String? _type;
  String? _level;
  String? _citation;
  String _priority = 'normal';
  String _currency = 'TZS';
  DateTime? _deadline;
  final List<PlatformFile> _files = [];
  bool _sending = false;
  Map<String, List<String>> _errors = {};

  @override
  void initState() {
    super.initState();
    _type = widget.presetType;
    _description.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    for (final c in [_title, _field, _purpose, _description, _pages, _budget]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDeadline() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _deadline ?? now.add(const Duration(days: 14)),
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 365 * 3)),
    );
    if (picked != null) setState(() => _deadline = picked);
  }

  Future<void> _pickFiles(RrOptions o) async {
    final picked = await FilePicker.pickFiles(
      allowMultiple: true,
      type: o.extensions.isEmpty ? FileType.any : FileType.custom,
      allowedExtensions: o.extensions.isEmpty ? null : o.extensions,
    );
    if (picked == null) return;
    setState(() {
      for (final f in picked.files) {
        if (f.path != null && _files.length < (o.maxFiles == 0 ? 8 : o.maxFiles)) _files.add(f);
      }
    });
  }

  Future<void> _submit() async {
    setState(() => _errors = {});
    if (!_form.currentState!.validate()) return;
    if (_type == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Choose the kind of work.')));
      return;
    }
    setState(() => _sending = true);
    try {
      final created = await ref.read(researchRequestsRepositoryProvider).create({
        'title': _title.text.trim(),
        'research_type': _type,
        'field_of_study': _field.text.trim(),
        'description': _description.text.trim(),
        'academic_level': _level,
        'purpose': _purpose.text.trim(),
        'deadline': _deadline == null ? null : DateFormat('yyyy-MM-dd').format(_deadline!),
        'pages': _pages.text.trim(),
        'citation_style': _citation,
        'budget': _budget.text.trim(),
        'currency': _currency,
        'priority': _priority,
      }, _files.map((f) => f.path!).toList());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Request ${created.reference} sent.')));
      Navigator.of(context).pop(created);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _errors = e.errors ?? {};
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  String? _err(String key) {
    final direct = _errors[key]?.first;
    if (direct != null) return direct;
    for (final e in _errors.entries) {
      if (e.key.startsWith('$key.')) return e.value.first;
    }
    return null;
  }

  InputDecoration _dec(String label, {String? hint, String? error}) => InputDecoration(
        labelText: label,
        hintText: hint,
        errorText: error,
        border: const OutlineInputBorder(),
        isDense: true,
      );

  @override
  Widget build(BuildContext context) {
    final options = ref.watch(researchRequestOptionsProvider);

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('New research request')),
      body: AsyncValueView<RrOptions>(
        value: options,
        onRefresh: () async => ref.refresh(researchRequestOptionsProvider.future),
        data: (o) => Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _section('1', 'What kind of work?'),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final t in o.types)
                    ChoiceChip(
                      label: Text(t.label),
                      selected: _type == t.value,
                      showCheckmark: false,
                      selectedColor: AppColors.indigo600,
                      labelStyle: TextStyle(color: _type == t.value ? Colors.white : AppColors.gray800),
                      onSelected: (_) => setState(() => _type = t.value),
                    ),
                ],
              ),
              if (_err('research_type') != null) _errorText(_err('research_type')!),
              const SizedBox(height: 16),
              TextFormField(
                controller: _title,
                maxLength: 200,
                decoration: _dec('Title / topic', hint: 'e.g. Effect of mobile money on small businesses', error: _err('title')),
                validator: (v) => (v ?? '').trim().length < 5 ? 'Give a title of at least 5 characters.' : null,
              ),
              const SizedBox(height: 8),
              TextFormField(controller: _field, maxLength: 120, decoration: _dec('Field / subject area', hint: 'e.g. Economics')),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                initialValue: _level,
                isExpanded: true,
                decoration: _dec('Academic level', error: _err('academic_level')),
                items: [for (final l in o.levels) DropdownMenuItem(value: l.value, child: Text(l.label))],
                onChanged: (v) => setState(() => _level = v),
                validator: (v) => v == null ? 'Choose the level.' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(controller: _purpose, maxLength: 200, decoration: _dec('Purpose (optional)', hint: 'e.g. Final year project, journal submission')),
              const SizedBox(height: 16),
              _section('2', 'Describe the work'),
              const SizedBox(height: 10),
              TextFormField(
                controller: _description,
                minLines: 6,
                maxLines: 14,
                maxLength: 20000,
                decoration: _dec(
                  'Detailed description',
                  hint: 'Objectives, chapters needed, methods / software, data you have, supervisor instructions…',
                  error: _err('description'),
                ).copyWith(
                  alignLabelWithHint: true,
                  helperText: _description.text.trim().length < 30 ? '${30 - _description.text.trim().length} more characters needed' : null,
                ),
                validator: (v) => (v ?? '').trim().length < 30 ? 'Describe the work in at least 30 characters.' : null,
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => _pickFiles(o),
                icon: const Icon(Icons.attach_file),
                label: Text(_files.isEmpty ? 'Attach files (optional)' : 'Add more files'),
              ),
              Text('Up to ${o.maxFiles} files, ${o.maxMb} MB each', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
              if (_err('files') != null) _errorText(_err('files')!),
              for (final f in _files)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.insert_drive_file_outlined),
                  title: Text(f.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                  subtitle: Text('${(f.size / 1024).ceil()} KB'),
                  trailing: IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() => _files.remove(f))),
                ),
              const SizedBox(height: 16),
              _section('3', 'Requirements'),
              const SizedBox(height: 10),
              InkWell(
                onTap: _pickDeadline,
                child: InputDecorator(
                  decoration: _dec('Deadline', error: _err('deadline')).copyWith(
                    suffixIcon: _deadline == null
                        ? const Icon(Icons.event)
                        : IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() => _deadline = null)),
                  ),
                  child: Text(_deadline == null ? 'No deadline' : DateFormat('EEE, d MMM yyyy').format(_deadline!)),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _pages,
                      keyboardType: TextInputType.number,
                      decoration: _dec('Pages', hint: 'e.g. 25', error: _err('pages')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _citation,
                      isExpanded: true,
                      decoration: _dec('Citation style'),
                      items: [
                        const DropdownMenuItem(value: null, child: Text('Not sure')),
                        for (final c in o.citationStyles) DropdownMenuItem(value: c.value, child: Text(c.label)),
                      ],
                      onChanged: (v) => setState(() => _citation = v),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  SizedBox(
                    width: 100,
                    child: DropdownButtonFormField<String>(
                      initialValue: _currency,
                      decoration: _dec('Currency'),
                      items: [for (final c in o.currencies) DropdownMenuItem(value: c, child: Text(c))],
                      onChanged: (v) => setState(() => _currency = v ?? 'TZS'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _budget,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: _dec('Budget (optional)', error: _err('budget')),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text('Priority', style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.gray700)),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [
                  for (final p in o.priorities)
                    ChoiceChip(
                      label: Text(p.label),
                      selected: _priority == p.value,
                      showCheckmark: false,
                      selectedColor: p.value == 'urgent'
                          ? AppColors.red600
                          : (p.value == 'high' ? const Color(0xFFD97706) : AppColors.indigo600),
                      labelStyle: TextStyle(color: _priority == p.value ? Colors.white : AppColors.gray800),
                      onSelected: (_) => setState(() => _priority = p.value),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              if (o.stages.isNotEmpty)
                MbuiCard(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const MbuiSectionLabel('How it works'),
                      const SizedBox(height: 8),
                      for (final s in o.stages)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 3),
                          child: Row(children: [
                            CircleAvatar(radius: 5, backgroundColor: s.color),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text.rich(TextSpan(children: [
                                TextSpan(text: s.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                                if (s.description != null) TextSpan(text: ' — ${s.description}', style: const TextStyle(color: AppColors.gray500)),
                              ]), style: const TextStyle(fontSize: 13)),
                            ),
                          ]),
                        ),
                    ],
                  ),
                ),
              const SizedBox(height: 20),
              MbuiButton(label: 'Send request', icon: Icons.send, loading: _sending, fullWidth: true, onPressed: _sending ? null : _submit),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _section(String n, String title) => Row(children: [
        CircleAvatar(radius: 13, backgroundColor: AppColors.indigo600, child: Text(n, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold))),
        const SizedBox(width: 10),
        Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.gray900)),
      ]);

  Widget _errorText(String t) => Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Text(t, style: const TextStyle(color: AppColors.red600, fontSize: 12)),
      );
}
