import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/returns_repository.dart';

class ReturnRequestScreen extends ConsumerStatefulWidget {
  const ReturnRequestScreen({super.key, required this.orderId});

  final int orderId;

  @override
  ConsumerState<ReturnRequestScreen> createState() =>
      _ReturnRequestScreenState();
}

class _ReturnRequestScreenState extends ConsumerState<ReturnRequestScreen> {
  static const Map<String, String> _reasons = <String, String>{
    'damaged_item': 'Item damaged',
    'wrong_item': 'Wrong item',
    'not_as_described': 'Not as described',
    'quality_issue': 'Quality issue',
  };

  final TextEditingController _notesController = TextEditingController();
  String _reasonCode = 'damaged_item';
  bool _submitting = false;
  bool _loadingEligibility = true;
  ReturnEligibilityDto? _eligibility;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_loadEligibility);
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final eligibility = _eligibility;
    final blocked = !_loadingEligibility &&
        eligibility != null &&
        eligibility.eligible == false;
    final blockedReason = eligibility?.reason ?? 'window expired';
    return Scaffold(
      appBar: AppBar(title: const Text('Request Return')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          if (_loadingEligibility) const LinearProgressIndicator(minHeight: 2),
          Text('Order #${widget.orderId}',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          if (blocked) ...<Widget>[
            const SizedBox(height: 8),
            Card(
              color: Colors.red.shade50,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  'Return is not eligible: $blockedReason.',
                ),
              ),
            ),
          ],
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _reasonCode,
            decoration: const InputDecoration(labelText: 'Reason'),
            items: _reasons.entries.map((entry) {
              return DropdownMenuItem<String>(
                  value: entry.key, child: Text(entry.value));
            }).toList(),
            onChanged: (value) =>
                setState(() => _reasonCode = value ?? _reasonCode),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesController,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'Issue details',
              hintText:
                  'Describe what happened and what resolution you expect.',
            ),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed:
                _submitting || blocked || _loadingEligibility ? null : _submit,
            child:
                Text(_submitting ? 'Submitting...' : 'Submit Return Request'),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final result = await ref.read(returnsRepositoryProvider).createReturn(
            orderId: widget.orderId,
            reasonCode: _reasonCode,
            notes: _notesController.text.trim(),
          );
      if (!mounted) return;
      context.go('/returns/${result.id}');
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not submit return request: $error')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _loadEligibility() async {
    setState(() => _loadingEligibility = true);
    try {
      final result = await ref
          .read(returnsRepositoryProvider)
          .checkEligibility(widget.orderId);
      if (!mounted) return;
      setState(() {
        _eligibility = result;
        _loadingEligibility = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingEligibility = false);
    }
  }
}
