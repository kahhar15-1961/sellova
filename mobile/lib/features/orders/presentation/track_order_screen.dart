import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'dart:async';

import '../../../app/providers/repository_providers.dart';
import '../data/order_repository.dart';

class TrackOrderScreen extends ConsumerStatefulWidget {
  const TrackOrderScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  ConsumerState<TrackOrderScreen> createState() => _TrackOrderScreenState();
}

class _TrackOrderScreenState extends ConsumerState<TrackOrderScreen> {
  OrderTrackingDto? _tracking;
  bool _loading = true;
  String? _error;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_load);
    _refreshTimer = Timer.periodic(const Duration(seconds: 10), (_) => _load(silent: true));
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final data = await ref.read(orderRepositoryProvider).getTracking(widget.orderId);
      if (!mounted) return;
      setState(() {
        _tracking = data;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = _tracking;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: Text('Order #${widget.orderId}')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(child: Text('Failed to load tracking: $_error'))
                  : Column(
            children: <Widget>[
              if (t != null)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Carrier: ${t.carrierName}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 4),
                      Row(
                        children: <Widget>[
                          Expanded(child: Text('Tracking ID: ${t.trackingId}')),
                          TextButton(
                            onPressed: () async {
                              await Clipboard.setData(ClipboardData(text: t.trackingId));
                              if (!context.mounted) return;
                              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tracking ID copied')));
                            },
                            child: const Text('Copy'),
                          ),
                        ],
                      ),
                      if (t.eta.isNotEmpty) Text('ETA: ${t.eta.substring(0, 10)}'),
                      if (t.trackingUrl.isNotEmpty)
                        TextButton(
                          onPressed: () async {
                            await Clipboard.setData(ClipboardData(text: t.trackingUrl));
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tracking URL copied')));
                          },
                          child: const Text('Copy tracking URL'),
                        ),
                    ],
                  ),
                ),
              Expanded(
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surface,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.35)),
                  ),
                  child: Column(
                    children: <Widget>[
                      for (var i = 0; i < (t?.timeline.length ?? 0); i++)
                        _TrackRow(
                          done: ((t!.timeline[i]['at'] ?? '').toString().isNotEmpty),
                          active: i == ((t.timeline.indexWhere((e) => ((e['at'] ?? '').toString().isEmpty)) == -1)
                              ? t.timeline.length - 1
                              : (t.timeline.indexWhere((e) => ((e['at'] ?? '').toString().isEmpty)) - 1)),
                          title: (t.timeline[i]['title'] ?? '').toString(),
                          subtitle: ((t.timeline[i]['at'] ?? '').toString().isEmpty)
                              ? 'Pending'
                              : (t.timeline[i]['at'] as String).substring(0, 16).replaceFirst('T', ' '),
                          showLine: i < (t.timeline.length - 1),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => context.push('/orders/${widget.orderId}/chat'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: const Text('Contact Seller'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: () => context.push('/disputes/create?orderId=${widget.orderId}'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: const Text('Open Dispute'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TrackRow extends StatelessWidget {
  const _TrackRow({
    required this.done,
    required this.active,
    required this.title,
    required this.subtitle,
    required this.showLine,
  });

  final bool done;
  final bool active;
  final String title;
  final String subtitle;
  final bool showLine;

  @override
  Widget build(BuildContext context) {
    final color = done ? const Color(0xFF22C55E) : (active ? const Color(0xFF4F46E5) : const Color(0xFF94A3B8));
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 26,
            child: Column(
              children: <Widget>[
                Container(
                  width: 18,
                  height: 18,
                  decoration: BoxDecoration(
                    color: done || active ? color : Colors.transparent,
                    border: Border.all(color: color, width: 2),
                    shape: BoxShape.circle,
                  ),
                  child: done ? const Icon(Icons.check, color: Colors.white, size: 12) : null,
                ),
                if (showLine)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: color.withValues(alpha: 0.35),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 2),
                  Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B))),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
