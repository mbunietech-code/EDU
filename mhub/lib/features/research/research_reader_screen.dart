import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/research_api.dart';
import '../../models/research.dart';
import '../../theme/tokens.dart';
import '../../widgets/async_value_view.dart';
import '../../widgets/mbui/mbui.dart';

class ResearchReaderScreen extends ConsumerStatefulWidget {
  const ResearchReaderScreen({
    super.key,
    required this.slug,
    required this.chapterId,
  });

  final String slug;
  final int chapterId;

  @override
  ConsumerState<ResearchReaderScreen> createState() =>
      _ResearchReaderScreenState();
}

class _ResearchReaderScreenState extends ConsumerState<ResearchReaderScreen> {
  late int _chapterId = widget.chapterId;
  final _scroll = ScrollController();
  final _sectionKeys = <int, GlobalKey>{};
  Set<int> _done = {};

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  ({String slug, int chapterId}) get _args =>
      (slug: widget.slug, chapterId: _chapterId);

  void _goChapter(int id) {
    setState(() {
      _chapterId = id;
      _sectionKeys.clear();
      _done = {};
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.jumpTo(0);
    });
  }

  Future<void> _toggle(String slug, int sectionId, bool done) async {
    setState(() {
      if (done) {
        _done.add(sectionId);
      } else {
        _done.remove(sectionId);
      }
    });
    try {
      final res = await ref
          .read(researchRepositoryProvider)
          .markSection(slug, sectionId, done);
      if (mounted) setState(() => _done = res.done.toSet());
      ref.invalidate(researchDetailProvider(slug));
      ref.invalidate(researchHomeProvider);
    } catch (_) {}
  }

  void _openToc(ResearchChapterContent c) {
    showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (_) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: MbuiSectionLabel('This chapter'),
            ),
            for (final s in c.sections)
              ListTile(
                dense: true,
                leading: Icon(
                  _done.contains(s.id)
                      ? Icons.check_circle
                      : Icons.circle_outlined,
                  size: 18,
                  color: _done.contains(s.id)
                      ? AppColors.emerald600
                      : AppColors.gray400,
                ),
                title: Text(s.heading, style: const TextStyle(fontSize: 13)),
                onTap: () {
                  Navigator.pop(context);
                  final key = _sectionKeys[s.id];
                  final ctx = key?.currentContext;
                  if (ctx != null) {
                    Scrollable.ensureVisible(ctx,
                        duration: const Duration(milliseconds: 300),
                        alignment: 0.05);
                  }
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(researchChapterProvider(_args));

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(async.asData?.value.chapterTitle ?? 'Reading'),
        actions: [
          if (async.asData != null)
            IconButton(
              tooltip: 'Contents',
              icon: const Icon(Icons.list),
              onPressed: () => _openToc(async.asData!.value),
            ),
        ],
      ),
      body: AsyncValueView<ResearchChapterContent>(
        value: async,
        onRefresh: () async => ref.refresh(researchChapterProvider(_args).future),
        data: (c) {
          // Sync server-known done state once per load.
          if (_done.isEmpty && c.doneSectionIds.isNotEmpty) {
            _done = c.doneSectionIds.toSet();
          }
          for (final s in c.sections) {
            _sectionKeys.putIfAbsent(s.id, () => GlobalKey());
          }

          return ListView(
            controller: _scroll,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              Text(c.researchTitle,
                  style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppColors.indigo600)),
              const SizedBox(height: 2),
              Text(c.chapterTitle,
                  style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: AppColors.gray900,
                      height: 1.25)),
              for (final s in c.sections)
                Container(
                  key: _sectionKeys[s.id],
                  margin: const EdgeInsets.only(top: 24),
                  padding: const EdgeInsets.only(top: 20),
                  decoration: const BoxDecoration(
                    border: Border(top: BorderSide(color: AppColors.gray200)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(s.heading,
                                style: const TextStyle(
                                    fontSize: 17,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.gray900)),
                          ),
                          _MarkReadCheck(
                            done: _done.contains(s.id),
                            onChanged: (v) => _toggle(widget.slug, s.id, v),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      MarkdownBody(
                        data: s.body.isEmpty ? '_No content._' : s.body,
                        styleSheet: _mdStyle(context),
                        onTapLink: (text, href, title) {
                          if (href != null) {
                            launchUrl(Uri.parse(href),
                                mode: LaunchMode.externalApplication);
                          }
                        },
                      ),
                    ],
                  ),
                ),
              if (c.sections.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 24),
                  child: Text('This chapter has no sections yet.',
                      style: TextStyle(color: AppColors.gray400)),
                ),

              const SizedBox(height: 32),
              Row(
                children: [
                  if (c.prev != null)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _goChapter(c.prev!.id),
                        icon: const Icon(Icons.chevron_left, size: 18),
                        label: Text(c.prev!.title,
                            maxLines: 1, overflow: TextOverflow.ellipsis),
                      ),
                    )
                  else
                    const Spacer(),
                  const SizedBox(width: 10),
                  if (c.next != null)
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: () => _goChapter(c.next!.id),
                        icon: const Icon(Icons.chevron_right, size: 18),
                        label: Text(c.next!.title,
                            maxLines: 1, overflow: TextOverflow.ellipsis),
                      ),
                    )
                  else
                    const Spacer(),
                ],
              ),
            ],
          );
        },
      ),
    );
  }

  MarkdownStyleSheet _mdStyle(BuildContext context) {
    final base = MarkdownStyleSheet.fromTheme(Theme.of(context));
    return base.copyWith(
      p: const TextStyle(fontSize: 15, height: 1.6, color: AppColors.gray700),
      h1: const TextStyle(fontSize: 19, fontWeight: FontWeight.bold, color: AppColors.gray900),
      h2: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppColors.gray900),
      h3: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.gray900),
      h4: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.gray900),
      listBullet: const TextStyle(fontSize: 15, height: 1.6, color: AppColors.gray700),
      blockquoteDecoration: const BoxDecoration(
        border: Border(left: BorderSide(color: AppColors.indigo100, width: 3)),
      ),
      blockquotePadding: const EdgeInsets.only(left: 12),
      blockquote: const TextStyle(
          fontSize: 15, fontStyle: FontStyle.italic, color: AppColors.gray600),
      code: const TextStyle(
          fontSize: 13, backgroundColor: AppColors.gray100, fontFamily: 'monospace'),
      a: const TextStyle(color: AppColors.indigo600, fontWeight: FontWeight.w500),
      horizontalRuleDecoration: const BoxDecoration(
        border: Border(top: BorderSide(color: AppColors.gray200)),
      ),
    );
  }
}

class _MarkReadCheck extends StatelessWidget {
  const _MarkReadCheck({required this.done, required this.onChanged});
  final bool done;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => onChanged(!done),
      borderRadius: BorderRadius.circular(6),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(done ? Icons.check_circle : Icons.circle_outlined,
                size: 18,
                color: done ? AppColors.emerald600 : AppColors.gray400),
            const SizedBox(width: 4),
            Text(done ? 'Read' : 'Mark read',
                style: TextStyle(
                    fontSize: 11,
                    color: done ? AppColors.emerald700 : AppColors.gray500)),
          ],
        ),
      ),
    );
  }
}
