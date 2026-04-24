import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import 'seller_ui.dart';

class SellerHelpSupportScreen extends ConsumerWidget {
  const SellerHelpSupportScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final email = ref.watch(sellerBusinessControllerProvider).storeSettings.contactEmail ?? 'support@example.com';
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Help & Support'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: <Widget>[
          _tile(context, Icons.help_outline_rounded, 'How it works', () => _toast(context)),
          _tile(context, Icons.chat_bubble_outline_rounded, 'FAQs', () => _toast(context)),
          _tile(context, Icons.description_outlined, 'Terms & Conditions', () => _toast(context)),
          _tile(context, Icons.shield_outlined, 'Privacy Policy', () => _toast(context)),
          _tile(context, Icons.support_agent_rounded, 'Contact Support', () => _toast(context)),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(color: const Color(0xFFF3F3FF), borderRadius: BorderRadius.circular(16)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    CircleAvatar(
                      backgroundColor: Colors.white,
                      child: Icon(Icons.support_agent_rounded, color: kSellerAccent.withValues(alpha: 0.9)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Need Help?', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900, color: const Color(0xFF2D2D50))),
                          Text('We are here to help you.', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF707090))),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Row(
                  children: <Widget>[
                    Icon(Icons.email_outlined, size: 18, color: kSellerAccent.withValues(alpha: 0.85)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: SelectableText(
                        email,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700, color: const Color(0xFF2D2D50)),
                      ),
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

  void _toast(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('This link will open help content in production.')));
  }

  Widget _tile(BuildContext context, IconData icon, String label, VoidCallback onTap) {
    return Material(
      color: Colors.white,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16),
          child: Row(
            children: <Widget>[
              Icon(icon, color: const Color(0xFF2D2D50)),
              const SizedBox(width: 14),
              Expanded(child: Text(label, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF2D2D50)))),
              const Icon(Icons.chevron_right_rounded, color: Color(0xFFCBD5E1)),
            ],
          ),
        ),
      ),
    );
  }
}
