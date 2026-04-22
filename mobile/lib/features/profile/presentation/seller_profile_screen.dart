import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/seller_profile_controller.dart';

class SellerProfileScreen extends ConsumerStatefulWidget {
  const SellerProfileScreen({super.key});

  @override
  ConsumerState<SellerProfileScreen> createState() => _SellerProfileScreenState();
}

class _SellerProfileScreenState extends ConsumerState<SellerProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayNameController = TextEditingController();
  final _legalNameController = TextEditingController();
  bool _seeded = false;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() => ref.read(sellerProfileControllerProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayNameController.dispose();
    _legalNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerProfileControllerProvider);
    final profile = state.profile;

    if (profile != null && !_seeded) {
      _displayNameController.text = profile.displayName;
      _legalNameController.text = profile.legalName;
      _seeded = true;
    }

    if (state.isLoading && profile == null && state.hasSellerProfile) {
      return const Center(child: CircularProgressIndicator());
    }

    if (!state.hasSellerProfile) {
      return RefreshIndicator(
        onRefresh: () => ref.read(sellerProfileControllerProvider.notifier).load(),
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: const <Widget>[
            SizedBox(height: 80),
            Icon(Icons.storefront_outlined, size: 56),
            SizedBox(height: 12),
            Center(
              child: Text(
                'You are not a seller yet.',
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      );
    }

    if (state.errorMessage != null && profile == null) {
      return _SellerError(
        message: state.errorMessage!,
        onRetry: () => ref.read(sellerProfileControllerProvider.notifier).load(),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(sellerProfileControllerProvider.notifier).load(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Text('Seller Profile', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 8),
          if (state.successMessage != null)
            _Banner(
              text: state.successMessage!,
              icon: Icons.check_circle_outline,
              color: Colors.green,
            ),
          if (state.errorMessage != null)
            _Banner(
              text: state.errorMessage!,
              icon: Icons.error_outline,
              color: Colors.red,
            ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Seller info', style: Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 8),
                  Text('Country: ${profile?.country.isEmpty ?? true ? 'N/A' : profile!.country}'),
                  Text('Currency: ${profile?.currency.isEmpty ?? true ? 'N/A' : profile!.currency}'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Edit seller profile', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _displayNameController,
                      decoration: const InputDecoration(
                        labelText: 'Display name',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Display name is required';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _legalNameController,
                      decoration: const InputDecoration(
                        labelText: 'Legal name',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if ((value ?? '').trim().isEmpty) {
                          return 'Legal name is required';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: state.isSaving
                            ? null
                            : () async {
                                if (!(_formKey.currentState?.validate() ?? false)) {
                                  return;
                                }
                                await ref.read(sellerProfileControllerProvider.notifier).update(
                                      displayName: _displayNameController.text,
                                      legalName: _legalNameController.text,
                                    );
                              },
                        child: state.isSaving
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Text('Save changes'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({
    required this.text,
    required this.icon,
    required this.color,
  });

  final String text;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          color: color.withOpacity(0.12),
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, color: color),
            const SizedBox(width: 8),
            Expanded(child: Text(text)),
          ],
        ),
      ),
    );
  }
}

class _SellerError extends StatelessWidget {
  const _SellerError({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.error_outline, size: 44),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
