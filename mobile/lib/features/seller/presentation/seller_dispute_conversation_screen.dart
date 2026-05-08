import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../disputes/application/dispute_detail_provider.dart';
import '../../disputes/data/dispute_repository.dart';
import '../application/seller_failure.dart';
import 'seller_ui.dart';

class SellerDisputeConversationScreen extends ConsumerStatefulWidget {
  const SellerDisputeConversationScreen({
    super.key,
    this.sellerView = false,
    this.disputeId,
  });

  final bool sellerView;
  final int? disputeId;

  @override
  ConsumerState<SellerDisputeConversationScreen> createState() =>
      _SellerDisputeConversationScreenState();
}

class _SellerDisputeConversationScreenState
    extends ConsumerState<SellerDisputeConversationScreen> {
  final TextEditingController _message = TextEditingController();
  final List<String> _attachments = <String>[];
  final List<_Entry> _entries = <_Entry>[];
  bool _sending = false;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _attachFile() async {
    final result = await FilePicker.platform
        .pickFiles(allowMultiple: false, withData: false);
    final path = result?.files.single.path;
    if (path == null) {
      return;
    }
    setState(() => _attachments.add(path));
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('File added.')),
      );
    }
  }

  Future<void> _send(BuildContext context, DisputeDto? dispute) async {
    final text = _message.text.trim();
    final messenger = ScaffoldMessenger.of(context);
    if (text.isEmpty && _attachments.isEmpty) {
      messenger
          .showSnackBar(const SnackBar(content: Text('Add a note or file.')));
      return;
    }
    if (_sending) return;

    setState(() => _sending = true);
    try {
      final disputeId = dispute?.id;
      if (disputeId != null) {
        final disputeRepo = ref.read(disputeRepositoryProvider);
        final evidence = <Map<String, dynamic>>[];
        if (text.isNotEmpty) {
          evidence.add(<String, dynamic>{
            'evidence_type': 'text',
            'content_text': text,
          });
        }
        for (final path in _attachments) {
          final url = await disputeRepo.uploadEvidenceFile(
            disputeCaseId: disputeId,
            filePath: path,
          );
          evidence.add(<String, dynamic>{
            'evidence_type': 'file',
            'storage_path': url,
          });
        }
        await disputeRepo.submitEvidence(
          disputeCaseId: disputeId,
          evidence: evidence,
        );
        ref.invalidate(disputeDetailProvider(disputeId));
        if (!mounted) return;
        setState(() {
          _entries.add(
            _Entry(
              sender: widget.sellerView ? 'Seller' : 'You',
              text: text.isEmpty ? 'Attachment sent.' : text,
              time: _nowLabel(),
              admin: false,
            ),
          );
          _message.clear();
          _attachments.clear();
        });
        HapticFeedback.mediumImpact();
        messenger.showSnackBar(const SnackBar(content: Text('Update sent.')));
      } else {
        final ticketId =
            await ref.read(orderRepositoryProvider).createSupportTicket(
                  subject: 'Dispute update',
                  message: text.isEmpty ? 'Attachment submitted.' : text,
                );
        if (!mounted) return;
        setState(() {
          _entries.add(
            _Entry(
              sender: 'Support',
              text: 'Ticket #$ticketId created.',
              time: _nowLabel(),
              admin: true,
            ),
          );
          _message.clear();
          _attachments.clear();
        });
        messenger.showSnackBar(
            SnackBar(content: Text('Ticket #$ticketId created.')));
      }
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text('Send failed: $e')));
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final disputeAsync = widget.disputeId == null
        ? null
        : ref.watch(disputeDetailProvider(widget.disputeId!));

    Widget body;
    if (disputeAsync == null) {
      body = _ConversationBody(
        dispute: null,
        message: _message,
        attachments: _attachments,
        entries: _entries,
        sending: _sending,
        onAttach: _sending ? null : _attachFile,
        onRemoveAttachment: (index) {
          setState(() => _attachments.removeAt(index));
        },
        onSend: () => _send(context, null),
      );
    } else {
      body = disputeAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  'Load failed',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(
                  SellerFailure.from(e).message,
                  textAlign: TextAlign.center,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted),
                ),
              ],
            ),
          ),
        ),
        data: (dispute) => _ConversationBody(
          dispute: dispute,
          message: _message,
          attachments: _attachments,
          entries: _entries,
          sending: _sending,
          onAttach: _sending ? null : _attachFile,
          onRemoveAttachment: (index) {
            setState(() => _attachments.removeAt(index));
          },
          onSend: () => _send(context, dispute),
          onOpenRespond: dispute.id == null
              ? null
              : () => context.push('/seller/disputes/${dispute.id}/respond'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Text(widget.disputeId == null
            ? 'Dispute update'
            : 'Dispute #${widget.disputeId}'),
        actions: <Widget>[
          IconButton(
            icon: const Icon(Icons.support_agent_rounded),
            tooltip: 'Support',
            onPressed: () => context.push('/seller/help-support'),
          ),
        ],
      ),
      body: body,
    );
  }
}

class _ConversationBody extends StatelessWidget {
  const _ConversationBody({
    required this.dispute,
    required this.message,
    required this.attachments,
    required this.entries,
    required this.sending,
    required this.onAttach,
    required this.onRemoveAttachment,
    required this.onSend,
    this.onOpenRespond,
  });

  final DisputeDto? dispute;
  final TextEditingController message;
  final List<String> attachments;
  final List<_Entry> entries;
  final bool sending;
  final VoidCallback? onAttach;
  final void Function(int index) onRemoveAttachment;
  final VoidCallback onSend;
  final VoidCallback? onOpenRespond;

  @override
  Widget build(BuildContext context) {
    final currentDispute = dispute;
    final cs = Theme.of(context).colorScheme;
    final title = dispute == null
        ? 'Add a short update and attach any files.'
        : 'Dispute #${currentDispute!.id ?? 'unknown'}';
    final subtitle = dispute == null
        ? null
        : (currentDispute!.raw['summary'] ??
                currentDispute.raw['description'] ??
                '')
            .toString();
    final orderId = currentDispute?.orderId;

    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: cs.surface,
              borderRadius: BorderRadius.circular(16),
              border:
                  Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
              boxShadow: const <BoxShadow>[
                BoxShadow(
                  color: Color(0x0D0F172A),
                  blurRadius: 24,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.receipt_long_rounded),
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
                          if (subtitle != null &&
                              subtitle.trim().isNotEmpty) ...<Widget>[
                            const SizedBox(height: 2),
                            Text(
                              subtitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(color: kSellerMuted),
                            ),
                          ],
                        ],
                      ),
                    ),
                    if (orderId != null)
                      TextButton(
                        onPressed: () =>
                            context.push('/seller/orders/$orderId'),
                        child: const Text('Order'),
                      ),
                  ],
                ),
                if (dispute != null) ...<Widget>[
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: <Widget>[
                      _MiniChip(label: currentDispute!.status),
                      if (orderId != null) _MiniChip(label: 'ORD-$orderId'),
                    ],
                  ),
                ],
                if (onOpenRespond != null) ...<Widget>[
                  const SizedBox(height: 10),
                  OutlinedButton(
                    onPressed: onOpenRespond,
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(44),
                    ),
                    child: const Text('Open response'),
                  ),
                ],
              ],
            ),
          ),
        ),
        if (entries.isEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Ready to send.',
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: kSellerMuted),
              ),
            ),
          ),
        Expanded(
          child: entries.isEmpty
              ? const SizedBox.expand()
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                  itemCount: entries.length,
                  itemBuilder: (BuildContext context, int index) {
                    final entry = entries[index];
                    return Align(
                      alignment: entry.admin
                          ? Alignment.centerLeft
                          : Alignment.centerRight,
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        constraints: BoxConstraints(
                          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
                        ),
                        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                        decoration: BoxDecoration(
                          color: entry.admin
                              ? const Color(0xFFFFF7ED)
                              : const Color(0xFFF1EEFF),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: cs.outlineVariant.withValues(alpha: 0.28),
                          ),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              entry.sender,
                              style: Theme.of(context)
                                  .textTheme
                                  .labelLarge
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              entry.text,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(height: 1.35),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              entry.time,
                              style: Theme.of(context)
                                  .textTheme
                                  .labelSmall
                                  ?.copyWith(color: kSellerMuted),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
        if (attachments.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: attachments
                    .asMap()
                    .entries
                    .map(
                      (entry) => InputChip(
                        label: Text(
                          entry.value.split('/').last,
                          overflow: TextOverflow.ellipsis,
                        ),
                        onDeleted: () => onRemoveAttachment(entry.key),
                        deleteIcon: const Icon(Icons.close_rounded, size: 16),
                      ),
                    )
                    .toList(),
              ),
            ),
          ),
        Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            8,
            16,
            14 + MediaQuery.paddingOf(context).bottom,
          ),
          child: Row(
            children: <Widget>[
              IconButton(
                onPressed: onAttach,
                icon: const Icon(Icons.attach_file_rounded),
                tooltip: 'Add file',
              ),
              Expanded(
                child: TextField(
                  controller: message,
                  minLines: 1,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    hintText: 'Type a note...',
                  ),
                ),
              ),
              const SizedBox(width: 10),
              IconButton.filled(
                onPressed: sending ? null : onSend,
                icon: sending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_rounded),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MiniChip extends StatelessWidget {
  const _MiniChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Color(0xFF1D4ED8),
          fontWeight: FontWeight.w800,
          fontSize: 11,
        ),
      ),
    );
  }
}

class _Entry {
  const _Entry({
    required this.sender,
    required this.text,
    required this.time,
    required this.admin,
  });

  final String sender;
  final String text;
  final String time;
  final bool admin;
}

String _nowLabel() {
  final now = DateTime.now();
  final hour = now.hour % 12 == 0 ? 12 : now.hour % 12;
  final minute = now.minute.toString().padLeft(2, '0');
  final suffix = now.hour >= 12 ? 'PM' : 'AM';
  return '$hour:$minute $suffix';
}
