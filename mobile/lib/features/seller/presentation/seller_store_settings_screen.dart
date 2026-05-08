import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/providers/repository_providers.dart';
import '../../../core/util/debouncer.dart';
import '../application/seller_business_controller.dart';
import '../application/seller_failure.dart';
import '../data/seller_form_draft_store.dart';
import 'seller_ui.dart';

class SellerStoreSettingsScreen extends ConsumerStatefulWidget {
  const SellerStoreSettingsScreen({super.key});

  @override
  ConsumerState<SellerStoreSettingsScreen> createState() =>
      _SellerStoreSettingsScreenState();
}

class _SellerStoreSettingsScreenState
    extends ConsumerState<SellerStoreSettingsScreen> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _desc = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _address = TextEditingController();
  final TextEditingController _city = TextEditingController();
  final TextEditingController _region = TextEditingController();
  final TextEditingController _postalCode = TextEditingController();
  final TextEditingController _country = TextEditingController();
  _StoreMediaSelection? _logo;
  _StoreMediaSelection? _banner;
  bool _seededFromRemote = false;
  bool _draftChecked = false;
  bool _listenersAttached = false;
  bool _uploadingMedia = false;
  final Debouncer _draftDebouncer =
      Debouncer(duration: const Duration(milliseconds: 450));

  @override
  void dispose() {
    _draftDebouncer.dispose();
    _name.dispose();
    _desc.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    _city.dispose();
    _region.dispose();
    _postalCode.dispose();
    _country.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_listenersAttached) {
      return;
    }
    _listenersAttached = true;
    void persistDraft() {
      _draftDebouncer.run(() {
        ref
            .read(sellerFormDraftStoreProvider)
            .saveStoreSettingsDraft(_name.text, _desc.text);
      });
    }

    _name.addListener(persistDraft);
    _desc.addListener(persistDraft);
  }

  Future<void> _save() async {
    await ref.read(sellerBusinessControllerProvider.notifier).saveStoreSettings(
          storeName: _name.text,
          storeDescription: _desc.text,
          storeLogoUrl: _logo?.storagePath,
          bannerImageUrl: _banner?.storagePath,
          contactEmail: _email.text,
          contactPhone: _phone.text,
          addressLine: _address.text,
          city: _city.text,
          region: _region.text,
          postalCode: _postalCode.text,
          country: _country.text,
        );
    if (!mounted) return;
    final next = ref.read(sellerBusinessControllerProvider);
    final message =
        next.failure?.message ?? next.successMessage ?? 'Changes processed.';
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _showMediaSourceSheet(_StoreMediaKind kind) async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => SafeArea(
        child: Container(
          margin: const EdgeInsets.all(12),
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
          ),
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
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                kind == _StoreMediaKind.logo ? 'Store logo' : 'Store banner',
                style: Theme.of(context)
                    .textTheme
                    .titleLarge
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              Text(
                'Capture or upload a clear brand image.',
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: kSellerMuted),
              ),
              const SizedBox(height: 16),
              _StoreMediaSourceTile(
                icon: Icons.photo_camera_outlined,
                title: 'Capture with camera',
                subtitle: 'Use your camera for a fresh brand image.',
                onTap: () {
                  Navigator.pop(ctx);
                  _pickStoreMedia(kind, _StoreMediaSource.camera);
                },
              ),
              const SizedBox(height: 10),
              _StoreMediaSourceTile(
                icon: Icons.photo_library_outlined,
                title: 'Choose from gallery',
                subtitle: 'Select an existing image from your device.',
                onTap: () {
                  Navigator.pop(ctx);
                  _pickStoreMedia(kind, _StoreMediaSource.gallery);
                },
              ),
              const SizedBox(height: 10),
              _StoreMediaSourceTile(
                icon: Icons.folder_open_outlined,
                title: 'Choose file',
                subtitle: 'Upload PNG, JPG, or WebP.',
                onTap: () {
                  Navigator.pop(ctx);
                  _pickStoreMedia(kind, _StoreMediaSource.file);
                },
              ),
              const SizedBox(height: 10),
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
    );
  }

  Future<void> _pickStoreMedia(
      _StoreMediaKind kind, _StoreMediaSource source) async {
    if (_uploadingMedia) {
      return;
    }
    String? path;
    String fileName = 'store-media.png';
    Uint8List? bytes;

    try {
      if (source == _StoreMediaSource.camera ||
          source == _StoreMediaSource.gallery) {
        final picked = await ImagePicker().pickImage(
          source: source == _StoreMediaSource.camera
              ? ImageSource.camera
              : ImageSource.gallery,
          imageQuality: 88,
          preferredCameraDevice: CameraDevice.rear,
        );
        if (picked == null) {
          return;
        }
        path = picked.path;
        fileName = picked.name;
        bytes = await picked.readAsBytes();
      } else {
        final result = await FilePicker.platform.pickFiles(
          type: FileType.image,
          allowMultiple: false,
          withData: true,
        );
        if (result == null || result.files.isEmpty) {
          return;
        }
        final file = result.files.single;
        path = file.path;
        fileName = file.name;
        bytes = file.bytes;
      }

      if (path == null && bytes == null) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not read the selected image.')),
        );
        return;
      }

      setState(() => _uploadingMedia = true);
      final uploaded =
          await ref.read(sellerRepositoryProvider).uploadSellerMedia(
                path ?? fileName,
                purpose: 'store_media',
                bytes: bytes,
                fileName: fileName,
              );
      if (!mounted) return;
      setState(() {
        final selection = _StoreMediaSelection(
          storagePath: uploaded.storagePath,
          fileName: uploaded.originalName.isNotEmpty
              ? uploaded.originalName
              : fileName,
          localPath: path ?? '',
          bytes: bytes,
          mimeType: uploaded.mimeType,
          size: uploaded.size,
        );
        if (kind == _StoreMediaKind.logo) {
          _logo = selection;
        } else {
          _banner = selection;
        }
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(
            '${kind == _StoreMediaKind.logo ? 'Logo' : 'Banner'} uploaded. Tap Save to publish.'),
      ));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(SellerFailure.from(e).message)));
    } finally {
      if (mounted) {
        setState(() => _uploadingMedia = false);
      }
    }
  }

  Future<void> _showMediaPreview(_StoreMediaKind kind) async {
    final media = kind == _StoreMediaKind.logo ? _logo : _banner;
    final remoteUrl = kind == _StoreMediaKind.logo
        ? ref.read(sellerBusinessControllerProvider).storeSettings.storeLogoUrl
        : ref
            .read(sellerBusinessControllerProvider)
            .storeSettings
            .bannerImageUrl;
    if (media == null && (remoteUrl == null || remoteUrl.isEmpty)) {
      return;
    }
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.all(16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      kind == _StoreMediaKind.logo
                          ? 'Logo preview'
                          : 'Banner preview',
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(dialogContext),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: AspectRatio(
                  aspectRatio: kind == _StoreMediaKind.logo ? 1 : 2.7,
                  child: _StoreMediaImage(
                    media: media,
                    remoteUrl: remoteUrl,
                    fit: BoxFit.contain,
                  ),
                ),
              ),
              if (media != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  media.fileName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context)
                      .textTheme
                      .labelLarge
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                Text(
                  '${media.mimeType.isEmpty ? 'image' : media.mimeType} · ${_formatStoreMediaSize(media.size)}',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final drafts = ref.read(sellerFormDraftStoreProvider);
    if (!_draftChecked) {
      _draftChecked = true;
      final draft = drafts.loadStoreSettingsDraft();
      if (draft != null) {
        final n = draft['store_name']?.toString() ?? '';
        final d = draft['store_description']?.toString() ?? '';
        if (n.trim().isNotEmpty || d.trim().isNotEmpty) {
          if (n.trim().isNotEmpty) {
            _name.text = n;
          }
          if (d.trim().isNotEmpty) {
            _desc.text = d;
          }
          _seededFromRemote = true;
        }
      }
    }

    final state = ref.watch(sellerBusinessControllerProvider);
    if (!_seededFromRemote) {
      _name.text = state.storeSettings.storeName;
      _desc.text = state.storeSettings.storeDescription;
      _email.text = state.storeSettings.contactEmail ?? '';
      _phone.text = state.storeSettings.contactPhone ?? '';
      _address.text = state.storeSettings.addressLine ?? '';
      _city.text = state.storeSettings.city ?? '';
      _region.text = state.storeSettings.region ?? '';
      _postalCode.text = state.storeSettings.postalCode ?? '';
      _country.text = state.storeSettings.country ?? '';
      if ((state.storeSettings.storeLogoUrl ?? '').isNotEmpty) {
        _logo = _StoreMediaSelection.remote(state.storeSettings.storeLogoUrl!);
      }
      if ((state.storeSettings.bannerImageUrl ?? '').isNotEmpty) {
        _banner =
            _StoreMediaSelection.remote(state.storeSettings.bannerImageUrl!);
      }
      _seededFromRemote = true;
    }
    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        backgroundColor: Colors.white.withValues(alpha: 0.94),
        surfaceTintColor: Colors.transparent,
        title: const Text('Store Settings'),
        leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
            onPressed: () => context.pop()),
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[Color(0xFFF7F8FC), Color(0xFFF3F5FA)],
          ),
        ),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Store name',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  TextField(
                      controller: _name,
                      decoration:
                          const InputDecoration(border: OutlineInputBorder())),
                  const SizedBox(height: 18),
                  Text('Logo',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  _StoreMediaCard(
                    kind: _StoreMediaKind.logo,
                    media: _logo,
                    remoteUrl: state.storeSettings.storeLogoUrl,
                    uploading: _uploadingMedia,
                    onChange: () => _showMediaSourceSheet(_StoreMediaKind.logo),
                    onPreview: () => _showMediaPreview(_StoreMediaKind.logo),
                  ),
                  const SizedBox(height: 12),
                  Text('Banner',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  _StoreMediaCard(
                    kind: _StoreMediaKind.banner,
                    media: _banner,
                    remoteUrl: state.storeSettings.bannerImageUrl,
                    uploading: _uploadingMedia,
                    onChange: () =>
                        _showMediaSourceSheet(_StoreMediaKind.banner),
                    onPreview: () => _showMediaPreview(_StoreMediaKind.banner),
                  ),
                  const SizedBox(height: 18),
                  Text('Description',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _desc,
                    maxLines: 4,
                    decoration: const InputDecoration(
                        border: OutlineInputBorder(), alignLabelWithHint: true),
                  ),
                  const SizedBox(height: 18),
                  Text('Contact details',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      labelText: 'Contact email',
                      prefixIcon: Icon(Icons.email_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      labelText: 'Phone number',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text('Store address',
                      style: Theme.of(context)
                          .textTheme
                          .labelMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _address,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      labelText: 'Address line',
                      prefixIcon: Icon(Icons.location_on_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: TextField(
                          controller: _city,
                          decoration: const InputDecoration(
                            border: OutlineInputBorder(),
                            labelText: 'City',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _region,
                          decoration: const InputDecoration(
                            border: OutlineInputBorder(),
                            labelText: 'Region',
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: TextField(
                          controller: _postalCode,
                          decoration: const InputDecoration(
                            border: OutlineInputBorder(),
                            labelText: 'Postal code',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _country,
                          decoration: const InputDecoration(
                            border: OutlineInputBorder(),
                            labelText: 'Country',
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: state.isSaving ? null : _save,
              style: FilledButton.styleFrom(
                  backgroundColor: kSellerAccent,
                  minimumSize: const Size.fromHeight(52)),
              child: state.isSaving
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }
}

enum _StoreMediaKind { logo, banner }

enum _StoreMediaSource { camera, gallery, file }

class _StoreMediaSelection {
  const _StoreMediaSelection({
    required this.storagePath,
    required this.fileName,
    required this.localPath,
    required this.bytes,
    required this.mimeType,
    required this.size,
  });

  factory _StoreMediaSelection.remote(String url) => _StoreMediaSelection(
        storagePath: url,
        fileName: url.split('/').last,
        localPath: '',
        bytes: null,
        mimeType: '',
        size: null,
      );

  final String storagePath;
  final String fileName;
  final String localPath;
  final Uint8List? bytes;
  final String mimeType;
  final int? size;

  bool get hasBytes => bytes != null && bytes!.isNotEmpty;
  bool get hasLocalFile => localPath.isNotEmpty && File(localPath).existsSync();
}

class _StoreMediaCard extends StatelessWidget {
  const _StoreMediaCard({
    required this.kind,
    required this.media,
    required this.remoteUrl,
    required this.uploading,
    required this.onChange,
    required this.onPreview,
  });

  final _StoreMediaKind kind;
  final _StoreMediaSelection? media;
  final String? remoteUrl;
  final bool uploading;
  final VoidCallback onChange;
  final VoidCallback onPreview;

  @override
  Widget build(BuildContext context) {
    final hasImage = media != null || (remoteUrl ?? '').isNotEmpty;
    final isLogo = kind == _StoreMediaKind.logo;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFDCE6F5)),
      ),
      child: Row(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(isLogo ? 16 : 14),
            child: SizedBox(
              width: isLogo ? 82 : 116,
              height: isLogo ? 82 : 72,
              child: _StoreMediaImage(
                media: media,
                remoteUrl: remoteUrl,
                fit: BoxFit.cover,
              ),
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
                        isLogo ? 'Store logo' : 'Store banner',
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                    if (hasImage) const _StoreMediaBadge(label: 'Uploaded'),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  media?.fileName ??
                      (hasImage ? 'Saved image' : 'No image selected'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: kSellerMuted),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    if (hasImage)
                      OutlinedButton.icon(
                        onPressed: uploading ? null : onPreview,
                        icon: const Icon(Icons.visibility_outlined, size: 18),
                        label: const Text('Preview'),
                      ),
                    FilledButton.tonalIcon(
                      onPressed: uploading ? null : onChange,
                      icon: uploading
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.photo_camera_outlined, size: 18),
                      label: Text(hasImage ? 'Change' : 'Upload'),
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
}

class _StoreMediaImage extends StatelessWidget {
  const _StoreMediaImage({
    required this.media,
    required this.remoteUrl,
    required this.fit,
  });

  final _StoreMediaSelection? media;
  final String? remoteUrl;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) {
    final item = media;
    if (item?.hasBytes ?? false) {
      return Image.memory(item!.bytes!, fit: fit);
    }
    if (item?.hasLocalFile ?? false) {
      return Image.file(File(item!.localPath), fit: fit);
    }
    if ((remoteUrl ?? '').isNotEmpty) {
      return Image.network(
        remoteUrl!,
        fit: fit,
        errorBuilder: (_, __, ___) => const _StoreMediaPlaceholder(),
      );
    }
    return const _StoreMediaPlaceholder();
  }
}

class _StoreMediaPlaceholder extends StatelessWidget {
  const _StoreMediaPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFEDE9FE),
      alignment: Alignment.center,
      child: const Icon(
        Icons.photo_size_select_actual_outlined,
        color: kSellerAccent,
        size: 34,
      ),
    );
  }
}

class _StoreMediaBadge extends StatelessWidget {
  const _StoreMediaBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFE8F6EC),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: const Color(0xFF15803D),
              fontWeight: FontWeight.w900,
            ),
      ),
    );
  }
}

class _StoreMediaSourceTile extends StatelessWidget {
  const _StoreMediaSourceTile({
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
                          ?.copyWith(color: kSellerMuted),
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

String _formatStoreMediaSize(int? size) {
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
