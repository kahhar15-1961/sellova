import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/app_providers.dart';
import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../../orders/application/chat_unread_provider.dart';
import '../../orders/data/order_repository.dart';
import '../application/seller_demo_controller.dart';
import 'seller_ui.dart';

class SellerOrderChatScreen extends ConsumerStatefulWidget {
  const SellerOrderChatScreen({super.key, required this.orderId});
  final int orderId;

  @override
  ConsumerState<SellerOrderChatScreen> createState() =>
      _SellerOrderChatScreenState();
}

class _SellerOrderChatScreenState extends ConsumerState<SellerOrderChatScreen> {
  final TextEditingController _input = TextEditingController();
  final Debouncer _typingDebouncer =
      Debouncer(duration: const Duration(milliseconds: 700));
  final List<ChatMessageDto> _messages = <ChatMessageDto>[];

  bool _loading = true;
  bool _sending = false;
  PlatformFile? _selectedFile;
  double? _uploadProgress;
  String? _attachmentError;
  int _threadId = 0;
  Timer? _pollTimer;
  Timer? _countdownTimer;
  List<String> _typingUsers = const <String>[];
  OrderDto? _order;
  bool _submittingDelivery = false;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_bootstrap);
  }

  Future<void> _bootstrap() async {
    try {
      final repo = ref.read(orderRepositoryProvider);
      try {
        _order = await repo.getById(widget.orderId);
      } catch (_) {}

      final threadId = await repo.getOrCreateChatThread(widget.orderId);
      final messages = await repo.listChatMessages(threadId);
      await repo.markChatThreadRead(threadId);
      ref.invalidate(chatUnreadCountProvider);
      final typingUsers = await repo.loadTypingUsers(threadId);

      if (!mounted) return;
      setState(() {
        _threadId = threadId;
        _messages
          ..clear()
          ..addAll(messages);
        _typingUsers = typingUsers;
        _loading = false;
      });

      _pollTimer?.cancel();
      _pollTimer = Timer.periodic(
        const Duration(seconds: 4),
        (_) => _refreshSilently(),
      );
      _countdownTimer?.cancel();
      _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) {
          setState(() {});
        }
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
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _typingUsers = const <String>[];
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Chat unavailable: $e')),
      );
    }
  }

  Future<void> _refreshSilently() async {
    if (!mounted || _threadId <= 0) return;
    try {
      final repo = ref.read(orderRepositoryProvider);
      final messages = await repo.listChatMessages(_threadId);
      await repo.markChatThreadRead(_threadId);
      ref.invalidate(chatUnreadCountProvider);
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
    await _sendPendingChatContent();
  }

  Future<void> _sendPendingChatContent(
      {bool forceDeliveryProof = false}) async {
    final text = _input.text.trim();
    final attachment = _selectedFile;
    if ((text.isEmpty && attachment == null) || _threadId <= 0 || _sending) {
      return;
    }

    setState(() {
      _sending = true;
      _attachmentError = null;
      _uploadProgress = attachment == null ? null : 0;
    });
    try {
      final repo = ref.read(orderRepositoryProvider);
      final ChatMessageDto created;
      if (attachment != null) {
        created = await repo.sendChatAttachment(
          threadId: _threadId,
          filePath: _sellerSafePlatformFilePath(attachment),
          fileBytes: attachment.bytes,
          fileName: attachment.name,
          body: text.isEmpty ? null : text,
          isDeliveryProof:
              forceDeliveryProof || _order?.usesProofDelivery == true,
          artifactType: forceDeliveryProof || _order?.usesProofDelivery == true
              ? 'delivery_proof'
              : null,
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
        _input.clear();
        _selectedFile = null;
        _uploadProgress = null;
      });
      HapticFeedback.lightImpact();
    } catch (e) {
      if (!mounted) return;
      setState(() => _attachmentError = 'Send failed. Please try again.');
      rethrow;
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  bool get _canSubmitDeliveryFromChat {
    final order = _order;
    if (order == null || !order.usesProofDelivery) {
      return false;
    }
    final status = order.status.toLowerCase();
    final fulfillment =
        (order.raw['fulfillment_state'] ?? '').toString().toLowerCase();
    return !<String>{
          'completed',
          'refunded',
          'cancelled',
          'disputed',
          'delivery_submitted',
          'buyer_review',
        }.contains(status) &&
        !<String>{
          'delivery_submitted',
          'buyer_review',
          'completed',
        }.contains(fulfillment);
  }

  Future<void> _submitDeliveryFromChat() async {
    if (_submittingDelivery || _order == null) {
      return;
    }
    setState(() => _submittingDelivery = true);
    try {
      if (_selectedFile != null || _input.text.trim().isNotEmpty) {
        await _sendPendingChatContent(forceDeliveryProof: true);
      }
      await ref.read(sellerOrdersProvider.notifier).submitDigitalDelivery(
            orderId: widget.orderId,
            note: 'Delivery submitted from escrow chat.',
          );
      final fresh =
          await ref.read(orderRepositoryProvider).getById(widget.orderId);
      if (!mounted) {
        return;
      }
      setState(() => _order = fresh);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Delivery submitted. Buyer review timer has started.'),
        ),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to submit delivery: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _submittingDelivery = false);
      }
    }
  }

  Future<void> _pickAndSendAttachment() async {
    if (_sending) return;
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
    if (_sellerSafePlatformFilePath(file) == null && file.bytes == null) {
      setState(() => _attachmentError = 'Unable to read this file.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
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
    _input.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final orderNumber = _order?.orderNumber ?? '#${widget.orderId}';
    final customer = (_order?.raw['customer_name'] ??
            _order?.raw['buyer_name'] ??
            _order?.raw['buyer'] ??
            'Customer')
        .toString();
    final title = _order?.itemSummary ?? 'Order chat';
    final status = _order?.status ?? '';

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
            context.go('/seller/orders/${widget.orderId}');
          },
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              orderNumber,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            Text(
              customer,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: kSellerMuted),
            ),
          ],
        ),
        actions: <Widget>[
          IconButton(
            icon: const Icon(Icons.receipt_long_outlined),
            tooltip: 'Order details',
            onPressed: () => context.push('/seller/orders/${widget.orderId}'),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert_rounded),
            onSelected: (value) {
              if (value == 'copy') {
                Clipboard.setData(ClipboardData(text: orderNumber));
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Order number copied.')),
                );
              }
            },
            itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
              const PopupMenuItem<String>(
                value: 'copy',
                child: ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.copy_rounded),
                  title: Text('Copy order number'),
                ),
              ),
            ],
          ),
        ],
      ),
      body: Column(
        children: <Widget>[
          if (_order != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: _SellerEscrowTimerBanner(order: _order!),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: cs.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                    color: cs.outlineVariant.withValues(alpha: 0.35)),
                boxShadow: const <BoxShadow>[
                  BoxShadow(
                    color: Color(0x0D0F172A),
                    blurRadius: 24,
                    offset: Offset(0, 10),
                  ),
                ],
              ),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.shopping_bag_outlined,
                        color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          title,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Order #${widget.orderId}',
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall
                              ?.copyWith(color: kSellerMuted),
                        ),
                      ],
                    ),
                  ),
                  if (status.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        status,
                        style: const TextStyle(
                          color: Color(0xFF1D4ED8),
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (_loading)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else if (_messages.isEmpty)
            Expanded(
              child: Center(
                child: Text(
                  'No messages yet.',
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(color: kSellerMuted),
                ),
              ),
            )
          else
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
                itemCount: _messages.length + (_typingUsers.isNotEmpty ? 1 : 0),
                itemBuilder: (BuildContext context, int index) {
                  if (index == _messages.length) {
                    return Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        '${_typingUsers.first} is typing...',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted),
                      ),
                    );
                  }

                  final m = _messages[index];
                  final fromMe = m.fromMe;
                  return Align(
                    alignment:
                        fromMe ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      constraints: BoxConstraints(
                        maxWidth: MediaQuery.sizeOf(context).width * 0.78,
                      ),
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                      decoration: BoxDecoration(
                        color: fromMe ? const Color(0xFFF1EEFF) : cs.surface,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: cs.outlineVariant.withValues(alpha: 0.28),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          if (m.body.isNotEmpty)
                            Text(
                              m.body,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(height: 1.35),
                            ),
                          if ((m.attachmentUrl ?? '').isNotEmpty) ...<Widget>[
                            if (m.body.isNotEmpty) const SizedBox(height: 6),
                            _SellerChatAttachment(
                              message: m,
                              baseUrl: ref.watch(baseUrlProvider),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              8,
              16,
              14 + MediaQuery.paddingOf(context).bottom,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                if (_selectedFile != null ||
                    _attachmentError != null ||
                    _uploadProgress != null)
                  _SellerAttachmentPreview(
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
                if (_canSubmitDeliveryFromChat) ...<Widget>[
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFBFDBFE)),
                    ),
                    child: Row(
                      children: <Widget>[
                        const Icon(Icons.verified_outlined,
                            color: Color(0xFF1D4ED8)),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Send files, credentials, or proof here, then submit delivery for buyer review.',
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: const Color(0xFF1E3A8A),
                                      fontWeight: FontWeight.w700,
                                    ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        FilledButton(
                          onPressed: _submittingDelivery
                              ? null
                              : _submitDeliveryFromChat,
                          child: _submittingDelivery
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child:
                                      CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Text('Submit'),
                        ),
                      ],
                    ),
                  ),
                ],
                Row(
                  children: <Widget>[
                    Expanded(
                      child: TextField(
                        controller: _input,
                        enabled: !_sending,
                        onChanged: (_) {
                          _typingDebouncer.run(() async {
                            final typing = _input.text.trim().isNotEmpty;
                            if (_threadId > 0) {
                              await ref.read(orderRepositoryProvider).setTyping(
                                    _threadId,
                                    typing: typing,
                                  );
                            }
                          });
                        },
                        decoration: InputDecoration(
                          hintText: 'Type a message...',
                          prefixIcon: IconButton(
                            onPressed: _sending ? null : _pickAndSendAttachment,
                            icon: const Icon(Icons.attach_file_rounded),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    IconButton.filled(
                      onPressed: _sending ? null : _send,
                      icon: _sending
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
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
    );
  }
}

class _SellerEscrowTimerBanner extends StatelessWidget {
  const _SellerEscrowTimerBanner({required this.order});

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
          const Icon(Icons.timer_outlined, color: Color(0xFF1D4ED8)),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  remaining == null
                      ? label
                      : '$label in ${_sellerFormatDuration(remaining)}',
                  style: Theme.of(context)
                      .textTheme
                      .titleSmall
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                if (action.isNotEmpty)
                  Text(
                    'Next action: $action',
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: kSellerMuted),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SellerChatAttachment extends StatelessWidget {
  const _SellerChatAttachment({
    required this.message,
    required this.baseUrl,
  });

  final ChatMessageDto message;
  final String baseUrl;

  @override
  Widget build(BuildContext context) {
    final isImage = message.attachmentType == 'image' ||
        message.attachmentMime.startsWith('image/');
    final url = _sellerResolveChatAttachmentUrl(message.attachmentUrl, baseUrl);
    final name = message.attachmentName ?? 'Attachment';
    final bytes = message.attachmentBytes;
    return Container(
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
                  _sellerShowImageAttachmentPreview(context, url, name, bytes),
              borderRadius: BorderRadius.circular(8),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Stack(
                  alignment: Alignment.bottomRight,
                  children: <Widget>[
                    _sellerChatAttachmentImage(
                      bytes: bytes,
                      url: url,
                      height: 120,
                      width: 220,
                      fit: BoxFit.cover,
                      errorHeight: 72,
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
              const SizedBox(width: 4),
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

String _sellerResolveChatAttachmentUrl(String? raw, String baseUrl) {
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

void _sellerShowImageAttachmentPreview(
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
                  child: _sellerChatAttachmentImage(
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

Widget _sellerChatAttachmentImage({
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

class _SellerAttachmentPreview extends StatelessWidget {
  const _SellerAttachmentPreview({
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
          color:
              error == null ? const Color(0xFFE2E8F0) : const Color(0xFFFCA5A5),
        ),
      ),
      child: Column(
        children: <Widget>[
          if (selected != null)
            Row(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: isImage
                      ? _sellerPreviewImage(selected)
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
              child: Text(
                error!,
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: const Color(0xFFB91C1C)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

Widget _sellerPreviewImage(PlatformFile file) {
  final bytes = file.bytes;
  if (bytes != null) {
    return Image.memory(bytes, width: 48, height: 48, fit: BoxFit.cover);
  }
  final path = _sellerSafePlatformFilePath(file);
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

String? _sellerSafePlatformFilePath(PlatformFile file) {
  if (kIsWeb) return null;
  try {
    return file.path;
  } catch (_) {
    return null;
  }
}

String _sellerFormatDuration(Duration duration) {
  if (duration.isNegative) return '0m';
  final hours = duration.inHours;
  final minutes = duration.inMinutes.remainder(60);
  if (hours >= 24) return '${hours ~/ 24}d ${hours.remainder(24)}h';
  if (hours > 0) return '${hours}h ${minutes}m';
  return '${minutes}m';
}
