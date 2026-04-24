import 'package:flutter/material.dart';

class ContactSellerChatScreen extends StatefulWidget {
  const ContactSellerChatScreen({
    super.key,
    required this.orderId,
    this.title = 'Contact Seller',
  });

  final int orderId;
  final String title;

  @override
  State<ContactSellerChatScreen> createState() => _ContactSellerChatScreenState();
}

class _ContactSellerChatScreenState extends State<ContactSellerChatScreen> {
  final TextEditingController _msgCtrl = TextEditingController();
  final List<_ChatMessage> _messages = <_ChatMessage>[
    _ChatMessage(fromSeller: true, text: 'Hi! Thank you for your order. Let me know if you have any questions.', time: '10:30 AM'),
    _ChatMessage(fromSeller: false, text: 'Hi! When will my order be shipped?', time: '10:31 AM'),
    _ChatMessage(fromSeller: true, text: 'Your order has been shipped today. You will receive it within 2-3 working days.', time: '10:32 AM'),
  ];

  @override
  void dispose() {
    _msgCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FE),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(widget.title),
            Text('Online', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF16A34A))),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: cs.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.35)),
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
                      child: const Icon(Icons.headphones, size: 28),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Wireless Noise Cancelling Headphones', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          const SizedBox(height: 2),
                          Text('Order #${widget.orderId}', style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded),
                  ],
                ),
              ),
            ),
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                itemCount: _messages.length + 1,
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Center(
                        child: Text('01 June 2025', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B))),
                      ),
                    );
                  }
                  final m = _messages[index - 1];
                  return Align(
                    alignment: m.fromSeller ? Alignment.centerLeft : Alignment.centerRight,
                    child: Container(
                      constraints: const BoxConstraints(maxWidth: 280),
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                      decoration: BoxDecoration(
                        color: m.fromSeller ? cs.surface : const Color(0xFFF1EEFF),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: cs.outlineVariant.withValues(alpha: 0.28)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(m.text, style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.35)),
                          const SizedBox(height: 6),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Text(m.time, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF64748B))),
                              if (!m.fromSeller) ...<Widget>[
                                const SizedBox(width: 4),
                                const Icon(Icons.done_all_rounded, size: 16, color: Color(0xFF4F46E5)),
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
            Padding(
              padding: EdgeInsets.fromLTRB(16, 8, 16, 14 + MediaQuery.paddingOf(context).bottom),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: TextField(
                      controller: _msgCtrl,
                      decoration: const InputDecoration(
                        hintText: 'Type a message...',
                        prefixIcon: Icon(Icons.attach_file_rounded),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  IconButton.filled(
                    onPressed: () {
                      final text = _msgCtrl.text.trim();
                      if (text.isEmpty) return;
                      setState(() {
                        _messages.add(_ChatMessage(fromSeller: false, text: text, time: 'Now'));
                        _msgCtrl.clear();
                      });
                    },
                    icon: const Icon(Icons.send_rounded),
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

class _ChatMessage {
  const _ChatMessage({
    required this.fromSeller,
    required this.text,
    required this.time,
  });

  final bool fromSeller;
  final String text;
  final String time;
}
