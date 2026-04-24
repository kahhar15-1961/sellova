import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/app_providers.dart';
import '../../auth/presentation/auth_ui_constants.dart';

const String _kSupportEmail = 'support@sellova.com';

class HelpSupportScreen extends ConsumerWidget {
  const HelpSupportScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Help & Support'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: <Widget>[
          _HelpTile(
            icon: Icons.chat_bubble_outline_rounded,
            title: 'Help Center',
            onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Help center articles are coming soon.')),
            ),
          ),
          _HelpTile(
            icon: Icons.help_outline_rounded,
            title: 'How it works',
            onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Guides for buyers and sellers are coming soon.')),
            ),
          ),
          _HelpTile(
            icon: Icons.article_outlined,
            title: 'FAQ',
            onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('FAQ is coming soon.')),
            ),
          ),
          _HelpTile(
            icon: Icons.gavel_outlined,
            title: 'Terms & Conditions',
            onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Terms will be published here soon.')),
            ),
          ),
          _HelpTile(
            icon: Icons.privacy_tip_outlined,
            title: 'Privacy Policy',
            onTap: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Privacy policy is coming soon.')),
            ),
          ),
          _HelpTile(
            icon: Icons.headset_mic_outlined,
            title: 'Contact Support',
            onTap: () async {
              await Clipboard.setData(const ClipboardData(text: _kSupportEmail));
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Support email copied to clipboard.')),
                );
              }
            },
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: kAuthAccentPurple.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: kAuthAccentPurple.withValues(alpha: 0.18)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: cs.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(Icons.support_agent_rounded, color: cs.primary, size: 28),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Need Help?',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF0B1A60),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'We are here to help you.',
                        style: theme.textTheme.bodyMedium?.copyWith(color: const Color(0xFF475569)),
                      ),
                      const SizedBox(height: 12),
                      SelectableText(
                        _kSupportEmail,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: const Color(0xFF0B1A60),
                          fontWeight: FontWeight.w700,
                          decoration: TextDecoration.underline,
                          decorationColor: kAuthAccentPurple,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          TextButton.icon(
            onPressed: () async {
              final ok = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Clear saved browsing state?'),
                      content: const Text(
                        'Resets saved list state for products, orders, disputes, and withdrawals.',
                      ),
                      actions: <Widget>[
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Clear')),
                      ],
                    ),
                  ) ??
                  false;
              if (!ok || !context.mounted) {
                return;
              }
              await ref.read(listStatePersistenceProvider).clearAllBrowsingState();
              await ref.read(navigationStatePersistenceProvider).resetToHome();
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Saved browsing state cleared.')));
              }
            },
            icon: const Icon(Icons.delete_sweep_outlined, size: 20),
            label: const Text('Clear saved browsing state'),
          ),
        ],
      ),
    );
  }
}

class _HelpTile extends StatelessWidget {
  const _HelpTile({
    required this.icon,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 4),
          child: Row(
            children: <Widget>[
              Icon(icon, size: 24, color: const Color(0xFF475569)),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: const Color(0xFF0F172A),
                      ),
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
            ],
          ),
        ),
      ),
    );
  }
}
