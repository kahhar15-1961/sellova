import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../application/chat_unread_provider.dart';
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
  ConsumerState<ContactSellerChatScreen> createState() =>
      _ContactSellerChatScreenState();
}

class _ContactSellerChatScreenState
    extends ConsumerState<ContactSellerChatScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final List<ChatMessageDto> _messages = <ChatMessageDto>[];
  bool _loading = true;
  int _threadId = 0;
  Timer? _pollTimer;
  final Debouncer _typingDebouncer =
      Debouncer(duration: const Duration(milliseconds: 700));
  List<String> _typingUsers = const <String>[];
  OrderDto? _order;
  PlatformFile? _selectedFile;
  bool _sending = false;
  double? _uploadProgress;
  String? _attachmentError;
  Timer? _countdownTimer;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_bootstrap);
  }

  Future<void> _bootstrap() async {
    try {
      final repo = ref.read(orderRepositoryProvider);
      final threadId = await repo.getOrCreateChatThread(widget.orderId);
      final order = await repo.getById(widget.orderId);
      final messages = await repo.listChatMessages(threadId);
      await repo.markChatThreadRead(threadId);
      ref.invalidate(chatUnreadCountProvider);
      if (!mounted) return;
      setState(() {
        _threadId = threadId;
        _order = order;
        _messages
          ..clear()
          ..addAll(messages);
        _loading = false;
      });
      _pollTimer?.cancel();
      _pollTimer = Timer.periodic(
          const Duration(seconds: 4), (_) => _refreshMessagesSilently());
      _countdownTimer?.cancel();
      _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() {});
      });
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
      final order = await repo.getById(widget.orderId);
      await repo.markChatThreadRead(_threadId);
      ref.invalidate(chatUnreadCountProvider);
      final typingUsers = await repo.loadTypingUsers(_threadId);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(messages);
        _order = order;
        _typingUsers = typingUsers;
      });
    } catch (_) {}
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    final attachment = _selectedFile;
    if ((text.isEmpty && attachment == null) || _threadId <= 0 || _sending) {
      return;
    }
    final repo = ref.read(orderRepositoryProvider);
    setState(() {
      _sending = true;
      _attachmentError = null;
      _uploadProgress = attachment == null ? null : 0;
    });
    try {
      final ChatMessageDto created;
      if (attachment != null) {
        created = await repo.sendChatAttachment(
          threadId: _threadId,
          filePath: _safePlatformFilePath(attachment),
          fileBytes: attachment.bytes,
          fileName: attachment.name,
          body: text.isEmpty ? null : text,
          isDeliveryProof: _order?.usesProofDelivery == true,
          artifactType:
              _order?.usesProofDelivery == true ? 'delivery_proof' : null,
          onSendProgress: (sent, total) {
            if (!mounted || total <= 0) return;
            setState(() => _uploadProgress = sent / total);
          },
        );
      } else {
        created = await repo.sendChatMessage(_threadId, text);
      }
      await repo.setTyping(_threadId, typing: false);
      if (!mounted) return;
      setState(() {
        _messages.add(created);
        _msgCtrl.clear();
        _selectedFile = null;
        _uploadProgress = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _attachmentError =
          'Upload failed. Please check the file and try again.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickAndSendAttachment() async {
    final picked = await FilePicker.platform.pickFiles(
      withData: true,
      allowMultiple: false,
      type: FileType.custom,
      allowedExtensions: const <String>[
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'pdf',
        'txt',
        'doc',
        'docx',
        'zip'
      ],
    );
    final file =
        (picked != null && picked.files.isNotEmpty) ? picked.files.first : null;
    if (file == null) return;
    if (_safePlatformFilePath(file) == null && file.bytes == null) {
      setState(() => _attachmentError = 'Unable to read this file.');
      return;
    }
    if ((file.size) > 10 * 1024 * 1024) {
      setState(() => _attachmentError = 'Attachment must be 10 MB or smaller.');
      return;
    }
    setState(() {
      _selectedFile = file;
      _attachmentError = null;
      _uploadProgress = null;
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _countdownTimer?.cancel();
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
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
              return;
            }
            context.go('/orders/${widget.orderId}');
          },
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(widget.title),
            Text(
              'Order #${widget.orderId}',
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: const Color(0xFF64748B)),
            ),
          ],
        ),
        actions: <Widget>[
          IconButton(
            icon: const Icon(Icons.receipt_long_outlined),
            tooltip: 'View order details',
            onPressed: () => context.push('/orders/${widget.orderId}'),
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            if (_order != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: _EscrowTimerBanner(order: _order!),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: InkWell(
                onTap: () => context.push('/orders/${widget.orderId}'),
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: cs.surface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: cs.outlineVariant.withValues(alpha: 0.35)),
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
                        child: const Icon(Icons.receipt_long_rounded, size: 28),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'View order details',
                              style: Theme.of(context)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Open the order linked to this conversation',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: const Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right_rounded),
                    ],
                  ),
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
                              child: Text('01 June 2025',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(
                                          color: const Color(0xFF64748B))),
                            ),
                          );
                        }
                        final m = _messages[index - 1];
                        return Align(
                          alignment: m.fromMe
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            constraints: const BoxConstraints(maxWidth: 280),
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                            decoration: BoxDecoration(
                              color: m.fromMe
                                  ? const Color(0xFFF1EEFF)
                                  : cs.surface,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                  color: cs.outlineVariant
                                      .withValues(alpha: 0.28)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                if (m.body.isNotEmpty)
                                  Text(m.body,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(height: 1.35)),
                                if ((m.attachmentUrl ?? '')
                                    .isNotEmpty) ...<Widget>[
                                  if (m.body.isNotEmpty)
                                    const SizedBox(height: 6),
                                  _ChatAttachmentBubble(
                                    message: m,
                                    baseUrl: ref.watch(baseUrlProvider),
                                  ),
                                ],
                                if ((m.markerType ?? '')
                                    .isNotEmpty) ...<Widget>[
                                  if (m.body.isNotEmpty ||
                                      (m.attachmentUrl ?? '').isNotEmpty)
                                    const SizedBox(height: 6),
                                  Text(
                                    _markerLabel(m.markerType!),
                                    style: Theme.of(context)
                                        .textTheme
                                        .labelSmall
                                        ?.copyWith(
                                          color: const Color(0xFF1D4ED8),
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                ],
                                const SizedBox(height: 6),
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: <Widget>[
                                    Text(
                                      m.createdAt.length >= 16
                                          ? m.createdAt.substring(11, 16)
                                          : 'Now',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                              color: const Color(0xFF64748B)),
                                    ),
                                    if (m.fromMe &&
                                        m.deliveryStatus == 'read') ...<Widget>[
                                      const SizedBox(width: 4),
                                      const Icon(Icons.done_all_rounded,
                                          size: 16, color: Color(0xFF4F46E5)),
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
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  if (_selectedFile != null ||
                      _attachmentError != null ||
                      _uploadProgress != null)
                    _AttachmentComposerPreview(
                      file: _selectedFile,
                      error: _attachmentError,
                      progress: _uploadProgress,
                      onRemove: _sending
                          ? null
                          : () => setState(() {
                                _selectedFile = null;
                                _attachmentError = null;
                                _uploadProgress = null;
                              }),
                    ),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: TextField(
                          controller: _msgCtrl,
                          enabled: !_sending,
                          onChanged: (_) {
                            _typingDebouncer.run(() async {
                              if (_threadId <= 0) return;
                              final typing = _msgCtrl.text.trim().isNotEmpty;
                              await ref
                                  .read(orderRepositoryProvider)
                                  .setTyping(_threadId, typing: typing);
                            });
                          },
                          decoration: InputDecoration(
                            hintText: 'Type a message...',
                            prefixIcon: IconButton(
                              onPressed:
                                  _sending ? null : _pickAndSendAttachment,
                              icon: const Icon(Icons.attach_file_rounded),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      IconButton.filled(
                        onPressed: _sending ? null : _send,
                        icon: _sending
                            ? const SizedBox.square(
                                dimension: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.send_rounded),
                      ),
                    ],
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

String _markerLabel(String marker) => switch (marker) {
      'fulfillment_started' => 'Seller started fulfillment',
      'delivery_submitted' => 'Digital delivery submitted',
      'instant_delivery_logged' => 'Instant delivery logged',
      'service_completed' => 'Service work submitted',
      _ => marker.replaceAll('_', ' '),
    };

class _EscrowTimerBanner extends StatelessWidget {
  const _EscrowTimerBanner({required this.order});

  final OrderDto order;

  @override
  Widget build(BuildContext context) {
    final timer = order.timeoutState;
    final active = (timer['active_timer'] ?? '').toString();
    final nextAt =
        DateTime.tryParse((timer['next_event_at'] ?? '').toString())?.toLocal();
    final remaining = nextAt?.difference(DateTime.now());
    final label = switch (active) {
      'buyer_review' => 'Buyer review expires',
      'seller_fulfillment_deadline' => 'Seller delivery deadline',
      'unpaid_order_expiration' => 'Payment expires',
      _ => 'Escrow timer',
    };
    final action =
        (timer['expiry_action'] ?? '').toString().replaceAll('_', ' ');
    final cs = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFBFDBFE)),
      ),
      child: Row(
        children: <Widget>[
          Icon(Icons.timer_outlined, color: cs.primary),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  remaining == null
                      ? label
                      : '$label in ${_formatDuration(remaining)}',
                  style: Theme.of(context)
                      .textTheme
                      .titleSmall
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                if (action.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 2),
                  Text(
                    'Next action: $action',
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: const Color(0xFF64748B)),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String _formatDuration(Duration duration) {
  if (duration.isNegative) return '0m';
  final hours = duration.inHours;
  final minutes = duration.inMinutes.remainder(60);
  if (hours >= 24) {
    return '${hours ~/ 24}d ${hours.remainder(24)}h';
  }
  if (hours > 0) return '${hours}h ${minutes}m';
  return '${minutes}m';
}

class _ChatAttachmentBubble extends StatelessWidget {
  const _ChatAttachmentBubble({
    required this.message,
    required this.baseUrl,
  });

  final ChatMessageDto message;
  final String baseUrl;

  @override
  Widget build(BuildContext context) {
    final isImage = message.attachmentType == 'image' ||
        message.attachmentMime.startsWith('image/');
    final url = _resolveChatAttachmentUrl(message.attachmentUrl, baseUrl);
    final name = message.attachmentName ?? 'Attachment';
    final bytes = message.attachmentBytes;
    return Container(
      constraints: const BoxConstraints(minWidth: 170),
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.65),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (isImage && url.isNotEmpty) ...<Widget>[
            InkWell(
              onTap: () =>
                  _showImageAttachmentPreview(context, url, name, bytes),
              borderRadius: BorderRadius.circular(8),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Stack(
                  alignment: Alignment.bottomRight,
                  children: <Widget>[
                    _chatAttachmentImage(
                      bytes: bytes,
                      url: url,
                      height: 130,
                      width: 220,
                      fit: BoxFit.cover,
                      errorHeight: 80,
                    ),
                    Container(
                      margin: const EdgeInsets.all(6),
                      padding: const EdgeInsets.all(5),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.58),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: const Icon(
                        Icons.zoom_out_map_rounded,
                        color: Colors.white,
                        size: 14,
                      ),
                    ),
                  ],
                ),
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
                      : Icons.insert_drive_file_outlined,
                  size: 16),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  name,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

String _resolveChatAttachmentUrl(String? raw, String baseUrl) {
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

void _showImageAttachmentPreview(
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
                  child: _chatAttachmentImage(
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

Widget _chatAttachmentImage({
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

class _AttachmentComposerPreview extends StatelessWidget {
  const _AttachmentComposerPreview({
    required this.file,
    required this.error,
    required this.progress,
    required this.onRemove,
  });

  final PlatformFile? file;
  final String? error;
  final double? progress;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final selected = file;
    final isImage = selected != null &&
        <String>{'jpg', 'jpeg', 'png', 'webp', 'gif'}
            .contains(selected.extension?.toLowerCase());
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: error == null
                ? const Color(0xFFE2E8F0)
                : const Color(0xFFFCA5A5)),
      ),
      child: Column(
        children: <Widget>[
          if (selected != null)
            Row(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: isImage
                      ? _previewImage(selected)
                      : Container(
                          width: 48,
                          height: 48,
                          color: const Color(0xFFEFF6FF),
                          child: const Icon(Icons.insert_drive_file_outlined),
                        ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    selected.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.close_rounded),
                  tooltip: 'Remove attachment',
                ),
              ],
            ),
          if (progress != null) ...<Widget>[
            const SizedBox(height: 8),
            LinearProgressIndicator(value: progress!.clamp(0, 1)),
          ],
          if (error != null) ...<Widget>[
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(error!,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: const Color(0xFFB91C1C))),
            ),
          ],
        ],
      ),
    );
  }
}

Widget _previewImage(PlatformFile file) {
  final bytes = file.bytes;
  if (bytes != null) {
    return Image.memory(bytes, width: 48, height: 48, fit: BoxFit.cover);
  }
  final path = _safePlatformFilePath(file);
  if (path != null) {
    return Image.file(File(path), width: 48, height: 48, fit: BoxFit.cover);
  }
  return Container(
    width: 48,
    height: 48,
    color: const Color(0xFFEFF6FF),
    child: const Icon(Icons.image_outlined),
  );
}

String? _safePlatformFilePath(PlatformFile file) {
  if (kIsWeb) return null;
  try {
    return file.path;
  } catch (_) {
    return null;
  }
}
