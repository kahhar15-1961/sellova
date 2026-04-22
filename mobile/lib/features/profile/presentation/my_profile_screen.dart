import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/my_profile_controller.dart';

class MyProfileScreen extends ConsumerStatefulWidget {
  const MyProfileScreen({super.key});

  @override
  ConsumerState<MyProfileScreen> createState() => _MyProfileScreenState();
}

class _MyProfileScreenState extends ConsumerState<MyProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _seeded = false;
  bool _hidePassword = true;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(() => ref.read(myProfileControllerProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayNameController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(myProfileControllerProvider);
    final profile = state.profile;

    if (profile != null && !_seeded) {
      _displayNameController.text = profile.displayName;
      _phoneController.text = profile.phone;
      _seeded = true;
    }

    if (state.isLoading && profile == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.errorMessage != null && profile == null) {
      return _ProfileError(
        message: state.errorMessage!,
        onRetry: () => ref.read(myProfileControllerProvider.notifier).load(),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(myProfileControllerProvider.notifier).load(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Text('My Profile', style: Theme.of(context).textTheme.headlineSmall),
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
                  Text('Account info', style: Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 8),
                  Text('Email: ${profile?.email ?? 'Unavailable'}'),
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
                    Text('Edit profile', style: Theme.of(context).textTheme.titleSmall),
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
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(
                        labelText: 'Phone',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _hidePassword,
                      decoration: InputDecoration(
                        labelText: 'Password (optional)',
                        border: const OutlineInputBorder(),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _hidePassword = !_hidePassword),
                          icon: Icon(_hidePassword ? Icons.visibility : Icons.visibility_off),
                        ),
                      ),
                      validator: (value) {
                        final input = (value ?? '').trim();
                        if (input.isNotEmpty && input.length < 8) {
                          return 'Password must be at least 8 characters';
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
                                await ref.read(myProfileControllerProvider.notifier).update(
                                      displayName: _displayNameController.text,
                                      phone: _phoneController.text,
                                      password: _passwordController.text,
                                    );
                                _passwordController.clear();
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

class _ProfileError extends StatelessWidget {
  const _ProfileError({
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
