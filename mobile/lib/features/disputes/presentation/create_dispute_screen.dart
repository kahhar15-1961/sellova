import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class CreateDisputeScreen extends StatefulWidget {
  const CreateDisputeScreen({
    super.key,
    this.orderId,
  });

  final int? orderId;

  @override
  State<CreateDisputeScreen> createState() => _CreateDisputeScreenState();
}

class _CreateDisputeScreenState extends State<CreateDisputeScreen> {
  final TextEditingController _descriptionCtrl = TextEditingController();
  String _selectedReason = 'Product not as described';
  int _selectedOrderId = 0;

  @override
  void initState() {
    super.initState();
    _selectedOrderId = widget.orderId ?? 85123456789;
  }

  @override
  void dispose() {
    _descriptionCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final reasons = <String>[
      'Product not as described',
      'Late delivery',
      'Damaged item',
      'Missing item',
      'Other',
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: const Text('Create New Dispute'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
          child: Column(
            children: <Widget>[
              Expanded(
                child: ListView(
                  children: <Widget>[
                    Text('Order', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedOrderId,
                      decoration: const InputDecoration(),
                      items: const <DropdownMenuItem<int>>[
                        DropdownMenuItem(value: 85123456789, child: Text('Order #85123456789 - \$499.00')),
                        DropdownMenuItem(value: 85123456790, child: Text('Order #85123456790 - \$903.98')),
                      ],
                      onChanged: (v) => setState(() => _selectedOrderId = v ?? _selectedOrderId),
                    ),
                    const SizedBox(height: 14),
                    Text('Reason', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: _selectedReason,
                      items: reasons.map((r) => DropdownMenuItem(value: r, child: Text(r))).toList(),
                      onChanged: (v) => setState(() => _selectedReason = v ?? _selectedReason),
                    ),
                    const SizedBox(height: 14),
                    Text('Description', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _descriptionCtrl,
                      minLines: 5,
                      maxLines: 6,
                      maxLength: 500,
                      decoration: const InputDecoration(
                        hintText: 'Describe your issue...',
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text('Upload Evidence', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: <Widget>[
                        _evidenceTile(cs, Icons.image_outlined),
                        _evidenceTile(cs, Icons.photo_camera_outlined),
                        _evidenceTile(cs, Icons.description_outlined),
                        _evidenceTile(cs, Icons.add_rounded, add: true),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Dispute submitted successfully.')),
                  );
                  context.go('/disputes');
                },
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Submit Dispute'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _evidenceTile(ColorScheme cs, IconData icon, {bool add = false}) {
    return Container(
      width: 72,
      height: 72,
      decoration: BoxDecoration(
        color: add ? cs.primaryContainer.withValues(alpha: 0.5) : cs.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(icon, color: add ? cs.primary : cs.onSurfaceVariant),
    );
  }
}
