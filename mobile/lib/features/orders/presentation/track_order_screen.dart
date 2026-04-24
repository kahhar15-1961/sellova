import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class TrackOrderScreen extends StatelessWidget {
  const TrackOrderScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(title: Text('Order #$orderId')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          child: Column(
            children: <Widget>[
              Expanded(
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surface,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.35)),
                  ),
                  child: const Column(
                    children: <Widget>[
                      _TrackRow(done: true, active: false, title: 'Paid in Escrow', subtitle: 'May 30, 10:30 AM', showLine: true),
                      _TrackRow(done: true, active: false, title: 'Processing', subtitle: 'May 30, 02:00 PM', showLine: true),
                      _TrackRow(done: false, active: true, title: 'Shipped', subtitle: 'Pending', showLine: true),
                      _TrackRow(done: false, active: false, title: 'Delivered', subtitle: 'Pending', showLine: true),
                      _TrackRow(done: false, active: false, title: 'Completed', subtitle: 'Pending', showLine: false),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => context.push('/disputes/create?orderId=$orderId'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Need Help? Open Dispute'),
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
