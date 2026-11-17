import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_client.dart';
import '../../models/research_request.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';
import 'research_requests_repository.dart';

/// One research request: the stage tracker (% and current stage), things
/// waiting for the customer (price, delivered work), deliverables to
/// download, the chat with the team, and the history.
class ResearchRequestDetailScreen extends ConsumerStatefulWidget {
  const ResearchRequestDetailScreen({super.key, required this.requestId, this.title});

  final int requestId;
  final String? title;

  @override
  ConsumerState<ResearchRequestDetailScreen> createState() => _ResearchRequestDetailScreenState();
}

class _ResearchRequestDetailScreenState extends ConsumerState<ResearchRequestDetailScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this);
  final _message = TextEditingController();
  final List<PlatformFile> _files = [];
  bool _busy = false;

  ResearchRequestsRepository get _repo => ref.read(researchRequestsRepositoryProvider);

  @override
  void dispose() {
    _tabs.dispose();
    _message.dispose();
    super.dispose();
  }

  void _reload() => ref.invalidate(researchRequestDetailProvider(widget.requestId));

  void _snack(String text) {
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _run(Future<void> Function() action, String done) async {
    setState(() => _busy = true);
    try {
      await action();
      _snack(done);
      _reload();
    } on ApiException catch (e) {
      _snack(e.errors?.values.first.first ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _open(RrFile f) async {
    try {
      final url = await _repo.fileUrl(f);
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } on ApiException catch (e) {
      _snack(e.message);
    }
  }

  Future<String?> _ask(String title, String hint, {bool required = false, int minLength = 0, String action = 'Send'}) async {
    final c = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(controller: c, minLines: 3, maxLines: 6, autofocus: true, decoration: InputDecoration(hintText: hint, border: const OutlineInputBorder())),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Back')),
          FilledButton(
            onPressed: () {
              if (required && c.text.trim().length < minLength) return;
              Navigator.pop(ctx, c.text.trim());
            },
            child: Text(action),
          ),
        ],
      ),
    );
    c.dispose();
    return result;
  }

  Future<void> _rate(ResearchRequest rr) async {
    var rating = 0;
    final review = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, set) => Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.viewInsetsOf(ctx).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Accept & rate the work', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              const Text('This closes the request.', style: TextStyle(color: AppColors.gray500)),
              const SizedBox(height: 14),
              Row(children: [
                for (var i = 1; i <= 5; i++)
                  IconButton(
                    iconSize: 36,
                    onPressed: () => set(() => rating = i),
                    icon: Icon(i <= rating ? Icons.star_rounded : Icons.star_outline_rounded, color: const Color(0xFFFBBF24)),
                  ),
              ]),
              TextField(controller: review, minLines: 2, maxLines: 5, decoration: const InputDecoration(hintText: 'Review (optional)', border: OutlineInputBorder())),
              const SizedBox(height: 14),
              MbuiButton(
                label: 'Accept & close',
                variant: MbuiVariant.success,
                fullWidth: true,
                onPressed: rating == 0 ? null : () => Navigator.pop(ctx, true),
              ),
            ],
          ),
        ),
      ),
    );
    final text = review.text.trim();
    review.dispose();
    if (ok == true) {
      await _run(() => _repo.complete(rr.id, rating, text), 'Thank you! The request is closed.');
    }
  }

  Future<void> _attach() async {
    final picked = await FilePicker.pickFiles(allowMultiple: true);
    if (picked == null) return;
    setState(() => _files.addAll(picked.files.where((f) => f.path != null)));
  }

  Future<void> _send(ResearchRequest rr) async {
    final body = _message.text.trim();
    if (body.isEmpty && _files.isEmpty) return;
    await _run(() async {
      await _repo.message(rr.id, body, _files.map((f) => f.path!).toList());
      _message.clear();
      setState(_files.clear);
    }, 'Message sent.');
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(researchRequestDetailProvider(widget.requestId));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(async.valueOrNull?.reference ?? widget.title ?? 'Request'),
        actions: [
          if (async.valueOrNull?.canCancel == true)
            PopupMenuButton<String>(
              onSelected: (_) async {
                final reason = await _ask('Cancel this request?', 'Reason (optional)', action: 'Cancel request');
                if (reason != null) await _run(() => _repo.cancel(widget.requestId, reason), 'Request cancelled.');
              },
              itemBuilder: (_) => const [PopupMenuItem(value: 'cancel', child: Text('Cancel request'))],
            ),
        ],
        bottom: TabBar(
          controller: _tabs,
          labelColor: AppColors.indigo600,
          indicatorColor: AppColors.indigo600,
          tabs: [
            const Tab(text: 'Progress'),
            Tab(text: 'Messages${(async.valueOrNull?.messages.length ?? 0) > 0 ? ' (${async.valueOrNull!.messages.length})' : ''}'),
            const Tab(text: 'Details'),
          ],
        ),
      ),
      body: AsyncValueView<ResearchRequest>(
        value: async,
        onRefresh: () async => ref.refresh(researchRequestDetailProvider(widget.requestId).future),
        data: (rr) => Stack(
          children: [
            TabBarView(
              controller: _tabs,
              children: [
                _progressTab(rr),
                _messagesTab(rr),
                _detailsTab(rr),
              ],
            ),
            if (_busy) const LinearProgressIndicator(minHeight: 2),
          ],
        ),
      ),
    );
  }

  // --- Progress ---------------------------------------------------------------

  Widget _progressTab(ResearchRequest rr) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(rr.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.gray900)),
        const SizedBox(height: 6),
        Wrap(spacing: 8, runSpacing: 6, children: [
          MbuiBadge('${rr.priorityLabel} priority', appearance: rr.priority == 'urgent' ? MbuiAppearance.danger : (rr.priority == 'high' ? MbuiAppearance.warning : MbuiAppearance.info)),
          if (rr.deadlineLabel != null)
            MbuiBadge(rr.deadlineLabel!, appearance: rr.overdue ? MbuiAppearance.danger : (rr.dueSoon ? MbuiAppearance.warning : MbuiAppearance.neutral)),
        ]),
        const SizedBox(height: 14),
        _hero(rr),
        if (rr.canAnswerQuote && rr.quote != null) ...[const SizedBox(height: 14), _quoteCard(rr)],
        if (rr.canComplete || rr.canRequestRevision) ...[const SizedBox(height: 14), _deliveredCard(rr)],
        if (rr.status == 'cancelled') ...[
          const SizedBox(height: 14),
          MbuiCard(child: Text('This request was cancelled.${rr.cancelReason != null ? ' Reason: ${rr.cancelReason}' : ''}', style: const TextStyle(color: AppColors.amber700))),
        ],
        if (rr.rating != null) ...[
          const SizedBox(height: 14),
          MbuiCard(
            child: Row(children: [
              for (var i = 1; i <= 5; i++)
                Icon(Icons.star_rounded, size: 20, color: i <= rr.rating! ? const Color(0xFFFBBF24) : AppColors.gray300),
              const SizedBox(width: 8),
              Expanded(child: Text('You rated this work ${rr.rating}/5', style: const TextStyle(color: AppColors.gray600))),
            ]),
          ),
        ],
        const SizedBox(height: 14),
        _tracker(rr),
        const SizedBox(height: 14),
        _deliverables(rr),
        const SizedBox(height: 14),
        _history(rr),
        const SizedBox(height: 24),
      ],
    );
  }

  /// Plain white card (site style): stage, % and a flat progress bar — no gradients.
  Widget _hero(ResearchRequest rr) {
    final headline = rr.status == 'cancelled' ? 'Cancelled' : (rr.status == 'completed' ? 'Completed' : (rr.stageName ?? 'Received'));
    final barColor = rr.status == 'cancelled' ? AppColors.gray400 : (rr.status == 'completed' ? AppColors.emerald600 : rr.color);
    return MbuiCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const MbuiSectionLabel('Where your work is'),
                  const SizedBox(height: 4),
                  Text(headline, style: const TextStyle(color: AppColors.gray900, fontSize: 20, fontWeight: FontWeight.bold)),
                  if (rr.isActive && rr.stageDescription != null)
                    Text(rr.stageDescription!, style: const TextStyle(color: AppColors.gray500, fontSize: 13)),
                ]),
              ),
              Text('${rr.progress}%', style: const TextStyle(color: AppColors.gray900, fontSize: 30, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: rr.progress / 100,
              minHeight: 8,
              backgroundColor: AppColors.gray100,
              valueColor: AlwaysStoppedAnimation(barColor),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tracker(ResearchRequest rr) {
    return MbuiCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const MbuiSectionLabel('Process'),
          const SizedBox(height: 10),
          for (var i = 0; i < rr.tracker.length; i++)
            _TrackerRow(
              step: rr.tracker[i],
              index: i,
              last: i == rr.tracker.length - 1,
              cancelled: rr.status == 'cancelled',
            ),
        ],
      ),
    );
  }

  Widget _quoteCard(ResearchRequest rr) {
    final q = rr.quote!;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.amber50,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('PRICE FOR THIS WORK', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, letterSpacing: 0.8, color: AppColors.amber700)),
          const SizedBox(height: 4),
          Text(q.label, style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: AppColors.gray900)),
          if (q.note != null) ...[const SizedBox(height: 6), Text(q.note!, style: const TextStyle(color: AppColors.gray700))],
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              child: MbuiButton(
                label: 'Accept price',
                variant: MbuiVariant.success,
                onPressed: _busy ? null : () => _run(() => _repo.acceptQuote(rr.id), 'Price accepted. The team has been told.'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: MbuiButton(
                label: 'Decline',
                variant: MbuiVariant.secondary,
                onPressed: _busy
                    ? null
                    : () async {
                        final reason = await _ask('Decline the price', 'Tell the team why (e.g. your budget)', action: 'Decline');
                        if (reason != null) await _run(() => _repo.declineQuote(rr.id, reason), 'Price declined.');
                      },
              ),
            ),
          ]),
        ],
      ),
    );
  }

  Widget _deliveredCard(ResearchRequest rr) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.emerald50,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: const Color(0xFFA7F3D0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(rr.canComplete ? 'Your work is delivered' : 'A draft is ready for you',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.gray900)),
          const SizedBox(height: 4),
          Text(rr.canComplete ? 'Download it below. Happy? Accept and rate it — or ask for changes.' : 'Read it and tell us what to change.',
              style: const TextStyle(color: AppColors.gray600)),
          const SizedBox(height: 12),
          Row(children: [
            if (rr.canComplete)
              Expanded(child: MbuiButton(label: 'Accept & rate', variant: MbuiVariant.success, onPressed: _busy ? null : () => _rate(rr))),
            if (rr.canComplete && rr.canRequestRevision) const SizedBox(width: 10),
            if (rr.canRequestRevision)
              Expanded(
                child: MbuiButton(
                  label: 'Ask for changes',
                  variant: MbuiVariant.secondary,
                  onPressed: _busy
                      ? null
                      : () async {
                          final reason = await _ask('What should be changed?', 'Chapter / page, what is wrong, what you expect…', required: true, minLength: 10);
                          if (reason != null) await _run(() => _repo.revision(rr.id, reason), 'Revision requested.');
                        },
                ),
              ),
          ]),
        ],
      ),
    );
  }

  Widget _deliverables(ResearchRequest rr) {
    return MbuiCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const MbuiSectionLabel('Your work (drafts & final)'),
          const SizedBox(height: 8),
          if (rr.deliverables.isEmpty)
            const Text('Drafts and the final work will appear here to download.', style: TextStyle(color: AppColors.gray500)),
          for (final f in rr.deliverables) _FileTile(file: f, onTap: () => _open(f)),
        ],
      ),
    );
  }

  Widget _history(ResearchRequest rr) {
    return MbuiCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const MbuiSectionLabel('History'),
          const SizedBox(height: 10),
          for (final h in rr.history)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: CircleAvatar(radius: 5, backgroundColor: h.color ?? AppColors.gray400),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${h.label}${h.progress != null ? ' · ${h.progress}%' : ''}', style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.gray900)),
                      if (h.note != null) Text(h.note!, style: const TextStyle(color: AppColors.gray600)),
                      Text(h.time, style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                    ]),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  // --- Messages ---------------------------------------------------------------

  Widget _messagesTab(ResearchRequest rr) {
    return Column(
      children: [
        Expanded(
          child: rr.messages.isEmpty
              ? const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No messages yet. Ask a question or add details for the team.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500)),
                  ),
                )
              : ListView.builder(
                  reverse: true,
                  padding: const EdgeInsets.all(16),
                  itemCount: rr.messages.length,
                  itemBuilder: (_, i) => _Bubble(msg: rr.messages[rr.messages.length - 1 - i], onFile: _open),
                ),
        ),
        if (rr.canMessage)
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.fromLTRB(8, 6, 8, 8),
              decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppColors.gray200))),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_files.isNotEmpty)
                    SizedBox(
                      height: 36,
                      child: ListView(scrollDirection: Axis.horizontal, children: [
                        for (final f in _files)
                          Padding(
                            padding: const EdgeInsets.only(right: 6),
                            child: InputChip(label: Text(f.name, overflow: TextOverflow.ellipsis), onDeleted: () => setState(() => _files.remove(f))),
                          ),
                      ]),
                    ),
                  Row(children: [
                    IconButton(icon: const Icon(Icons.attach_file), onPressed: _busy ? null : _attach),
                    Expanded(
                      child: TextField(
                        controller: _message,
                        minLines: 1,
                        maxLines: 5,
                        decoration: const InputDecoration(hintText: 'Write to the team…', border: InputBorder.none),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.send, color: AppColors.indigo600),
                      onPressed: _busy ? null : () => _send(rr),
                    ),
                  ]),
                ],
              ),
            ),
          ),
      ],
    );
  }

  // --- Details ----------------------------------------------------------------

  Widget _detailsTab(ResearchRequest rr) {
    Widget row(String k, String? v) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(width: 120, child: Text(k, style: const TextStyle(color: AppColors.gray500))),
            Expanded(child: Text(v == null || v.isEmpty ? '—' : v, style: const TextStyle(fontWeight: FontWeight.w500, color: AppColors.gray900))),
          ]),
        );

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        MbuiCard(
          child: Column(children: [
            row('Reference', rr.reference),
            row('Type', rr.typeLabel),
            row('Level', rr.levelLabel),
            row('Field', rr.fieldOfStudy),
            row('Purpose', rr.purpose),
            row('Length', rr.pages != null ? '${rr.pages} pages' : null),
            row('Citation', rr.citationLabel),
            row('Your budget', rr.budgetLabel),
            row('Price', rr.quote == null ? 'Not quoted yet' : '${rr.quote!.label}${rr.quote!.status != null && rr.quote!.status != 'pending' ? ' (${rr.quote!.status})' : ''}'),
            row('Handled by', rr.handledBy ?? 'Our research team'),
            row('Sent', rr.createdAgo),
          ]),
        ),
        const SizedBox(height: 14),
        MbuiCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const MbuiSectionLabel('Your description'),
            const SizedBox(height: 8),
            SelectableText(rr.description ?? '', style: const TextStyle(color: AppColors.gray800, height: 1.4)),
          ]),
        ),
        if (rr.attachments.isNotEmpty) ...[
          const SizedBox(height: 14),
          MbuiCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const MbuiSectionLabel('Your attachments'),
              const SizedBox(height: 8),
              for (final f in rr.attachments) _FileTile(file: f, onTap: () => _open(f)),
            ]),
          ),
        ],
      ],
    );
  }
}

class _TrackerRow extends StatelessWidget {
  const _TrackerRow({required this.step, required this.index, required this.last, required this.cancelled});

  final RrStep step;
  final int index;
  final bool last;
  final bool cancelled;

  @override
  Widget build(BuildContext context) {
    final done = step.state == 'done';
    final current = step.state == 'current' && !cancelled;
    final dotColor = done ? AppColors.emerald600 : (current ? step.color : AppColors.gray200);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(children: [
            Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                color: dotColor,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: done
                  ? const Icon(Icons.check, size: 16, color: Colors.white)
                  : Text('${index + 1}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: current ? Colors.white : AppColors.gray500)),
            ),
            if (!last) Expanded(child: Container(width: 2, color: done ? AppColors.emerald600 : AppColors.gray200)),
          ]),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 4, bottom: last ? 0 : 14),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Text(step.name,
                      style: TextStyle(
                        fontWeight: current ? FontWeight.bold : FontWeight.w500,
                        color: done || current ? AppColors.gray900 : AppColors.gray400,
                      )),
                  if (current) ...[
                    const SizedBox(width: 8),
                    Text('NOW', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: step.color, letterSpacing: 0.8)),
                  ],
                ]),
                if (current && step.description != null)
                  Text(step.description!, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _FileTile extends StatelessWidget {
  const _FileTile({required this.file, required this.onTap});
  final RrFile file;
  final VoidCallback onTap;

  Color get _tone => switch (file.extension) {
        'pdf' => AppColors.red600,
        'doc' || 'docx' || 'odt' || 'rtf' || 'txt' => const Color(0xFF0284C7),
        'xls' || 'xlsx' || 'csv' || 'sav' || 'dta' => AppColors.emerald600,
        'ppt' || 'pptx' => const Color(0xFFEA580C),
        _ => AppColors.gray500,
      };

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      onTap: onTap,
      leading: Container(
        width: 40,
        height: 40,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: _tone.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(AppRadius.md)),
        child: Text(file.extension.toUpperCase(), style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: _tone)),
      ),
      title: Text(file.name, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text([file.kindLabel, file.sizeLabel, if (file.note != null) file.note!].join(' · '), maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: const Icon(Icons.download_outlined, color: AppColors.indigo600),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.msg, required this.onFile});
  final RrMessage msg;
  final void Function(RrFile) onFile;

  @override
  Widget build(BuildContext context) {
    final mine = !msg.fromTeam;
    final fg = mine ? Colors.white : AppColors.gray900;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: mine ? AppColors.indigo600 : AppColors.gray100,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(mine ? 16 : 4),
            bottomRight: Radius.circular(mine ? 4 : 16),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${msg.fromTeam ? '${msg.author} · Team' : 'You'} · ${msg.time}',
                style: TextStyle(fontSize: 11, color: mine ? const Color(0xFFC7D2FE) : AppColors.gray500)),
            if (msg.body != null && msg.body!.isNotEmpty) ...[
              const SizedBox(height: 2),
              SelectableText(msg.body!, style: TextStyle(color: fg)),
            ],
            for (final f in msg.files)
              InkWell(
                onTap: () => onFile(f),
                child: Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.attach_file, size: 16, color: fg),
                    const SizedBox(width: 4),
                    Flexible(child: Text(f.name, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg, decoration: TextDecoration.underline))),
                  ]),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
