import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_demo_controller.dart';
import 'seller_ui.dart';

class _Bubble {
  const _Bubble({required this.fromCustomer, required this.text, required this.time, this.read = false});
  final bool fromCustomer;
  final String text;
  final String time;
  final bool read;
}

class SellerOrderChatScreen extends ConsumerStatefulWidget {
  const SellerOrderChatScreen({super.key, required this.orderId});
  final int orderId;

  @override
  ConsumerState<SellerOrderChatScreen> createState() => _SellerOrderChatScreenState();
}

class _SellerOrderChatScreenState extends ConsumerState<SellerOrderChatScreen> {
  final TextEditingController _input = TextEditingController();
  final List<_Bubble> _messages = <_Bubble>[
    const _Bubble(fromCustomer: true, text: 'Hi, I have placed an order.', time: '10:30 AM'),
    const _Bubble(fromCustomer: false, text: 'Yes, thank you! We will process it soon.', time: '10:31 AM', read: true),
    const _Bubble(fromCustomer: true, text: 'When will it be shipped?', time: '10:32 AM'),
    const _Bubble(fromCustomer: false, text: 'It will be shipped today. You will get tracking information.', time: '10:33 AM', read: true),
    const _Bubble(fromCustomer: true, text: 'Okay, thanks!', time: '10:34 AM'),
  ];

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = ref.watch(sellerOrdersProvider.notifier).byId(widget.orderId);
    final customer = order?.customerName ?? 'Customer';
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const CircleAvatar(radius: 18, child: Icon(Icons.person_rounded, size: 20)),
            const SizedBox(width: 10),
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(customer, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  Row(
                    children: <Widget>[
                      Container(width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFF22C55E), shape: BoxShape.circle)),
                      const SizedBox(width: 6),
                      Text('Online', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF16A34A))),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: <Widget>[
          IconButton(icon: const Icon(Icons.more_vert_rounded), onPressed: () {}),
        ],
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Text('29 May, 10:30 AM', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
          ),
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              itemCount: _messages.length,
              itemBuilder: (BuildContext context, int i) {
                final m = _messages[i];
                final align = m.fromCustomer ? Alignment.centerLeft : Alignment.centerRight;
                final bg = m.fromCustomer ? Colors.white : const Color(0xFFEDE9FE);
                return Align(
                  alignment: align,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
                    padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                    decoration: BoxDecoration(
                      color: bg,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(m.text, style: Theme.of(context).textTheme.bodyMedium),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Text(m.time, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: kSellerMuted)),
                            if (!m.fromCustomer && m.read) ...<Widget>[
                              const SizedBox(width: 6),
                              const Icon(Icons.done_all_rounded, size: 16, color: Color(0xFF2563EB)),
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
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: TextField(
                      controller: _input,
                      decoration: InputDecoration(
                        hintText: 'Type a message...',
                        filled: true,
                        fillColor: Colors.white,
                        prefixIcon: IconButton(icon: const Icon(Icons.attach_file_rounded), onPressed: () {}),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(28)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Material(
                    color: kSellerAccent,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () {
                        final t = _input.text.trim();
                        if (t.isEmpty) return;
                        HapticFeedback.lightImpact();
                        setState(() {
                          _messages.add(_Bubble(fromCustomer: false, text: t, time: 'Now', read: true));
                          _input.clear();
                        });
                      },
                      child: const Padding(
                        padding: EdgeInsets.all(14),
                        child: Icon(Icons.send_rounded, color: Colors.white, size: 20),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
