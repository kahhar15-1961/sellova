import 'package:flutter/material.dart';

class SellerDisputeConversationScreen extends StatefulWidget {
  const SellerDisputeConversationScreen({super.key, this.sellerView = false});
  final bool sellerView;

  @override
  State<SellerDisputeConversationScreen> createState() => _SellerDisputeConversationScreenState();
}

class _SellerDisputeConversationScreenState extends State<SellerDisputeConversationScreen> {
  final TextEditingController _message = TextEditingController();
  final List<({String sender, String text, String time, bool admin})> _messages = <({String sender, String text, String time, bool admin})>[
    (sender: 'Ahammad Uddin (Buyer)', text: 'I received the product but the left ear is not working.', time: '29 May 2025, 10:30 AM', admin: false),
    (sender: 'Tech Haven (Seller)', text: 'I\'m sorry to hear that. Can you please share a video showing the issue?', time: '29 May 2025, 10:36 AM', admin: false),
    (sender: 'ShopEase Admin', text: 'We are reviewing the issue. Please wait while our team checks the evidence.', time: '30 May 2025, 08:16 AM', admin: true),
  ];

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dispute #DSP-2025-000034'), actions: <Widget>[Chip(label: const Text('Open'))]),
      body: Column(
        children: <Widget>[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
            child: const Text('Wireless Noise Cancelling Headphones\nOrder: ORD-2025-000125'),
          ),
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: _messages.length,
              itemBuilder: (_, i) {
                final m = _messages[i];
                return Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: m.admin ? const Color(0xFFFFFBEB) : const Color(0xFFF5F3FF),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.35)),
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
                    Text(m.sender, style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(m.time, style: const TextStyle(color: Colors.black54)),
                    const SizedBox(height: 8),
                    Text(m.text),
                    if (m.admin) ...<Widget>[
                      const SizedBox(height: 8),
                      Chip(
                        label: const Text('Under Review'),
                        avatar: const Icon(Icons.schedule_rounded, size: 16),
                        backgroundColor: const Color(0xFFFFF7ED),
                      ),
                    ],
                  ]),
                );
              },
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(12, 8, 12, 8 + MediaQuery.paddingOf(context).bottom),
            child: Row(
              children: <Widget>[
                IconButton(onPressed: () {}, icon: const Icon(Icons.attach_file_rounded)),
                Expanded(
                  child: TextField(controller: _message, decoration: const InputDecoration(hintText: 'Type a message...')),
                ),
                IconButton(
                  onPressed: () {
                    if (_message.text.trim().isEmpty) return;
                    setState(() {
                      _messages.add((
                        sender: widget.sellerView ? 'Tech Haven (Seller)' : 'Ahammad Uddin (Buyer)',
                        text: _message.text.trim(),
                        time: 'Now',
                        admin: false,
                      ));
                      _message.clear();
                    });
                  },
                  icon: const Icon(Icons.send_rounded),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
