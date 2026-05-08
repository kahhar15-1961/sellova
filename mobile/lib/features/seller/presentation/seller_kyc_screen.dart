import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/errors/api_exception.dart';
import '../../profile/application/my_profile_controller.dart';
import '../../profile/application/seller_profile_controller.dart';
import '../../profile/data/profile_repository.dart';
import '../domain/seller_models.dart';
import 'seller_ui.dart';

class SellerKycScreen extends ConsumerStatefulWidget {
  const SellerKycScreen({super.key});

  @override
  ConsumerState<SellerKycScreen> createState() => _SellerKycScreenState();
}

class _SellerKycScreenState extends ConsumerState<SellerKycScreen> {
  final _formKey = GlobalKey<FormState>();
  final _displayName = TextEditingController();
  final _legalName = TextEditingController();
  final _countryCode = TextEditingController(text: 'BD');
  final _currency = TextEditingController(text: 'BDT');
  final Map<String, Map<String, _KycDocumentSelection>> _documentsByPreset =
      <String, Map<String, _KycDocumentSelection>>{};
  String _selectedPresetKey = _kycDocumentPresets.first.key;
  bool _seeded = false;
  bool _working = false;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
        () => ref.read(sellerProfileControllerProvider.notifier).load());
    Future<void>.microtask(
        () => ref.read(myProfileControllerProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayName.dispose();
    _legalName.dispose();
    _countryCode.dispose();
    _currency.dispose();
    super.dispose();
  }

  _KycDocumentPreset get _activePreset =>
      _kycDocumentPresets.firstWhere((e) => e.key == _selectedPresetKey);

  Map<String, _KycDocumentSelection> get _currentDocuments =>
      _documentsByPreset.putIfAbsent(
        _selectedPresetKey,
        () => <String, _KycDocumentSelection>{},
      );

  List<_KycDocumentSlotSpec> get _activeSlots => _activePreset.slots;

  Future<void> _selectPreset(String presetKey) async {
    if (_working || presetKey == _selectedPresetKey) {
      return;
    }
    setState(() {
      _selectedPresetKey = presetKey;
    });
  }

  Future<void> _pickAndUpload(String docType) async {
    if (_working) return;
    final slot = _activeSlots.firstWhere((e) => e.key == docType);
    await _showUploadSourceSheet(
      docType,
      allowCamera: slot.requiresCamera,
      allowGallery: true,
      allowFile: true,
    );
  }

  Future<void> _uploadPickedFile(
    String docType, {
    required String path,
    required String fileName,
    Uint8List? bytes,
  }) async {
    setState(() => _working = true);
    try {
      final uploaded =
          await ref.read(sellerRepositoryProvider).uploadSellerMedia(
                path,
                purpose: 'kyc',
                bytes: bytes,
                fileName: fileName,
              );
      if (!mounted) return;
      setState(() {
        _currentDocuments[docType] = _KycDocumentSelection(
          localPath: path,
          fileName: fileName,
          uploaded: uploaded,
          bytes: bytes,
        );
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${_docLabel(docType)} uploaded.')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyKycError(error))),
      );
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  String _friendlyKycError(Object error) {
    if (error is ApiException &&
        error.type == ApiExceptionType.validationFailed &&
        error.context.isNotEmpty) {
      final violations = error.context['violations'];
      if (violations is List && violations.isNotEmpty) {
        final flattened = violations
            .whereType<Map>()
            .map((item) {
              final field = (item['field'] ?? '').toString();
              final message = (item['message'] ?? '').toString();
              return field.isEmpty ? message : '$field: $message';
            })
            .where((line) => line.trim().isNotEmpty)
            .join('\n');
        if (flattened.isNotEmpty) {
          return flattened;
        }
      }
      final entries = error.context.entries
          .map((entry) => '${entry.key}: ${entry.value}')
          .join(', ');
      return entries.isEmpty ? error.message : entries;
    }
    if (error is ApiException) {
      return error.message;
    }
    return 'Something went wrong. Please try again.';
  }

  Future<void> _captureFromCamera(String docType) async {
    if (_working) return;
    try {
      final picked = await ImagePicker().pickImage(
        source: ImageSource.camera,
        imageQuality: 88,
        preferredCameraDevice: CameraDevice.rear,
      );
      if (picked == null) {
        return;
      }
      final bytes = await picked.readAsBytes();
      await _uploadPickedFile(
        docType,
        path: picked.path,
        fileName: picked.name,
        bytes: bytes,
      );
    } catch (_) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content:
              Text('Camera unavailable. Use gallery or file upload instead.'),
        ),
      );
      return;
    }
  }

  Future<void> _pickFromGallery(String docType) async {
    if (_working) return;
    try {
      final picked = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        imageQuality: 88,
      );
      if (picked == null) {
        return;
      }
      final bytes = await picked.readAsBytes();
      await _uploadPickedFile(
        docType,
        path: picked.path,
        fileName: picked.name,
        bytes: bytes,
      );
    } catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyKycError(error))),
      );
      return;
    }
  }

  Future<void> _pickFromFiles(String docType) async {
    if (_working) return;
    final result = await FilePicker.platform.pickFiles(
      allowMultiple: false,
      withData: true,
    );
    if (result == null || result.files.isEmpty) {
      return;
    }
    final file = result.files.single;
    final path = file.path;
    final fileName = file.name;
    final bytes = file.bytes;
    if (path == null && bytes == null) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not read the selected file.')),
      );
      return;
    }
    await _uploadPickedFile(
      docType,
      path: path ?? fileName,
      fileName: fileName,
      bytes: bytes,
    );
  }

  Future<void> _showUploadSourceSheet(
    String docType, {
    required bool allowCamera,
    required bool allowGallery,
    required bool allowFile,
  }) async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => SafeArea(
        child: Container(
          margin: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
          ),
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Center(
                  child: Container(
                    width: 42,
                    height: 5,
                    decoration: BoxDecoration(
                      color: const Color(0xFFD7DEEA),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _docLabel(docType),
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  'Choose how to add this document.',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted),
                ),
                const SizedBox(height: 16),
                if (allowCamera)
                  _SourceActionTile(
                    icon: Icons.photo_camera_outlined,
                    title: 'Capture with camera',
                    subtitle: 'Use the camera to photograph the document.',
                    onTap: () {
                      Navigator.pop(ctx);
                      _captureFromCamera(docType);
                    },
                  ),
                if (allowCamera) const SizedBox(height: 10),
                if (allowGallery)
                  _SourceActionTile(
                    icon: Icons.photo_library_outlined,
                    title: 'Choose from gallery',
                    subtitle: 'Upload an existing photo from your device.',
                    onTap: () {
                      Navigator.pop(ctx);
                      _pickFromGallery(docType);
                    },
                  ),
                if (allowGallery) const SizedBox(height: 10),
                if (allowFile)
                  _SourceActionTile(
                    icon: Icons.folder_open_outlined,
                    title: 'Choose file',
                    subtitle: 'Select a PDF, image, or document file.',
                    onTap: () {
                      Navigator.pop(ctx);
                      _pickFromFiles(docType);
                    },
                  ),
                if (allowFile) const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () => Navigator.pop(ctx),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(50),
                  ),
                  child: const Text('Close'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _createProfile() async {
    if (_working) return;
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() => _working = true);
    try {
      final created = await ref
          .read(profileRepositoryProvider)
          .createMeSeller(<String, dynamic>{
        'display_name': _displayName.text.trim(),
        if (_legalName.text.trim().isNotEmpty)
          'legal_name': _legalName.text.trim(),
        'country_code': _countryCode.text.trim().toUpperCase(),
        'default_currency': _currency.text.trim().toUpperCase(),
      });
      await ref.read(sellerProfileControllerProvider.notifier).load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('${created.displayName} is ready for verification.')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyKycError(error))),
      );
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  Future<void> _showDocumentPreview(String docType) async {
    final doc = _currentDocuments[docType];
    if (doc == null) {
      return;
    }

    await showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close preview',
      barrierColor: Colors.black.withValues(alpha: 0.72),
      transitionDuration: const Duration(milliseconds: 220),
      pageBuilder: (dialogContext, animation, secondaryAnimation) {
        return Scaffold(
          backgroundColor: Colors.black.withValues(alpha: 0.92),
          body: SafeArea(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: _DocumentPreviewSheet(
                    title: _docLabel(docType),
                    selection: doc,
                    onClose: () => Navigator.of(dialogContext).pop(),
                  ),
                ),
              ),
            ),
          ),
        );
      },
      transitionBuilder: (context, animation, secondaryAnimation, child) {
        final curved = CurvedAnimation(
          parent: animation,
          curve: Curves.easeOutCubic,
        );
        return FadeTransition(
          opacity: curved,
          child: ScaleTransition(
            scale: Tween<double>(begin: 0.96, end: 1).animate(curved),
            child: child,
          ),
        );
      },
    );
  }

  Future<void> _submitKyc(SellerProfileDto profile) async {
    final requiredDocs = _activeSlots
        .where((slot) => slot.required)
        .map((slot) => slot.key)
        .toList();
    for (final key in requiredDocs) {
      if (!_currentDocuments.containsKey(key)) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Upload ${_docLabel(key)} first.')),
        );
        return;
      }
    }

    if (_working) return;
    setState(() => _working = true);
    try {
      final docs = <Map<String, dynamic>>[
        for (final entry in _currentDocuments.entries)
          <String, dynamic>{
            'doc_type': entry.key,
            'storage_path': entry.value.uploaded.storagePath,
            if (entry.value.uploaded.checksumSha256.trim().isNotEmpty)
              'checksum_sha256': entry.value.uploaded.checksumSha256.trim(),
          },
      ];

      await ref
          .read(profileRepositoryProvider)
          .submitSellerKyc(<String, dynamic>{
        'seller_profile_id': profile.raw['id'],
        'documents': docs,
      });

      await ref.read(sellerProfileControllerProvider.notifier).load();
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('KYC submitted for review.')),
      );
      context.go('/seller/onboarding');
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_friendlyKycError(error))),
      );
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  String _docLabel(String key) => switch (key) {
        'nid_front' => 'NID front',
        'nid_back' => 'NID back',
        'nid_selfie' => 'NID selfie',
        'license_front' => 'License front',
        'license_back' => 'License back',
        'license_selfie' => 'License selfie',
        'passport_page' => 'Passport page',
        'passport_selfie' => 'Passport selfie',
        'business_license' => 'Business license',
        'address_proof' => 'Address proof',
        _ => key,
      };

  @override
  Widget build(BuildContext context) {
    final meState = ref.watch(myProfileControllerProvider);
    final sellerState = ref.watch(sellerProfileControllerProvider);
    final profile = sellerState.profile;
    final cs = Theme.of(context).colorScheme;

    if (meState.profile != null && !_seeded) {
      _displayName.text = meState.profile!.displayName;
      _countryCode.text =
          meState.profile!.country.isEmpty ? 'BD' : meState.profile!.country;
      _currency.text =
          meState.profile!.currency.isEmpty ? 'BDT' : meState.profile!.currency;
      _seeded = true;
    }

    final latestStatus = profile?.latestKycStatus ?? 'none';
    final canSubmitKyc = latestStatus == 'none' ||
        latestStatus == 'rejected' ||
        latestStatus == 'expired';
    final isReviewLocked = latestStatus == 'submitted' ||
        latestStatus == 'under_review' ||
        latestStatus == 'approved';
    final statusLabel = switch (latestStatus) {
      'submitted' => 'Submitted',
      'under_review' => 'Under review',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'expired' => 'Expired',
      _ => 'Not submitted',
    };
    final requiredSlotKeys = _activeSlots
        .where((slot) => slot.required)
        .map((slot) => slot.key)
        .toSet();
    final readyToSubmit = requiredSlotKeys.isNotEmpty &&
        requiredSlotKeys.every(_currentDocuments.containsKey);

    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        title: const Text('KYC Verification'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: RefreshIndicator(
          onRefresh: () async {
            await Future.wait(<Future<void>>[
              ref.read(sellerProfileControllerProvider.notifier).load(),
              ref.read(myProfileControllerProvider.notifier).load(),
            ]);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: <Widget>[
              if (!isReviewLocked) ...<Widget>[
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: sellerCardDecoration(cs),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Container(
                            width: 48,
                            height: 48,
                            decoration: BoxDecoration(
                              color: kSellerAccent.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Icon(Icons.badge_outlined,
                                color: kSellerAccent.withValues(alpha: 0.9)),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  'Identity review',
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleLarge
                                      ?.copyWith(fontWeight: FontWeight.w900),
                                ),
                                Text(
                                  'Upload documents once, then submit for admin review.',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(color: kSellerMuted),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'Status: $statusLabel',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            fontWeight: FontWeight.w800, color: kSellerNavy),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        profile?.latestKycSubmittedAt == null
                            ? 'No KYC case has been submitted yet.'
                            : 'Submitted on ${sellerNiceDate(profile!.latestKycSubmittedAt!)}',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
              ],
              if (profile == null) ...<Widget>[
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: sellerCardDecoration(cs),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Create seller profile',
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.w900)),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _displayName,
                          decoration:
                              const InputDecoration(labelText: 'Display name'),
                          validator: (value) {
                            if ((value ?? '').trim().isEmpty) {
                              return 'Display name is required';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _legalName,
                          decoration:
                              const InputDecoration(labelText: 'Legal name'),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: <Widget>[
                            Expanded(
                              child: TextField(
                                controller: _countryCode,
                                decoration:
                                    const InputDecoration(labelText: 'Country'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: TextField(
                                controller: _currency,
                                decoration: const InputDecoration(
                                    labelText: 'Currency'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed: _working ? null : _createProfile,
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(52),
                            backgroundColor: kSellerAccent,
                          ),
                          child: _working
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Create profile'),
                        ),
                      ],
                    ),
                  ),
                ),
              ] else if (isReviewLocked) ...<Widget>[
                _KycLockedPanel(
                  status: latestStatus,
                  submittedAt: profile.latestKycSubmittedAt,
                  documents: profile.latestKycDocuments,
                  onRefresh: () =>
                      ref.read(sellerProfileControllerProvider.notifier).load(),
                  onSupport: () => context.push('/seller/help-support'),
                ),
              ] else if (canSubmitKyc) ...<Widget>[
                _KycFlowStepper(
                  activeStep: _currentDocuments.isEmpty
                      ? 1
                      : _currentDocuments.length <
                              _activeSlots.where((e) => e.required).length
                          ? 2
                          : 3,
                ),
                const SizedBox(height: 12),
                Text(
                  'Document type',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: _kycDocumentPresets
                      .map(
                        (preset) => ChoiceChip(
                          selected: _selectedPresetKey == preset.key,
                          onSelected: _working
                              ? null
                              : (selected) {
                                  if (selected) {
                                    _selectPreset(preset.key);
                                  }
                                },
                          avatar: Icon(
                            preset.icon,
                            size: 18,
                            color: _selectedPresetKey == preset.key
                                ? Colors.white
                                : const Color(0xFF4C1D95),
                          ),
                          label: Text(preset.label),
                          labelStyle: TextStyle(
                            color: _selectedPresetKey == preset.key
                                ? Colors.white
                                : const Color(0xFF0F172A),
                            fontWeight: FontWeight.w700,
                          ),
                          selectedColor: kSellerAccent,
                          backgroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(999),
                          ),
                        ),
                      )
                      .toList(),
                ),
                const SizedBox(height: 12),
                _KycWizardHeader(
                  preset: _activePreset,
                  selectedCount: _currentDocuments.length,
                  requiredCount: _activeSlots.where((e) => e.required).length,
                  slots: _activeSlots,
                  selectedDocumentKeys: _currentDocuments.keys.toSet(),
                ),
                const SizedBox(height: 12),
                Text(
                  'Documents',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                for (final slot in _activeSlots) ...<Widget>[
                  _DocSlot(
                    slot: slot,
                    uploaded: _currentDocuments[slot.key],
                    working: _working,
                    onUpload: () => _pickAndUpload(slot.key),
                    onPreview: _currentDocuments[slot.key] == null
                        ? null
                        : () => _showDocumentPreview(slot.key),
                  ),
                  const SizedBox(height: 10),
                ],
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF3F4FF),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Automation',
                          style: Theme.of(context)
                              .textTheme
                              .titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900)),
                      const SizedBox(height: 6),
                      Text(
                        'After submission, the case is queued for admin review automatically. You will see the status on this screen and in onboarding.',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted, height: 1.45),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: _working || !readyToSubmit
                      ? null
                      : () => _submitKyc(profile),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                    backgroundColor: kSellerAccent,
                  ),
                  child: _working
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : Text(readyToSubmit
                          ? 'Submit for review'
                          : 'Upload required documents'),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () => context.push('/seller/help-support'),
                  style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(52)),
                  child: const Text('Need help'),
                ),
              ] else ...<Widget>[
                _KycLockedPanel(
                  status: latestStatus,
                  submittedAt: profile.latestKycSubmittedAt,
                  documents: profile.latestKycDocuments,
                  onRefresh: () =>
                      ref.read(sellerProfileControllerProvider.notifier).load(),
                  onSupport: () => context.push('/seller/help-support'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DocSlot extends StatelessWidget {
  const _DocSlot({
    required this.slot,
    required this.uploaded,
    required this.working,
    required this.onUpload,
    required this.onPreview,
  });

  final _KycDocumentSlotSpec slot;
  final _KycDocumentSelection? uploaded;
  final bool working;
  final VoidCallback onUpload;
  final VoidCallback? onPreview;

  @override
  Widget build(BuildContext context) {
    final hasUpload = uploaded != null;
    final canPreview = hasUpload && onPreview != null;
    final thumbnail = _previewThumbnail(context);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: working ? null : onUpload,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: sellerCardDecoration(Theme.of(context).colorScheme),
          child: Row(
            children: <Widget>[
              ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: Container(
                  width: 52,
                  height: 52,
                  color: const Color(0xFFF3F4FF),
                  child: thumbnail,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            slot.label,
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ),
                        if (slot.required)
                          _InlineStatusBadge(
                            label: hasUpload ? 'Uploaded' : 'Required',
                            color: hasUpload
                                ? const Color(0xFF15803D)
                                : const Color(0xFFB45309),
                            background: hasUpload
                                ? const Color(0xFFE8F6EC)
                                : const Color(0xFFFFF5E7),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    if (!hasUpload)
                      Text(
                        slot.requiresCamera
                            ? 'Capture or upload a clear photo'
                            : 'Select a file to continue',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted),
                      )
                    else ...<Widget>[
                      TextButton(
                        onPressed: canPreview ? onPreview : null,
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          alignment: Alignment.centerLeft,
                        ),
                        child: Text(
                          uploaded!.fileName.isEmpty
                              ? 'Uploaded file'
                              : uploaded!.fileName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: const Color(0xFF1D4ED8),
                                    fontWeight: FontWeight.w700,
                                    decoration: TextDecoration.underline,
                                  ),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${uploaded!.mimeType.isEmpty ? 'unknown type' : uploaded!.mimeType} · ${_formatSize(uploaded!.size)}',
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  if (canPreview)
                    IconButton(
                      onPressed: onPreview,
                      icon: const Icon(Icons.visibility_outlined),
                      tooltip: 'Preview file',
                    ),
                  FilledButton.tonal(
                    onPressed: working ? null : onUpload,
                    child: Text(hasUpload
                        ? (slot.requiresCamera ? 'Retake' : 'Replace')
                        : 'Add'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _previewThumbnail(BuildContext context) {
    if (uploaded == null) {
      return Icon(
        slot.requiresCamera ? Icons.photo_camera_outlined : Icons.upload_file,
        color: kSellerAccent,
      );
    }
    if (uploaded!.hasPreviewBytes) {
      return Image.memory(
        uploaded!.bytes!,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => const Center(
          child: Icon(Icons.description_outlined, color: Color(0xFF475569)),
        ),
      );
    }
    if (_canRenderLocalImage(uploaded!)) {
      return Image.file(
        File(uploaded!.localPreviewPath),
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => const Center(
          child: Icon(Icons.description_outlined, color: Color(0xFF475569)),
        ),
      );
    }
    return const Center(
      child: Icon(Icons.description_outlined, color: Color(0xFF475569)),
    );
  }
}

class _KycLockedPanel extends StatelessWidget {
  const _KycLockedPanel({
    required this.status,
    required this.submittedAt,
    required this.documents,
    required this.onRefresh,
    required this.onSupport,
  });

  final String status;
  final DateTime? submittedAt;
  final List<Map<String, dynamic>> documents;
  final VoidCallback onRefresh;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    final approved = normalized == 'approved';
    final title = switch (normalized) {
      'approved' => 'Verification Approved',
      'submitted' => 'Submitted for review',
      'under_review' => 'Under review',
      _ => 'Verification in progress',
    };
    final body = switch (normalized) {
      'approved' =>
        'Your seller account is fully verified. You can now access all features.',
      'submitted' => 'Your documents are queued for admin review.',
      'under_review' => 'An admin reviewer is checking your documents.',
      _ => 'We will update this screen when the review changes.',
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          padding: const EdgeInsets.fromLTRB(18, 20, 18, 20),
          decoration: BoxDecoration(
            color: approved ? const Color(0xFFE6FAF0) : const Color(0xFFFFF8E7),
            borderRadius: BorderRadius.circular(11),
            border: Border.all(
              color:
                  approved ? const Color(0xFF86EFAC) : const Color(0xFFF2D49B),
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFD1FAE5),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFF86EFAC)),
                ),
                child: Icon(
                  approved
                      ? Icons.verified_user_outlined
                      : Icons.hourglass_top_rounded,
                  color: approved
                      ? const Color(0xFF10B981)
                      : const Color(0xFFB45309),
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: const Color(0xFF064E3B),
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      body,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: const Color(0xFF047857),
                            height: 1.45,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.fromLTRB(18, 16, 16, 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(11),
            border: Border.all(color: const Color(0xFFE5E7EB)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF111827).withValues(alpha: 0.055),
                blurRadius: 14,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFAFAFA),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFEDEEF1)),
                    ),
                    child: const Icon(Icons.manage_accounts_outlined,
                        color: Color(0xFF4F46E5), size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Identity Review',
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: const Color(0xFF18181B),
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          approved
                              ? 'Your identity verification has been successfully processed.'
                              : 'Your identity verification is being processed.',
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: const Color(0xFF52525B),
                                    height: 1.25,
                                  ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Align(
                alignment: Alignment.centerRight,
                child: Container(
                  width: 182,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFAFAFA),
                    borderRadius: BorderRadius.circular(9),
                    border: Border.all(color: const Color(0xFFEDEEF1)),
                  ),
                  child: Column(
                    children: <Widget>[
                      _KycInfoLine(
                        label: 'Status',
                        value: approved ? 'Approved' : title,
                        valueColor: approved
                            ? const Color(0xFF16A34A)
                            : const Color(0xFFB45309),
                      ),
                      const SizedBox(height: 5),
                      _KycInfoLine(
                        label: 'Submitted',
                        value: submittedAt == null
                            ? 'Unavailable'
                            : sellerShortDate(submittedAt!),
                        icon: Icons.schedule_rounded,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 26),
        Text(
          'Submitted Documents',
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: const Color(0xFF18181B),
                fontWeight: FontWeight.w900,
              ),
        ),
        const SizedBox(height: 10),
        if (documents.isEmpty)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: _kycDocumentCardDecoration(),
            child: Text(
              'Documents are saved with your review case.',
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: kSellerMuted),
            ),
          )
        else
          for (final doc in documents) ...<Widget>[
            _KycSubmittedDocRow(doc: doc),
            const SizedBox(height: 10),
          ],
        const SizedBox(height: 14),
        Row(
          children: <Widget>[
            Expanded(
              child: FilledButton.tonal(
                onPressed: onRefresh,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  backgroundColor: const Color(0xFFF4F4F5),
                  foregroundColor: const Color(0xFF3F3F46),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(Icons.refresh_rounded, size: 18),
                    SizedBox(width: 8),
                    Text('Refresh'),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton(
                onPressed: onSupport,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                  foregroundColor: const Color(0xFF18181B),
                  side: const BorderSide(color: Color(0xFF18181B)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(Icons.chat_bubble_outline_rounded, size: 17),
                    SizedBox(width: 8),
                    Text('Support'),
                  ],
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _KycSubmittedDocRow extends StatelessWidget {
  const _KycSubmittedDocRow({required this.doc});

  final Map<String, dynamic> doc;

  @override
  Widget build(BuildContext context) {
    final type = (doc['doc_type'] ?? '').toString();
    final status = (doc['status'] ?? 'uploaded').toString();
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 13, 12, 13),
      decoration: _kycDocumentCardDecoration(),
      child: Row(
        children: <Widget>[
          Container(
            width: 36,
            height: 36,
            decoration: const BoxDecoration(
              color: Color(0xFFEFF2FF),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.description_outlined,
                color: Color(0xFF4F46E5), size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  _documentTitle(type),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: const Color(0xFF18181B),
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  _documentSubtitle(type),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: const Color(0xFF71717A),
                        fontWeight: FontWeight.w600,
                      ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFE8FFF4),
              borderRadius: BorderRadius.circular(7),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const Icon(Icons.check_circle_outline_rounded,
                    size: 13, color: Color(0xFF10B981)),
                const SizedBox(width: 5),
                Text(
                  status.toLowerCase().contains('reject')
                      ? 'REVIEW'
                      : 'VERIFIED',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: const Color(0xFF047857),
                        fontSize: 10,
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _KycInfoLine extends StatelessWidget {
  const _KycInfoLine({
    required this.label,
    required this.value,
    this.valueColor,
    this.icon,
  });

  final String label;
  final String value;
  final Color? valueColor;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: const Color(0xFF71717A),
                  fontWeight: FontWeight.w700,
                ),
          ),
        ),
        if (icon != null) ...<Widget>[
          Icon(icon, size: 12, color: const Color(0xFFA1A1AA)),
          const SizedBox(width: 3),
        ],
        Flexible(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.right,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: valueColor ?? const Color(0xFF18181B),
                  fontWeight: FontWeight.w900,
                ),
          ),
        ),
      ],
    );
  }
}

BoxDecoration _kycDocumentCardDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(9),
    border: Border.all(color: const Color(0xFFE5E7EB)),
    boxShadow: <BoxShadow>[
      BoxShadow(
        color: const Color(0xFF111827).withValues(alpha: 0.035),
        blurRadius: 10,
        offset: const Offset(0, 4),
      ),
    ],
  );
}

String _documentTitle(String type) {
  final normalized = type.toLowerCase();
  if (normalized.contains('front')) return 'Nid Front';
  if (normalized.contains('back')) return 'Nid Back';
  if (normalized.contains('selfie')) return 'Selfie';
  if (normalized.contains('trade')) return 'Trade License';
  if (normalized.isEmpty) return 'Document';
  return normalized
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _documentSubtitle(String type) {
  final normalized = type.toLowerCase();
  if (normalized.contains('front')) return 'National ID (Front)';
  if (normalized.contains('back')) return 'National ID (Back)';
  if (normalized.contains('selfie')) return 'Face verification';
  if (normalized.contains('trade')) return 'Business document';
  return 'Submitted document';
}

class _SourceActionTile extends StatelessWidget {
  const _SourceActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFF8FAFF),
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: <Widget>[
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF2FF),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: kSellerAccent),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: kSellerMuted, height: 1.35),
                    ),
                  ],
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

class _DocumentPreviewSheet extends StatelessWidget {
  const _DocumentPreviewSheet({
    required this.title,
    required this.selection,
    required this.onClose,
  });

  final String title;
  final _KycDocumentSelection selection;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final preview = _buildPreview(context);
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(28),
      clipBehavior: Clip.antiAlias,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 12),
            child: Row(
              children: <Widget>[
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF2FF),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.visibility_outlined,
                    color: kSellerAccent,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        title,
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        selection.fileName.isEmpty
                            ? 'Selected document'
                            : selection.fileName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context)
                            .textTheme
                            .bodySmall
                            ?.copyWith(color: kSellerMuted),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: onClose,
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(22),
              child: AspectRatio(
                aspectRatio: 1.1,
                child: ColoredBox(
                  color: const Color(0xFFF7F8FC),
                  child: preview,
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    _PreviewMetaChip(
                      icon: Icons.insert_drive_file_outlined,
                      label: selection.mimeType.isEmpty
                          ? 'unknown type'
                          : selection.mimeType,
                    ),
                    _PreviewMetaChip(
                      icon: Icons.data_usage_outlined,
                      label: _formatSize(selection.size),
                    ),
                    _PreviewMetaChip(
                      icon: Icons.verified_outlined,
                      label: selection.hasPreviewBytes
                          ? 'Preview ready'
                          : 'Stored file',
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: onClose,
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      backgroundColor: kSellerAccent,
                    ),
                    child: const Text('Close preview'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPreview(BuildContext context) {
    if (selection.hasPreviewBytes) {
      return Image.memory(
        selection.bytes!,
        fit: BoxFit.contain,
      );
    }
    if (_canRenderLocalImage(selection)) {
      return Image.file(
        File(selection.localPreviewPath),
        fit: BoxFit.contain,
      );
    }
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 92,
              height: 92,
              decoration: BoxDecoration(
                color: const Color(0xFFEFF2FF),
                borderRadius: BorderRadius.circular(26),
              ),
              child: const Icon(
                Icons.description_outlined,
                size: 44,
                color: kSellerAccent,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Preview unavailable',
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              'This file can still be submitted and reviewed by admin.',
              textAlign: TextAlign.center,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: kSellerMuted, height: 1.35),
            ),
          ],
        ),
      ),
    );
  }
}

class _PreviewMetaChip extends StatelessWidget {
  const _PreviewMetaChip({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FC),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFD7DEEA)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 16, color: kSellerAccent),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
          ),
        ],
      ),
    );
  }
}

class _InlineStatusBadge extends StatelessWidget {
  const _InlineStatusBadge({
    required this.label,
    required this.color,
    required this.background,
  });

  final String label;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: color,
              fontWeight: FontWeight.w900,
            ),
      ),
    );
  }
}

class _KycDocumentSelection {
  const _KycDocumentSelection({
    required this.localPath,
    required this.fileName,
    required this.uploaded,
    required this.bytes,
  });

  final String localPath;
  final String fileName;
  final SellerUploadResult uploaded;
  final Uint8List? bytes;

  bool get isImage {
    final lower = fileName.toLowerCase();
    return lower.endsWith('.png') ||
        lower.endsWith('.jpg') ||
        lower.endsWith('.jpeg') ||
        lower.endsWith('.webp');
  }

  String get localPreviewPath => localPath;

  String get mimeType => uploaded.mimeType;

  int? get size => uploaded.size;

  bool get hasPreviewBytes => bytes != null && bytes!.isNotEmpty;
}

class _KycDocumentSlotSpec {
  const _KycDocumentSlotSpec({
    required this.key,
    required this.label,
    required this.required,
    this.requiresCamera = true,
  });

  final String key;
  final String label;
  final bool required;
  final bool requiresCamera;
}

class _KycDocumentPreset {
  const _KycDocumentPreset({
    required this.key,
    required this.label,
    required this.subtitle,
    required this.icon,
    required this.slots,
  });

  final String key;
  final String label;
  final String subtitle;
  final IconData icon;
  final List<_KycDocumentSlotSpec> slots;
}

bool _canRenderLocalImage(_KycDocumentSelection selection) {
  if (selection.localPath.isEmpty) {
    return false;
  }
  final file = File(selection.localPath);
  return file.existsSync() && selection.isImage;
}

String _formatSize(int? size) {
  if (size == null || size <= 0) {
    return 'Unknown size';
  }
  const units = <String>['B', 'KB', 'MB', 'GB'];
  var value = size.toDouble();
  var unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit++;
  }
  return '${value.toStringAsFixed(value >= 10 || unit == 0 ? 0 : 1)} ${units[unit]}';
}

class _KycWizardHeader extends StatelessWidget {
  const _KycWizardHeader({
    required this.preset,
    required this.selectedCount,
    required this.requiredCount,
    required this.slots,
    required this.selectedDocumentKeys,
  });

  final _KycDocumentPreset preset;
  final int selectedCount;
  final int requiredCount;
  final List<_KycDocumentSlotSpec> slots;
  final Set<String> selectedDocumentKeys;

  @override
  Widget build(BuildContext context) {
    final complete =
        requiredCount == 0 ? 0 : (selectedCount / requiredCount).clamp(0, 1);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD6E4FF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF2FF),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(preset.icon, color: kSellerAccent),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      preset.label,
                      style: Theme.of(context)
                          .textTheme
                          .titleSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      preset.subtitle,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: kSellerMuted),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: <Widget>[
                  Text(
                    '${(complete * 100).round()}%',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                          color: kSellerNavy,
                        ),
                  ),
                  Text(
                    '$selectedCount / $requiredCount',
                    style: Theme.of(context)
                        .textTheme
                        .labelSmall
                        ?.copyWith(color: kSellerMuted),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: slots
                .map(
                  (slot) => _PreviewMetaChip(
                    icon: selectedDocumentKeys.contains(slot.key)
                        ? Icons.check_circle_outline
                        : Icons.pending_outlined,
                    label: '${slot.label} '
                        '${selectedDocumentKeys.contains(slot.key) ? 'ready' : 'pending'}',
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _KycFlowStepper extends StatelessWidget {
  const _KycFlowStepper({
    required this.activeStep,
  });

  final int activeStep;

  @override
  Widget build(BuildContext context) {
    final steps = <_MiniStep>[
      const _MiniStep(
        title: 'Select type',
        subtitle: 'Choose NID, license, or passport',
        step: 1,
      ),
      const _MiniStep(
        title: 'Capture files',
        subtitle: 'Camera, gallery, or file upload',
        step: 2,
      ),
      const _MiniStep(
        title: 'Review & submit',
        subtitle: 'Preview then send for admin review',
        step: 3,
      ),
    ];

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD6E4FF)),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A102A43),
            blurRadius: 16,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Verification flow',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: const Color(0xFF0F172A),
                ),
          ),
          const SizedBox(height: 2),
          Text(
            'Follow the guided steps to complete verification.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: kSellerMuted,
                ),
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              for (var index = 0; index < steps.length; index++) ...<Widget>[
                Expanded(
                  child: _StepRailItem(
                    step: steps[index],
                    active: activeStep >= steps[index].step,
                    completed: activeStep > steps[index].step,
                  ),
                ),
                if (index != steps.length - 1)
                  Padding(
                    padding: const EdgeInsets.only(top: 13),
                    child: Container(
                      width: 20,
                      height: 2,
                      decoration: BoxDecoration(
                        color: activeStep > steps[index].step
                            ? kSellerAccent
                            : const Color(0xFFD7DEEA),
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _StepRailItem extends StatelessWidget {
  const _StepRailItem({
    required this.step,
    required this.active,
    required this.completed,
  });

  final _MiniStep step;
  final bool active;
  final bool completed;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color:
                completed || active ? kSellerAccent : const Color(0xFFE5EAF3),
            shape: BoxShape.circle,
            boxShadow: completed || active
                ? const <BoxShadow>[
                    BoxShadow(
                      color: Color(0x1F4F46E5),
                      blurRadius: 12,
                      offset: Offset(0, 6),
                    ),
                  ]
                : const <BoxShadow>[],
          ),
          alignment: Alignment.center,
          child: completed
              ? const Icon(Icons.check_rounded, size: 18, color: Colors.white)
              : Text(
                  '${step.step}',
                  style: TextStyle(
                    color: active ? Colors.white : const Color(0xFF475569),
                    fontWeight: FontWeight.w900,
                  ),
                ),
        ),
        const SizedBox(height: 8),
        Text(
          step.title,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w900,
                color: const Color(0xFF0F172A),
              ),
        ),
        const SizedBox(height: 2),
        Text(
          step.subtitle,
          textAlign: TextAlign.center,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: kSellerMuted,
                height: 1.25,
              ),
        ),
      ],
    );
  }
}

class _MiniStep {
  const _MiniStep({
    required this.title,
    required this.subtitle,
    required this.step,
  });

  final String title;
  final String subtitle;
  final int step;
}

const List<_KycDocumentPreset> _kycDocumentPresets = <_KycDocumentPreset>[
  _KycDocumentPreset(
    key: 'nid',
    label: 'NID',
    subtitle: 'National ID with front, back, and selfie.',
    icon: Icons.badge_outlined,
    slots: <_KycDocumentSlotSpec>[
      _KycDocumentSlotSpec(
        key: 'nid_front',
        label: 'NID front',
        required: true,
        requiresCamera: true,
      ),
      _KycDocumentSlotSpec(
        key: 'nid_back',
        label: 'NID back',
        required: true,
        requiresCamera: true,
      ),
      _KycDocumentSlotSpec(
        key: 'nid_selfie',
        label: 'Selfie',
        required: true,
        requiresCamera: true,
      ),
    ],
  ),
  _KycDocumentPreset(
    key: 'driving_license',
    label: 'Driving License',
    subtitle: 'License front, back, and a selfie.',
    icon: Icons.directions_car_outlined,
    slots: <_KycDocumentSlotSpec>[
      _KycDocumentSlotSpec(
        key: 'license_front',
        label: 'License front',
        required: true,
        requiresCamera: true,
      ),
      _KycDocumentSlotSpec(
        key: 'license_back',
        label: 'License back',
        required: true,
        requiresCamera: true,
      ),
      _KycDocumentSlotSpec(
        key: 'license_selfie',
        label: 'Selfie',
        required: true,
        requiresCamera: true,
      ),
    ],
  ),
  _KycDocumentPreset(
    key: 'passport',
    label: 'Passport',
    subtitle: 'Passport photo page and a selfie.',
    icon: Icons.flight_outlined,
    slots: <_KycDocumentSlotSpec>[
      _KycDocumentSlotSpec(
        key: 'passport_page',
        label: 'Passport photo page',
        required: true,
        requiresCamera: true,
      ),
      _KycDocumentSlotSpec(
        key: 'passport_selfie',
        label: 'Selfie',
        required: true,
        requiresCamera: true,
      ),
    ],
  ),
];
