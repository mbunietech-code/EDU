import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api_client.dart';
import '../../data/admin_catalogue_api.dart';
import '../../models/admin_catalogue.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'admin_common.dart';
import 'admin_form_kit.dart';

// ===========================================================================
// Product
// ===========================================================================
class ProductFormScreen extends ConsumerWidget {
  const ProductFormScreen({super.key, this.productId});
  final int? productId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (productId == null) {
      return const _ProductForm(detail: null);
    }
    final async = ref.watch(adminCatProductProvider(productId!));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Edit product')),
      body: AsyncValueView<CatProductDetail>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminCatProductProvider(productId!).future),
        data: (d) => _ProductForm(detail: d),
      ),
    );
  }
}

class _ProductForm extends ConsumerStatefulWidget {
  const _ProductForm({required this.detail});
  final CatProductDetail? detail;

  @override
  ConsumerState<_ProductForm> createState() => _ProductFormState();
}

class _ProductFormState extends ConsumerState<_ProductForm> {
  late final TextEditingController _name;
  late final TextEditingController _desc;
  late final TextEditingController _price;
  late final TextEditingController _features;
  late final TextEditingController _swVersion;
  late final TextEditingController _swKey;
  String _type = 'subscription';
  String _status = 'draft';
  bool _featured = false;
  bool _saving = false;

  CatProductDetail? get d => widget.detail;
  bool get isNew => d == null;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: d?.name ?? '');
    _desc = TextEditingController(text: d?.description ?? '');
    _price = TextEditingController(text: d != null ? _num(d!.price) : '');
    _features = TextEditingController(text: (d?.features ?? []).join('\n'));
    _swVersion = TextEditingController(text: d?.softwareVersion ?? '');
    _swKey = TextEditingController(text: d?.softwareKey ?? '');
    _type = d?.type ?? 'subscription';
    _status = d?.status ?? 'draft';
    _featured = d?.isFeatured ?? false;
  }

  @override
  void dispose() {
    for (final c in [_name, _desc, _price, _features, _swVersion, _swKey]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final body = <String, dynamic>{
      'name': _name.text.trim(),
      'description': _desc.text.trim(),
      'price': double.tryParse(_price.text.trim()) ?? 0,
      'type': _type,
      'status': _status,
      'is_featured': _featured,
      'features': _features.text
          .split('\n')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList(),
      if (_type == 'software') 'software_version': _swVersion.text.trim(),
      if (_type == 'software') 'software_key': _swKey.text.trim(),
    };

    setState(() => _saving = true);
    try {
      final repo = ref.read(adminCatalogueRepositoryProvider);
      if (isNew) {
        await repo.createProduct(body);
      } else {
        await repo.updateProduct(d!.id, body);
        ref.invalidate(adminCatProductProvider(d!.id));
      }
      ref.invalidate(adminCatProductsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(isNew ? 'Product created.' : 'Product saved.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      _err(e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete() async {
    final reason = await promptReason(context,
        title: 'Delete product', actionLabel: 'Delete', hint: 'Reason');
    if (reason == null || reason.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(adminCatalogueRepositoryProvider).deleteProduct(d!.id, reason);
      ref.invalidate(adminCatProductsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Product deleted.')));
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      _err(e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _err(ApiException e) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = ListView(
      padding: const EdgeInsets.all(16),
      children: [
        MbuiCard(
          child: Column(
            children: [
              LabeledInput(
                label: 'Name',
                child: TextField(controller: _name),
              ),
              LabeledInput(
                label: 'Description',
                child: TextField(controller: _desc, maxLines: 4),
              ),
              LabeledInput(
                label: 'Base price (TZS)',
                hint: 'Plans can override this with their own price.',
                child: TextField(
                  controller: _price,
                  keyboardType: TextInputType.number,
                ),
              ),
              LabeledInput(
                label: 'Type',
                child: StatusDropdown(
                  value: _type,
                  options: const ['subscription', 'software'],
                  onChanged: (v) => setState(() => _type = v),
                ),
              ),
              LabeledInput(
                label: 'Status',
                child: StatusDropdown(
                  value: _status,
                  onChanged: (v) => setState(() => _status = v),
                ),
              ),
              LabeledInput(
                label: 'Features (one per line)',
                child: TextField(controller: _features, maxLines: 5),
              ),
              if (_type == 'software') ...[
                LabeledInput(
                  label: 'Software version',
                  child: TextField(controller: _swVersion),
                ),
                LabeledInput(
                  label: 'Software key',
                  child: TextField(controller: _swKey),
                ),
              ],
              FeaturedSwitch(
                value: _featured,
                onChanged: (v) => setState(() => _featured = v),
              ),
              if (!isNew)
                ImageUploadField(
                  label: 'Product image',
                  currentUrl: d!.imageUrl,
                  onUpload: (path) async {
                    await ref
                        .read(adminCatalogueRepositoryProvider)
                        .uploadImage('products', d!.id, path);
                    ref.invalidate(adminCatProductProvider(d!.id));
                    ref.invalidate(adminCatProductsProvider);
                  },
                ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        MbuiButton(
          label: isNew ? 'Create product' : 'Save changes',
          loading: _saving,
          fullWidth: true,
          onPressed: _save,
        ),
        if (!isNew) ...[
          const SizedBox(height: 28),
          const MbuiSectionLabel('Plans'),
          const SizedBox(height: 8),
          for (final plan in d!.plans)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: MbuiCard(
                onTap: () => _openPlan(plan),
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(plan.name,
                              style: const TextStyle(
                                  fontSize: 13, fontWeight: FontWeight.w600)),
                          Text('${plan.priceLabel} · ${plan.durationLabel}',
                              style: const TextStyle(
                                  fontSize: 12, color: AppColors.gray500)),
                        ],
                      ),
                    ),
                    MbuiStatusBadge(plan.status),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 4),
          MbuiButton(
            label: 'Add plan',
            variant: MbuiVariant.secondary,
            icon: Icons.add,
            onPressed: () => _openPlan(null),
          ),
        ],
      ],
    );

    if (isNew) {
      return Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(title: const Text('New product')),
        body: body,
      );
    }
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: const Text('Edit product'),
        actions: [
          IconButton(
            icon: const Icon(Icons.delete_outline, color: AppColors.red600),
            onPressed: _saving ? null : _delete,
          ),
        ],
      ),
      body: body,
    );
  }

  void _openPlan(CatPlan? plan) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => Padding(
        padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: _PlanEditor(productId: d!.id, plan: plan),
      ),
    ).then((_) => ref.invalidate(adminCatProductProvider(d!.id)));
  }
}

class _PlanEditor extends ConsumerStatefulWidget {
  const _PlanEditor({required this.productId, this.plan});
  final int productId;
  final CatPlan? plan;

  @override
  ConsumerState<_PlanEditor> createState() => _PlanEditorState();
}

class _PlanEditorState extends ConsumerState<_PlanEditor> {
  late final TextEditingController _name;
  late final TextEditingController _desc;
  late final TextEditingController _price;
  late final TextEditingController _days;
  String _durationType = 'days';
  String _status = 'active';
  bool _saving = false;

  CatPlan? get p => widget.plan;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: p?.name ?? '');
    _desc = TextEditingController(text: p?.description ?? '');
    _price = TextEditingController(text: p != null ? _num(p!.price) : '');
    _days = TextEditingController(text: p?.durationDays?.toString() ?? '30');
    _durationType = p?.durationType ?? 'days';
    _status = p?.status ?? 'active';
  }

  @override
  void dispose() {
    for (final c in [_name, _desc, _price, _days]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final body = <String, dynamic>{
      'name': _name.text.trim(),
      'description': _desc.text.trim(),
      'duration_type': _durationType,
      if (_durationType == 'days')
        'duration_days': int.tryParse(_days.text.trim()) ?? 30,
      'price': double.tryParse(_price.text.trim()) ?? 0,
      'status': _status,
    };
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminCatalogueRepositoryProvider);
      if (p == null) {
        await repo.createPlan(widget.productId, body);
      } else {
        await repo.updatePlan(p!.id, body);
      }
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _delete() async {
    final reason = await promptReason(context,
        title: 'Delete plan', actionLabel: 'Delete', hint: 'Reason');
    if (reason == null || reason.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(adminCatalogueRepositoryProvider).deletePlan(p!.id, reason);
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(p == null ? 'New plan' : 'Edit plan',
              style: const TextStyle(
                  fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          LabeledInput(label: 'Name', child: TextField(controller: _name)),
          LabeledInput(
              label: 'Description',
              child: TextField(controller: _desc, maxLines: 2)),
          LabeledInput(
            label: 'Duration',
            child: StatusDropdown(
              value: _durationType,
              options: const ['days', 'lifetime'],
              onChanged: (v) => setState(() => _durationType = v),
            ),
          ),
          if (_durationType == 'days')
            LabeledInput(
              label: 'Duration (days)',
              child: TextField(
                  controller: _days, keyboardType: TextInputType.number),
            ),
          LabeledInput(
            label: 'Price (TZS)',
            child: TextField(
                controller: _price, keyboardType: TextInputType.number),
          ),
          LabeledInput(
            label: 'Status',
            child: StatusDropdown(
              value: _status,
              options: const ['active', 'inactive'],
              onChanged: (v) => setState(() => _status = v),
            ),
          ),
          const SizedBox(height: 8),
          MbuiButton(
            label: p == null ? 'Add plan' : 'Save plan',
            loading: _saving,
            fullWidth: true,
            onPressed: _save,
          ),
          if (p != null) ...[
            const SizedBox(height: 8),
            MbuiButton(
              label: 'Delete plan',
              variant: MbuiVariant.danger,
              fullWidth: true,
              onPressed: _saving ? null : _delete,
            ),
          ],
        ],
      ),
    );
  }
}

// ===========================================================================
// Tool
// ===========================================================================
class ToolFormScreen extends ConsumerWidget {
  const ToolFormScreen({super.key, this.toolId});
  final int? toolId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (toolId == null) return const _ToolForm(detail: null);
    final async = ref.watch(adminCatToolProvider(toolId!));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Edit tool')),
      body: AsyncValueView<CatToolDetail>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminCatToolProvider(toolId!).future),
        data: (d) => _ToolForm(detail: d),
      ),
    );
  }
}

class _ToolForm extends ConsumerStatefulWidget {
  const _ToolForm({required this.detail});
  final CatToolDetail? detail;

  @override
  ConsumerState<_ToolForm> createState() => _ToolFormState();
}

class _ToolFormState extends ConsumerState<_ToolForm> {
  late final TextEditingController _name;
  late final TextEditingController _desc;
  late final TextEditingController _version;
  late final TextEditingController _license;
  late final TextEditingController _price;
  String _status = 'draft';
  bool _featured = false;
  bool _saving = false;

  CatToolDetail? get d => widget.detail;
  bool get isNew => d == null;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: d?.name ?? '');
    _desc = TextEditingController(text: d?.description ?? '');
    _version = TextEditingController(text: d?.version ?? '');
    _license = TextEditingController(text: d?.licenseKey ?? '');
    _price = TextEditingController(text: d != null ? _num(d!.price) : '0');
    _status = d?.status ?? 'draft';
    _featured = d?.isFeatured ?? false;
  }

  @override
  void dispose() {
    for (final c in [_name, _desc, _version, _license, _price]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final body = {
      'name': _name.text.trim(),
      'description': _desc.text.trim(),
      'version': _version.text.trim(),
      'license_key': _license.text.trim(),
      'price': double.tryParse(_price.text.trim()) ?? 0,
      'status': _status,
      'is_featured': _featured,
    };
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminCatalogueRepositoryProvider);
      if (isNew) {
        await repo.createTool(body);
      } else {
        await repo.updateTool(d!.id, body);
        ref.invalidate(adminCatToolProvider(d!.id));
      }
      ref.invalidate(adminCatToolsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(isNew ? 'Tool created.' : 'Tool saved.')));
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

  Future<void> _delete() async {
    final reason = await promptReason(context,
        title: 'Delete tool', actionLabel: 'Delete', hint: 'Reason');
    if (reason == null || reason.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref.read(adminCatalogueRepositoryProvider).deleteTool(d!.id, reason);
      ref.invalidate(adminCatToolsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Tool deleted.')));
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
    return AdminFormScaffold(
      title: isNew ? 'New tool' : 'Edit tool',
      saveLabel: isNew ? 'Create tool' : 'Save changes',
      saving: _saving,
      onSave: _save,
      onDelete: isNew ? null : _delete,
      children: [
        LabeledInput(label: 'Name', child: TextField(controller: _name)),
        LabeledInput(
            label: 'Description',
            child: TextField(controller: _desc, maxLines: 4)),
        LabeledInput(label: 'Version', child: TextField(controller: _version)),
        LabeledInput(
            label: 'License key',
            hint: 'Delivered to the customer once their payment is approved.',
            child: TextField(controller: _license)),
        LabeledInput(
          label: 'Price (TZS, 0 = free)',
          child: TextField(
              controller: _price, keyboardType: TextInputType.number),
        ),
        LabeledInput(
          label: 'Status',
          child: StatusDropdown(
              value: _status, onChanged: (v) => setState(() => _status = v)),
        ),
        FeaturedSwitch(
            value: _featured, onChanged: (v) => setState(() => _featured = v)),
        if (!isNew)
          ImageUploadField(
            label: 'Tool image',
            currentUrl: d!.imageUrl,
            onUpload: (path) async {
              await ref
                  .read(adminCatalogueRepositoryProvider)
                  .uploadImage('tools', d!.id, path);
              ref.invalidate(adminCatToolProvider(d!.id));
              ref.invalidate(adminCatToolsProvider);
            },
          ),
      ],
    );
  }
}

// ===========================================================================
// Scholarship
// ===========================================================================
class ScholarshipFormScreen extends ConsumerWidget {
  const ScholarshipFormScreen({super.key, this.scholarshipId});
  final int? scholarshipId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (scholarshipId == null) return const _ScholarshipForm(detail: null);
    final async = ref.watch(adminCatScholarshipProvider(scholarshipId!));
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(title: const Text('Edit scholarship')),
      body: AsyncValueView<CatScholarshipDetail>(
        value: async,
        onRefresh: () async =>
            ref.refresh(adminCatScholarshipProvider(scholarshipId!).future),
        data: (d) => _ScholarshipForm(detail: d),
      ),
    );
  }
}

class _ScholarshipForm extends ConsumerStatefulWidget {
  const _ScholarshipForm({required this.detail});
  final CatScholarshipDetail? detail;

  @override
  ConsumerState<_ScholarshipForm> createState() => _ScholarshipFormState();
}

class _ScholarshipFormState extends ConsumerState<_ScholarshipForm> {
  late final TextEditingController _title;
  late final TextEditingController _country;
  late final TextEditingController _desc;
  late final TextEditingController _applyUrl;
  DateTime? _deadline;
  String _status = 'draft';
  bool _featured = false;
  bool _saving = false;

  CatScholarshipDetail? get d => widget.detail;
  bool get isNew => d == null;

  @override
  void initState() {
    super.initState();
    _title = TextEditingController(text: d?.title ?? '');
    _country = TextEditingController(text: d?.country ?? '');
    _desc = TextEditingController(text: d?.description ?? '');
    _applyUrl = TextEditingController(text: d?.applyUrl ?? '');
    _deadline = d?.deadline != null ? DateTime.tryParse(d!.deadline!) : null;
    _status = d?.status ?? 'draft';
    _featured = d?.isFeatured ?? false;
  }

  @override
  void dispose() {
    for (final c in [_title, _country, _desc, _applyUrl]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final body = <String, dynamic>{
      'title': _title.text.trim(),
      'country': _country.text.trim(),
      'description': _desc.text.trim(),
      'apply_url': _applyUrl.text.trim(),
      'status': _status,
      'is_featured': _featured,
      if (_deadline != null)
        'deadline':
            '${_deadline!.year}-${_deadline!.month.toString().padLeft(2, '0')}-${_deadline!.day.toString().padLeft(2, '0')}',
    };
    setState(() => _saving = true);
    try {
      final repo = ref.read(adminCatalogueRepositoryProvider);
      if (isNew) {
        await repo.createScholarship(body);
      } else {
        await repo.updateScholarship(d!.id, body);
        ref.invalidate(adminCatScholarshipProvider(d!.id));
      }
      ref.invalidate(adminCatScholarshipsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content:
                Text(isNew ? 'Scholarship created.' : 'Scholarship saved.')));
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

  Future<void> _delete() async {
    final reason = await promptReason(context,
        title: 'Delete scholarship', actionLabel: 'Delete', hint: 'Reason');
    if (reason == null || reason.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref
          .read(adminCatalogueRepositoryProvider)
          .deleteScholarship(d!.id, reason);
      ref.invalidate(adminCatScholarshipsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Scholarship deleted.')));
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
    return AdminFormScaffold(
      title: isNew ? 'New scholarship' : 'Edit scholarship',
      saveLabel: isNew ? 'Create scholarship' : 'Save changes',
      saving: _saving,
      onSave: _save,
      onDelete: isNew ? null : _delete,
      children: [
        LabeledInput(label: 'Title', child: TextField(controller: _title)),
        LabeledInput(label: 'Country', child: TextField(controller: _country)),
        LabeledInput(
            label: 'Description',
            child: TextField(controller: _desc, maxLines: 5)),
        LabeledInput(
          label: 'Deadline',
          child: InkWell(
            onTap: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: _deadline ?? DateTime.now(),
                firstDate: DateTime(2020),
                lastDate: DateTime(2100),
              );
              if (picked != null) setState(() => _deadline = picked);
            },
            child: InputDecorator(
              decoration: const InputDecoration(),
              child: Text(
                _deadline == null
                    ? 'No deadline'
                    : '${_deadline!.year}-${_deadline!.month.toString().padLeft(2, '0')}-${_deadline!.day.toString().padLeft(2, '0')}',
              ),
            ),
          ),
        ),
        LabeledInput(
            label: 'Apply URL', child: TextField(controller: _applyUrl)),
        LabeledInput(
          label: 'Status',
          child: StatusDropdown(
              value: _status, onChanged: (v) => setState(() => _status = v)),
        ),
        FeaturedSwitch(
            value: _featured, onChanged: (v) => setState(() => _featured = v)),
        if (!isNew)
          ImageUploadField(
            label: 'Scholarship image',
            currentUrl: d!.imageUrl,
            onUpload: (path) async {
              await ref
                  .read(adminCatalogueRepositoryProvider)
                  .uploadImage('scholarships', d!.id, path);
              ref.invalidate(adminCatScholarshipProvider(d!.id));
              ref.invalidate(adminCatScholarshipsProvider);
            },
          ),
      ],
    );
  }
}

String _num(double v) => v == v.roundToDouble() ? v.toInt().toString() : '$v';
