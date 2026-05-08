import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'auth_form_widgets.dart';
import 'auth_landing_shell.dart';
import 'auth_ui_constants.dart';

/// Password self-service is not wired to the API yet; this screen captures email for support follow-up.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AuthLandingShell(
      title: 'Reset access',
      subtitle:
          'Enter the email on your account and support can help with the next step.',
      highlights: const <String>[
        'Support follow-up',
        'Secure recovery',
      ],
      onBack: () => context.pop(),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              'Enter the email on your account. Automated reset is not enabled yet, but we can still help.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: const Color(0xFF64748B), height: 1.45),
            ),
            const SizedBox(height: 24),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: authInputDecoration(hint: 'you@example.com').copyWith(
                labelText: 'Email',
              ),
              validator: (v) {
                final s = (v ?? '').trim();
                if (s.isEmpty) {
                  return 'Email is required';
                }
                if (!s.contains('@')) {
                  return 'Enter a valid email';
                }
                return null;
              },
            ),
            const SizedBox(height: 24),
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: kAuthAccentPurple,
                minimumSize: const Size.fromHeight(52),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(kAuthFieldRadius)),
              ),
              onPressed: () {
                if (!(_formKey.currentState?.validate() ?? false)) {
                  return;
                }
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      'If an account exists for ${_email.text.trim()}, support can help reset it. Self-service reset is coming soon.',
                    ),
                  ),
                );
                context.pop();
              },
              child: const Text('Continue'),
            ),
          ],
        ),
      ),
    );
  }
}
