import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/telemetry/telemetry.dart';
import '../../../core/util/debouncer.dart';
import '../../disputes/application/dispute_detail_provider.dart';
import '../application/seller_failure.dart';
import '../data/seller_form_draft_store.dart';
import 'seller_ui.dart';

class SellerRespondDisputeScreen extends ConsumerStatefulWidget {
  const SellerRespondDisputeScreen({super.key, required this.disputeId});
  final int disputeId;

  @override
  ConsumerState<SellerRespondDisputeScreen> createState() => _SellerRespondDisputeScreenState();
}

class _SellerRespondDisputeScreenState extends ConsumerState<SellerRespondDisputeScreen> {
  final TextEditingController _response = TextEditingController();
  final List<String> _uploadedUrls = <String>[];
  bool _busy = false;
  bool _draftChecked = false;
  final Debouncer _draftDebouncer = Debouncer(duration: const Duration(milliseconds: 400));

  @override
  void initState() {
    super.initState();
    _response.addListener(() {
      _draftDebouncer.run(() {
        ref.read(sellerFormDraftStoreProvider).saveRespondDisputeDraft(widget.disputeId, <String, dynamic>{
          'text': _response.text,
          'urls': List<String>.from(_uploadedUrls),
        });
      });
    });
  }

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _response.dispose();
    super.dispose();
  }

  Future<void> _pickAndUpload() async {
    final result = await FilePicker.platform.pickFiles(allowMultiple: false, withData: false);
    final path = result?.files.single.path;
    if (path == null) {
      return;
    }
    setState(() => _busy = true);
    ref.read(telemetryProvider).record('seller.dispute.respond.upload_attempt', <String, Object?>{'dispute_id': widget.disputeId});
    try {
      final url = await ref.read(disputeRepositoryProvider).uploadEvidenceFile(
            disputeCaseId: widget.disputeId,
            filePath: path,
          );
      if (!mounted) return;
      setState(() {
        _uploadedUrls.add(url);
        _busy = false;
      });
      ref.read(sellerFormDraftStoreProvider).saveRespondDisputeDraft(widget.disputeId, <String, dynamic>{
        'text': _response.text,
        'urls': List<String>.from(_uploadedUrls),
      });
      ref.read(telemetryProvider).record('seller.dispute.respond.upload_success', <String, Object?>{'dispute_id': widget.disputeId});
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('File uploaded.')));
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ref.read(telemetryProvider).record('seller.dispute.respond.upload_failed', <String, Object?>{'dispute_id': widget.disputeId});
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(SellerFailure.from(e).message)));
    }
  }

  Future<void> _submit() async {
    if (_response.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please add a response.')));
      return;
    }
    setState(() => _busy = true);
    ref.read(telemetryProvider).record('seller.dispute.respond.submit_attempt', <String, Object?>{'dispute_id': widget.disputeId});
    try {
      final evidence = <Map<String, dynamic>>[
        <String, dynamic>{
          'evidence_type': 'text',
          'content_text': _response.text.trim(),
        },
        ..._uploadedUrls.map((String u) => <String, dynamic>{'evidence_type': 'image', 'storage_path': u}),
      ];
      await ref.read(disputeRepositoryProvider).submitEvidence(
            disputeCaseId: widget.disputeId,
            evidence: evidence,
          );
      await ref.read(sellerFormDraftStoreProvider).clearRespondDisputeDraft(widget.disputeId);
      ref.invalidate(disputeDetailProvider(widget.disputeId));
      ref.read(telemetryProvider).record('seller.dispute.respond.submit_success', <String, Object?>{'dispute_id': widget.disputeId});
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Response submitted successfully.')));
      context.pop();
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ref.read(telemetryProvider).record('seller.dispute.respond.submit_failed', <String, Object?>{'dispute_id': widget.disputeId});
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(SellerFailure.from(e).message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_draftChecked) {
      _draftChecked = true;
      final draft = ref.read(sellerFormDraftStoreProvider).loadRespondDisputeDraft(widget.disputeId);
      if (draft != null) {
        final t = draft['text']?.toString();
        if (t != null && t.isNotEmpty) {
          _response.text = t;
        }
        final urls = draft['urls'];
        if (urls is List) {
          _uploadedUrls
            ..clear()
            ..addAll(urls.map((e) => e.toString()));
        }
      }
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Respond to Dispute'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        children: <Widget>[
          Text('Add Response', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          TextField(
            controller: _response,
            maxLines: 6,
            decoration: const InputDecoration(
              hintText: 'Type your response to the customer...',
              alignLabelWithHint: true,
              border: OutlineInputBorder(),
            ),
          ),
          if (_uploadedUrls.isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            Text('Attachments (${_uploadedUrls.length})', style: Theme.of(context).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800)),
            ..._uploadedUrls.map((u) => Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: SelectableText(u, style: Theme.of(context).textTheme.bodySmall),
                )),
          ],
          const SizedBox(height: 22),
          Text('Upload Evidence (Optional)', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          InkWell(
            onTap: _busy ? null : _pickAndUpload,
            borderRadius: BorderRadius.circular(14),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 36),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: kSellerAccent.withValues(alpha: 0.45), width: 1.2),
              ),
              child: Column(
                children: <Widget>[
                  Icon(Icons.cloud_upload_outlined, size: 40, color: kSellerAccent.withValues(alpha: 0.9)),
                  const SizedBox(height: 10),
                  const Text('Tap to upload', style: TextStyle(color: kSellerAccent, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text('or drag and drop', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 28),
          FilledButton(
            onPressed: _busy ? null : _submit,
            style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(54)),
            child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Submit Response'),
          ),
        ],
      ),
    );
  }
}
