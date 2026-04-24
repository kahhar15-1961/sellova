import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import 'seller_ui.dart';

/// Premium storefront-style profile for the seller persona (demo + navigation).
class SellerStoreProfileScreen extends ConsumerWidget {
  const SellerStoreProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(sellerBusinessControllerProvider).storeSettings;
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Store Profile'),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded), onPressed: () => context.pop()),
      ),
      body: ListView(
        children: <Widget>[
          Stack(
            clipBehavior: Clip.none,
            children: <Widget>[
              Container(
                height: 120,
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: <Color>[Color(0xFFE8E4FF), Color(0xFFD4CCFF)]),
                ),
                child: Align(
                  alignment: Alignment.topRight,
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: CircleAvatar(
                      backgroundColor: Colors.white,
                      child: IconButton(icon: const Icon(Icons.photo_camera_outlined, color: kSellerAccent), onPressed: () {}),
                    ),
                  ),
                ),
              ),
              Positioned(
                left: 0,
                right: 0,
                top: 72,
                child: Column(
                  children: <Widget>[
                    CircleAvatar(
                      radius: 48,
                      backgroundColor: Colors.white,
                      child: CircleAvatar(
                        radius: 44,
                        backgroundColor: const Color(0xFFF1F5F9),
                        child: Icon(Icons.storefront_rounded, size: 40, color: Colors.grey.shade700),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(settings.storeName, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
                    Text('Electronics Store', style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted)),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        ...List<Widget>.generate(5, (_) => const Icon(Icons.star_rounded, color: Color(0xFFEAB308), size: 20)),
                        const SizedBox(width: 6),
                        Text('(728 Reviews)', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 140),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('About Store', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text(
                  settings.storeDescription,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: kSellerMuted, height: 1.45),
                ),
                const SizedBox(height: 20),
                _infoRow(context, Icons.store_outlined, 'Store Name', settings.storeName, onTap: () => context.push('/seller/store-settings')),
                const Divider(height: 1),
                _infoRow(context, Icons.phone_outlined, 'Phone Number', settings.contactPhone ?? '+880 1912-345678'),
                const Divider(height: 1),
                _infoRow(context, Icons.email_outlined, 'Email', settings.contactEmail ?? 'techhaven@gmail.com', onTap: () => context.push('/seller/store-settings')),
                const Divider(height: 1),
                _infoRow(context, Icons.location_on_outlined, 'Address', 'Dhaka, Bangladesh'),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: () => context.push('/seller/store-settings'),
                  style: FilledButton.styleFrom(backgroundColor: kSellerAccent, minimumSize: const Size.fromHeight(52)),
                  child: const Text('Edit Profile'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(BuildContext context, IconData icon, String label, String value, {VoidCallback? onTap}) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: kSellerAccent),
      title: Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: kSellerMuted)),
      subtitle: Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
      trailing: onTap != null ? const Icon(Icons.chevron_right_rounded) : null,
      onTap: onTap,
    );
  }
}
