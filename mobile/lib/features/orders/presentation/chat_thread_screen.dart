import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:async';

import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../data/order_repository.dart';

class ChatThreadScreen extends ConsumerStatefulWidget {
  const ChatThreadScreen({
    super.key,
    required this.threadId,
    this.title = 'Chat',
  });

  final int threadId;
  final String title;

  @override
  ConsumerState<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends ConsumerState<ChatThreadScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final List<ChatMessageDto> _messages = <ChatMessageDto>[];
  bool _loading = true;
  Timer? _pollTimer;
  final Debouncer _typingDebouncer = Debouncer(duration: const Duration(milliseconds: 700));
  List<String> _typingUsers = const <String>[];

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_load);
  }

  Future<void> _load() async {
    try {
      final repo = ref.read(orderRepositoryProvider);
      final messages = await repo.listChatMessages(widget.threadId);
      await repo.markChatThreadRead(widget.threadId);
      final typingUsers = await repo.loadTypingUsers(widget.threadId);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(messages);
        _typingUsers = typingUsers;
        _loading = false;
      });
      _pollTimer?.cancel();
      _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) => _refreshSilently());
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  Future<void> _refreshSilently() async {
    if (!mounted) return;
    try {
      final repo = ref.read(orderRepositoryProvider);
      final messages = await repo.listChatMessages(widget.threadId);
      await repo.markChatThreadRead(widget.threadId);
      final typingUsers = await repo.loadTypingUsers(widget.threadId);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(messages);
        _typingUsers = typingUsers;
      });
    } catch (_) {}
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty) return;
    final repo = ref.read(orderRepositoryProvider);
    final created = await repo.sendChatMessage(widget.threadId, text);
    await repo.setTyping(widget.threadId, typing: false);
    if (!mounted) return;
    setState(() {
      _messages.add(created);
      _msgCtrl.clear();
    });
  }

  Future<void> _pickAndSendAttachment() async {
    final picked = await FilePicker.platform.pickFiles(withData: false, allowMultiple: false);
    final file = (picked != null && picked.files.isNotEmpty) ? picked.files.first : null;
    if (file == null || file.path == null) return;
    final created = await ref.read(orderRepositoryProvider).sendChatAttachment(
          threadId: widget.threadId,
          filePath: file.path!,
          fileName: file.name,
          body: _msgCtrl.text.trim().isEmpty ? null : _msgCtrl.text.trim(),
        );
    if (!mounted) return;
    setState(() {
      _messages.add(created);
      _msgCtrl.clear();
    });
    await ref.read(orderRepositoryProvider).setTyping(widget.threadId, typing: false);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _typingDebouncer.dispose();
    _msgCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: Text(widget.title)),
      body: Column(
        children: <Widget>[
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                    itemCount: _messages.length,
                    itemBuilder: (_, i) {
                      final m = _messages[i];
                      return Align(
                        alignment: m.fromMe ? Alignment.centerRight : Alignment.centerLeft,
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          constraints: const BoxConstraints(maxWidth: 290),
                          padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                          decoration: BoxDecoration(
                            color: m.fromMe ? const Color(0xFFF1EEFF) : cs.surface,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.28)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              if (m.body.isNotEmpty) Text(m.body),
                              if ((m.attachmentUrl ?? '').isNotEmpty) ...<Widget>[
                                if (m.body.isNotEmpty) const SizedBox(height: 6),
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: <Widget>[
                                    const Icon(Icons.attach_file_rounded, size: 16),
                                    Flexible(
                                      child: Text(
                                        m.attachmentName ?? 'Attachment',
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
          if (_typingUsers.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 6),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  '${_typingUsers.first} is typing...',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
                ),
              ),
            ),
          Padding(
            padding: EdgeInsets.fromLTRB(16, 8, 16, 14 + MediaQuery.paddingOf(context).bottom),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    controller: _msgCtrl,
                    onChanged: (_) {
                      _typingDebouncer.run(() async {
                        final typing = _msgCtrl.text.trim().isNotEmpty;
                        await ref.read(orderRepositoryProvider).setTyping(widget.threadId, typing: typing);
                      });
                    },
                    decoration: InputDecoration(
                      hintText: 'Type a message...',
                      prefixIcon: IconButton(
                        onPressed: _pickAndSendAttachment,
                        icon: const Icon(Icons.attach_file_rounded),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                IconButton.filled(onPressed: _send, icon: const Icon(Icons.send_rounded)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

