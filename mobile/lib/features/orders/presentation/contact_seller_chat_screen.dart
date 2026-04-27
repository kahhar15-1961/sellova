import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:async';

import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../data/order_repository.dart';

class ContactSellerChatScreen extends ConsumerStatefulWidget {
  const ContactSellerChatScreen({
    super.key,
    required this.orderId,
    this.title = 'Contact Seller',
  });

  final int orderId;
  final String title;

  @override
  ConsumerState<ContactSellerChatScreen> createState() => _ContactSellerChatScreenState();
}

class _ContactSellerChatScreenState extends ConsumerState<ContactSellerChatScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final List<ChatMessageDto> _messages = <ChatMessageDto>[];
  bool _loading = true;
  int _threadId = 0;
  Timer? _pollTimer;
  final Debouncer _typingDebouncer = Debouncer(duration: const Duration(milliseconds: 700));
  List<String> _typingUsers = const <String>[];

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_bootstrap);
  }

  Future<void> _bootstrap() async {
    try {
      final repo = ref.read(orderRepositoryProvider);
      final threadId = await repo.getOrCreateChatThread(widget.orderId);
      final messages = await repo.listChatMessages(threadId);
      await repo.markChatThreadRead(threadId);
      if (!mounted) return;
      setState(() {
        _threadId = threadId;
        _messages
          ..clear()
          ..addAll(messages);
        _loading = false;
      });
      _pollTimer?.cancel();
      _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) => _refreshMessagesSilently());
      await ref.read(chatRealtimeClientProvider).subscribeThread(
            threadId: threadId,
            onMessageCreated: (message) {
              if (!mounted) return;
              final dto = ChatMessageDto(message);
              setState(() {
                if (_messages.any((m) => m.id == dto.id)) return;
                _messages.add(dto);
              });
            },
            onTypingUpdated: (typing) {
              if (!mounted) return;
              final typingOn = typing['typing'] == true;
              final name = (typing['name'] ?? '').toString();
              if (name.isEmpty) return;
              setState(() {
                if (!typingOn) {
                  _typingUsers = _typingUsers.where((u) => u != name).toList();
                } else if (!_typingUsers.contains(name)) {
                  _typingUsers = <String>[..._typingUsers, name];
                }
              });
            },
          );
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  Future<void> _refreshMessagesSilently() async {
    if (!mounted || _threadId <= 0) return;
    try {
      final repo = ref.read(orderRepositoryProvider);
      final messages = await repo.listChatMessages(_threadId);
      await repo.markChatThreadRead(_threadId);
      final typingUsers = await repo.loadTypingUsers(_threadId);
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
    if (text.isEmpty || _threadId <= 0) return;
    final repo = ref.read(orderRepositoryProvider);
    final created = await repo.sendChatMessage(_threadId, text);
    await repo.setTyping(_threadId, typing: false);
    if (!mounted) return;
    setState(() {
      _messages.add(created);
      _msgCtrl.clear();
    });
  }

  Future<void> _pickAndSendAttachment() async {
    if (_threadId <= 0) return;
    final picked = await FilePicker.platform.pickFiles(withData: false, allowMultiple: false);
    final file = (picked != null && picked.files.isNotEmpty) ? picked.files.first : null;
    if (file == null || file.path == null) return;
    final created = await ref.read(orderRepositoryProvider).sendChatAttachment(
          threadId: _threadId,
          filePath: file.path!,
          fileName: file.name,
          body: _msgCtrl.text.trim().isEmpty ? null : _msgCtrl.text.trim(),
        );
    if (!mounted) return;
    setState(() {
      _messages.add(created);
      _msgCtrl.clear();
    });
    await ref.read(orderRepositoryProvider).setTyping(_threadId, typing: false);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _typingDebouncer.dispose();
    if (_threadId > 0) {
      ref.read(chatRealtimeClientProvider).unsubscribeThread(_threadId);
    }
    _msgCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(widget.title),
            Text('Online', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF16A34A))),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
                ),
                child: Row(
                  children: <Widget>[
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        color: cs.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.headphones, size: 28),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Wireless Noise Cancelling Headphones', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          const SizedBox(height: 2),
                          Text('Order #${widget.orderId}', style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded),
                  ],
                ),
              ),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                itemCount: _messages.length + 1,
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Center(
                        child: Text('01 June 2025', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B))),
                      ),
                    );
                  }
                  final m = _messages[index - 1];
                  return Align(
                    alignment: m.fromMe ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      constraints: const BoxConstraints(maxWidth: 280),
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                      decoration: BoxDecoration(
                        color: m.fromMe ? const Color(0xFFF1EEFF) : cs.surface,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.28)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          if (m.body.isNotEmpty) Text(m.body, style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.35)),
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
                                    style: Theme.of(context).textTheme.bodySmall,
                                  ),
                                ),
                              ],
                            ),
                          ],
                          const SizedBox(height: 6),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Text(
                                m.createdAt.length >= 16 ? m.createdAt.substring(11, 16) : 'Now',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B)),
                              ),
                              if (m.fromMe && m.deliveryStatus == 'read') ...<Widget>[
                                const SizedBox(width: 4),
                                const Icon(Icons.done_all_rounded, size: 16, color: Color(0xFF4F46E5)),
                              ],
                            ],
                          ),
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
                          if (_threadId <= 0) return;
                          final typing = _msgCtrl.text.trim().isNotEmpty;
                          await ref.read(orderRepositoryProvider).setTyping(_threadId, typing: typing);
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
                  IconButton.filled(
                    onPressed: _send,
                    icon: const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
