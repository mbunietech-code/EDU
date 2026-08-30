import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api_client.dart';
import '../../theme/tokens.dart';
import '../../widgets/mbui/mbui.dart';

/// Small building blocks shared by the admin create/edit forms so they all
/// read like the website's form pages.
class LabeledInput extends StatelessWidget {
  const LabeledInput({super.key, required this.label, required this.child, this.hint});
  final String label;
  final Widget child;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppColors.gray700)),
          const SizedBox(height: 6),
          child,
          if (hint != null) ...[
            const SizedBox(height: 4),
            Text(hint!,
                style: const TextStyle(fontSize: 11, color: AppColors.gray400)),
          ],
        ],
      ),
    );
  }
}

class StatusDropdown extends StatelessWidget {
  const StatusDropdown({
    super.key,
    required this.value,
    required this.onChanged,
    this.options = const ['draft', 'published', 'archived'],
  });

  final String value;
  final ValueChanged<String> onChanged;
  final List<String> options;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      initialValue: options.contains(value) ? value : options.first,
      items: [
        for (final o in options)
          DropdownMenuItem(
            value: o,
            child: Text(o[0].toUpperCase() + o.substring(1)),
          ),
      ],
      onChanged: (v) => onChanged(v ?? options.first),
    );
  }
}

class FeaturedSwitch extends StatelessWidget {
  const FeaturedSwitch({super.key, required this.value, required this.onChanged});
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      title: const Text('Featured', style: TextStyle(fontSize: 14)),
      subtitle: const Text('Highlight on the site', style: TextStyle(fontSize: 12)),
      value: value,
      onChanged: onChanged,
    );
  }
}

/// Shows the current image (if any) and an upload button. [onUpload] receives
/// the picked file path and should perform the multipart upload, returning the
/// new image URL (or null on failure). Requires an already-saved entity.
class ImageUploadField extends StatefulWidget {
  const ImageUploadField({
    super.key,
    required this.label,
    required this.currentUrl,
    required this.onUpload,
    this.enabled = true,
  });

  final String label;
  final String? currentUrl;
  final Future<void> Function(String path) onUpload;
  final bool enabled;

  @override
  State<ImageUploadField> createState() => _ImageUploadFieldState();
}

class _ImageUploadFieldState extends State<ImageUploadField> {
  File? _local;
  bool _busy = false;
  String? _error;

  Future<void> _pick() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      imageQuality: 85,
    );
    if (picked == null) return;
    setState(() {
      _local = File(picked.path);
      _busy = true;
      _error = null;
    });
    try {
      await widget.onUpload(picked.path);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasImage = _local != null || widget.currentUrl != null;
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.label,
              style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppColors.gray700)),
          const SizedBox(height: 8),
          if (hasImage)
            ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.md),
              child: _local != null
                  ? Image.file(_local!, height: 140, fit: BoxFit.cover)
                  : Image.network(widget.currentUrl!,
                      height: 140,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => const SizedBox.shrink()),
            ),
          const SizedBox(height: 8),
          MbuiButton(
            label: hasImage ? 'Replace image' : 'Upload image',
            variant: MbuiVariant.secondary,
            icon: Icons.image_outlined,
            loading: _busy,
            onPressed: widget.enabled ? _pick : null,
          ),
          if (_error != null) ...[
            const SizedBox(height: 4),
            Text(_error!,
                style: const TextStyle(fontSize: 12, color: AppColors.red700)),
          ],
        ],
      ),
    );
  }
}

/// A page scaffold for an admin form with a sticky primary save button.
class AdminFormScaffold extends StatelessWidget {
  const AdminFormScaffold({
    super.key,
    required this.title,
    required this.children,
    required this.saveLabel,
    required this.onSave,
    required this.saving,
    this.onDelete,
  });

  final String title;
  final List<Widget> children;
  final String saveLabel;
  final VoidCallback onSave;
  final bool saving;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (onDelete != null)
            IconButton(
              icon: const Icon(Icons.delete_outline),
              color: AppColors.red600,
              onPressed: saving ? null : onDelete,
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          MbuiCard(child: Column(children: children)),
          const SizedBox(height: 16),
          MbuiButton(
            label: saveLabel,
            loading: saving,
            fullWidth: true,
            onPressed: onSave,
          ),
        ],
      ),
    );
  }
}
