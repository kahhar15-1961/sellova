import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';
import 'dart:async';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../application/chat_unread_provider.dart';
import '../data/order_repository.dart';

class ChatThreadScreen extends ConsumerStatefulWidget {
  const ChatThreadScreen({
    super.key,
    required this.threadId,
    this.title = 'Chat',
    this.panel = 'buyer',
  });

  final int threadId;
  final String title;
  final String panel;

  @override
  ConsumerState<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends ConsumerState<ChatThreadScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final List<ChatMessageDto> _messages = <ChatMessageDto>[];
  bool _loading = true;
  Timer? _pollTimer;
  final Debouncer _typingDebouncer =
      Debouncer(duration: const Duration(milliseconds: 700));
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
      ref.invalidate(chatUnreadCountProvider);
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
      _pollTimer =
          Timer.periodic(const Duration(seconds: 4), (_) => _refreshSilently());
      await ref.read(chatRealtimeClientProvider).subscribeThread(
            threadId: widget.threadId,
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

  Future<void> _refreshSilently() async {
    if (!mounted) return;
    try {
      final repo = ref.read(orderRepositoryProvider);
      final messages = await repo.listChatMessages(widget.threadId);
      await repo.markChatThreadRead(widget.threadId);
      ref.invalidate(chatUnreadCountProvider);
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
    final picked = await FilePicker.platform
        .pickFiles(withData: true, allowMultiple: false);
    final file =
        (picked != null && picked.files.isNotEmpty) ? picked.files.first : null;
    if (file == null ||
        (_threadSafePlatformFilePath(file) == null && file.bytes == null)) {
      return;
    }
    final created = await ref.read(orderRepositoryProvider).sendChatAttachment(
          threadId: widget.threadId,
          filePath: _threadSafePlatformFilePath(file),
          fileBytes: file.bytes,
          fileName: file.name,
          body: _msgCtrl.text.trim().isEmpty ? null : _msgCtrl.text.trim(),
        );
    if (!mounted) return;
    setState(() {
      _messages.add(created);
      _msgCtrl.clear();
    });
    await ref
        .read(orderRepositoryProvider)
        .setTyping(widget.threadId, typing: false);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _typingDebouncer.dispose();
    ref.read(chatRealtimeClientProvider).unsubscribeThread(widget.threadId);
    _msgCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final baseUrl = ref.watch(baseUrlProvider);
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
              return;
            }
            context.go('/chats?panel=${Uri.encodeComponent(widget.panel)}');
          },
          tooltip: 'Back',
        ),
        title: Text(widget.title),
        actions: <Widget>[
          IconButton(
            icon: const Icon(Icons.home_outlined),
            tooltip: widget.panel == 'seller' ? 'Seller dashboard' : 'Home',
            onPressed: () => context
                .go(widget.panel == 'seller' ? '/seller/dashboard' : '/home'),
          ),
        ],
      ),
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
                      final attachmentUrl = _threadResolveChatAttachmentUrl(
                        m.attachmentUrl,
                        baseUrl,
                      );
                      final isImage = m.attachmentType == 'image' ||
                          m.attachmentMime.startsWith('image/');
                      final attachmentName = m.attachmentName ?? 'Attachment';
                      final attachmentBytes = m.attachmentBytes;
                      return Align(
                        alignment: m.fromMe
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          constraints: const BoxConstraints(maxWidth: 290),
                          padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                          decoration: BoxDecoration(
                            color:
                                m.fromMe ? const Color(0xFFF1EEFF) : cs.surface,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                                color:
                                    cs.outlineVariant.withValues(alpha: 0.28)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              if (m.body.isNotEmpty) Text(m.body),
                              if ((m.attachmentUrl ?? '')
                                  .isNotEmpty) ...<Widget>[
                                if (m.body.isNotEmpty)
                                  const SizedBox(height: 6),
                                InkWell(
                                  onTap: isImage && attachmentUrl.isNotEmpty
                                      ? () => _threadShowImageAttachmentPreview(
                                            context,
                                            attachmentUrl,
                                            attachmentName,
                                            attachmentBytes,
                                          )
                                      : null,
                                  borderRadius: BorderRadius.circular(10),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: <Widget>[
                                      if (isImage &&
                                          attachmentUrl.isNotEmpty) ...<Widget>[
                                        ClipRRect(
                                          borderRadius:
                                              BorderRadius.circular(8),
                                          child: _threadChatAttachmentImage(
                                            bytes: attachmentBytes,
                                            url: attachmentUrl,
                                            height: 110,
                                            width: 210,
                                            fit: BoxFit.cover,
                                            errorHeight: 72,
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                      ],
                                      Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: <Widget>[
                                          Icon(
                                            isImage
                                                ? Icons.image_outlined
                                                : Icons.attach_file_rounded,
                                            size: 16,
                                          ),
                                          Flexible(
                                            child: Text(
                                              attachmentName,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
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
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: const Color(0xFF64748B)),
                ),
              ),
            ),
          Padding(
            padding: EdgeInsets.fromLTRB(
                16, 8, 16, 14 + MediaQuery.paddingOf(context).bottom),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    controller: _msgCtrl,
                    onChanged: (_) {
                      _typingDebouncer.run(() async {
                        final typing = _msgCtrl.text.trim().isNotEmpty;
                        await ref
                            .read(orderRepositoryProvider)
                            .setTyping(widget.threadId, typing: typing);
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
                    onPressed: _send, icon: const Icon(Icons.send_rounded)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String? _threadSafePlatformFilePath(PlatformFile file) {
  if (kIsWeb) return null;
  try {
    return file.path;
  } catch (_) {
    return null;
  }
}

String _threadResolveChatAttachmentUrl(String? raw, String baseUrl) {
  final value = raw?.trim() ?? '';
  if (value.isEmpty) return '';
  final uri = Uri.tryParse(value);
  if (uri != null && uri.hasScheme) return value;
  final normalizedBase = baseUrl.endsWith('/')
      ? baseUrl.substring(0, baseUrl.length - 1)
      : baseUrl;
  return value.startsWith('/')
      ? '$normalizedBase$value'
      : '$normalizedBase/$value';
}

void _threadShowImageAttachmentPreview(
  BuildContext context,
  String url,
  String title,
  Uint8List? bytes,
) {
  showDialog<void>(
    context: context,
    builder: (context) => Dialog(
      insetPadding: EdgeInsets.zero,
      backgroundColor: Colors.black,
      child: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              child: Row(
                children: <Widget>[
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded, color: Colors.white),
                  ),
                  Expanded(
                    child: Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: InteractiveViewer(
                minScale: 0.75,
                maxScale: 5,
                child: Center(
                  child: _threadChatAttachmentImage(
                    bytes: bytes,
                    url: url,
                    fit: BoxFit.contain,
                    errorHeight: 120,
                    errorColor: Colors.black,
                    errorIconColor: Colors.white,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

Widget _threadChatAttachmentImage({
  required Uint8List? bytes,
  required String url,
  required BoxFit fit,
  double? height,
  double? width,
  double errorHeight = 80,
  Color errorColor = const Color(0xFFF8FAFC),
  Color errorIconColor = Colors.black87,
}) {
  if (bytes != null) {
    return Image.memory(
      bytes,
      height: height,
      width: width,
      fit: fit,
    );
  }
  return Image.network(
    url,
    height: height,
    width: width,
    fit: fit,
    errorBuilder: (_, __, ___) => Container(
      height: errorHeight,
      width: width,
      color: errorColor,
      child: Center(
        child: Icon(Icons.broken_image_outlined, color: errorIconColor),
      ),
    ),
  );
}
