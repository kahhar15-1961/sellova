import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../application/seller_business_controller.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

/// Premium storefront-style profile for the seller persona (demo + navigation).
class SellerStoreProfileScreen extends ConsumerWidget {
  const SellerStoreProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(sellerBusinessControllerProvider).storeSettings;
    final logoUrl = (settings.storeLogoUrl ?? '').trim();
    final bannerUrl = (settings.bannerImageUrl ?? '').trim();
    final phone = (settings.contactPhone ?? '').trim();
    final email = (settings.contactEmail ?? '').trim();
    final address = _storeAddress(settings);
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Store Profile'),
        leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: () => context.pop()),
      ),
      body: ListView(
        children: <Widget>[
          Stack(
            clipBehavior: Clip.none,
            children: <Widget>[
              Container(
                height: 120,
                decoration: BoxDecoration(
                  gradient: bannerUrl.isEmpty ? kSellerPrimaryGradient : null,
                ),
                child: Stack(
                  fit: StackFit.expand,
                  children: <Widget>[
                    if (bannerUrl.isNotEmpty)
                      Image.network(
                        bannerUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: kSellerPrimaryGradient,
                          ),
                        ),
                      ),
                    Align(
                      alignment: Alignment.topRight,
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: CircleAvatar(
                          backgroundColor: Colors.white,
                          child: IconButton(
                            icon: const Icon(Icons.photo_camera_outlined,
                                color: kSellerAccent),
                            onPressed: () =>
                                context.push('/seller/store-settings'),
                          ),
                        ),
                      ),
                    ),
                  ],
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
                        backgroundImage:
                            logoUrl.isNotEmpty ? NetworkImage(logoUrl) : null,
                        child: logoUrl.isEmpty
                            ? Icon(Icons.storefront_rounded,
                                size: 40, color: Colors.grey.shade700)
                            : null,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(settings.storeName,
                        style: Theme.of(context)
                            .textTheme
                            .headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900)),
                    Text(
                      settings.storeDescription.isEmpty
                          ? 'Seller store profile'
                          : settings.storeDescription,
                      textAlign: TextAlign.center,
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(color: kSellerMuted),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        ...List<Widget>.generate(
                            4,
                            (_) => const Icon(Icons.star_rounded,
                                color: Color(0xFFEAB308), size: 20)),
                        const Icon(Icons.star_half_rounded,
                            color: Color(0xFFEAB308), size: 20),
                        const SizedBox(width: 6),
                        Text('Store overview',
                            style: Theme.of(context)
                                .textTheme
                                .bodySmall
                                ?.copyWith(color: kSellerMuted)),
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
                Text('About Store',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text(
                  settings.storeDescription,
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(color: kSellerMuted, height: 1.45),
                ),
                const SizedBox(height: 20),
                _infoRow(context, Icons.store_outlined, 'Store Name',
                    settings.storeName,
                    onTap: () => context.push('/seller/store-settings')),
                const Divider(height: 1),
                _infoRow(context, Icons.phone_outlined, 'Phone Number',
                    phone.isEmpty ? 'Not added yet' : phone,
                    onTap: () => context.push('/seller/store-settings')),
                const Divider(height: 1),
                _infoRow(context, Icons.email_outlined, 'Email',
                    email.isEmpty ? 'Not added yet' : email,
                    onTap: () => context.push('/seller/store-settings')),
                const Divider(height: 1),
                _infoRow(context, Icons.location_on_outlined, 'Address',
                    address.isEmpty ? 'Not added yet' : address,
                    onTap: () => context.push('/seller/store-settings')),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: () => context.push('/seller/store-settings'),
                  style: FilledButton.styleFrom(
                      backgroundColor: kSellerAccent,
                      minimumSize: const Size.fromHeight(52)),
                  child: const Text('Edit Profile'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _storeAddress(SellerStoreSettings settings) {
    final saved = (settings.storeAddress ?? '').trim();
    if (saved.isNotEmpty) {
      return saved;
    }
    return <String?>[
      settings.addressLine,
      settings.city,
      settings.region,
      settings.postalCode,
      settings.country,
    ]
        .map((value) => (value ?? '').trim())
        .where((value) => value.isNotEmpty)
        .join(', ');
  }

  Widget _infoRow(
      BuildContext context, IconData icon, String label, String value,
      {VoidCallback? onTap}) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: kSellerAccent),
      title: Text(label,
          style: Theme.of(context)
              .textTheme
              .bodySmall
              ?.copyWith(color: kSellerMuted)),
      subtitle: Text(value,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
      trailing: onTap != null ? const Icon(Icons.chevron_right_rounded) : null,
      onTap: onTap,
    );
  }
}
