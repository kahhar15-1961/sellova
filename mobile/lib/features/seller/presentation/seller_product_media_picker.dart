import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';

class ProductImageSelection {
  const ProductImageSelection({
    required this.storagePath,
    required this.fileName,
    this.localPath = '',
    this.previewUrl,
    this.bytes,
  });

  final String storagePath;
  final String fileName;
  final String localPath;
  final String? previewUrl;
  final Uint8List? bytes;

  bool get hasBytes => bytes != null && bytes!.isNotEmpty;
  bool get hasLocalFile => localPath.isNotEmpty && File(localPath).existsSync();
}

class ProductImageGalleryPicker extends StatelessWidget {
  const ProductImageGalleryPicker({
    super.key,
    required this.images,
    required this.uploading,
    required this.onAdd,
    required this.onRemove,
    required this.onMakeCover,
  });

  final List<ProductImageSelection> images;
  final bool uploading;
  final VoidCallback onAdd;
  final ValueChanged<int> onRemove;
  final ValueChanged<int> onMakeCover;

  @override
  Widget build(BuildContext context) {
    final hasImages = images.isNotEmpty;
    final cover = hasImages ? images.first : null;
    final cs = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            onTap: uploading ? null : onAdd,
            borderRadius: BorderRadius.circular(16),
            child: Ink(
              height: 190,
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: hasImages ? cs.primary : cs.outlineVariant,
                ),
              ),
              child: Stack(
                fit: StackFit.expand,
                children: <Widget>[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(15),
                    child: cover == null
                        ? const _ProductImagePlaceholder()
                        : ProductImagePreview(image: cover),
                  ),
                  if (uploading)
                    ColoredBox(
                      color: Colors.black.withValues(alpha: 0.22),
                      child: const Center(
                        child: CircularProgressIndicator(color: Colors.white),
                      ),
                    ),
                  Positioned(
                    left: 12,
                    right: 12,
                    bottom: 12,
                    child: Row(
                      children: <Widget>[
                        FilledButton.icon(
                          onPressed: uploading ? null : onAdd,
                          icon: const Icon(Icons.add_photo_alternate_outlined),
                          label:
                              Text(hasImages ? 'Add images' : 'Upload images'),
                        ),
                        const Spacer(),
                        if (hasImages)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.black.withValues(alpha: 0.56),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              '${images.length} image${images.length == 1 ? '' : 's'}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (images.isNotEmpty) ...<Widget>[
          const SizedBox(height: 10),
          SizedBox(
            height: 86,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: images.length + 1,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (context, index) {
                if (index == images.length) {
                  return _AddImageTile(onTap: uploading ? null : onAdd);
                }
                final image = images[index];
                return _ImageTile(
                  image: image,
                  isCover: index == 0,
                  onRemove: uploading ? null : () => onRemove(index),
                  onMakeCover:
                      uploading || index == 0 ? null : () => onMakeCover(index),
                );
              },
            ),
          ),
        ],
      ],
    );
  }
}

class ProductImagePreview extends StatelessWidget {
  const ProductImagePreview({super.key, required this.image});

  final ProductImageSelection image;

  @override
  Widget build(BuildContext context) {
    if (image.hasBytes) {
      return Image.memory(image.bytes!, fit: BoxFit.cover);
    }
    if (image.hasLocalFile) {
      return Image.file(File(image.localPath), fit: BoxFit.cover);
    }
    final url = image.previewUrl ?? image.storagePath;
    if (url.startsWith('http://') || url.startsWith('https://')) {
      return Image.network(
        url,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => const _ProductImagePlaceholder(),
      );
    }
    return const _ProductImagePlaceholder();
  }
}

class _ImageTile extends StatelessWidget {
  const _ImageTile({
    required this.image,
    required this.isCover,
    required this.onRemove,
    required this.onMakeCover,
  });

  final ProductImageSelection image;
  final bool isCover;
  final VoidCallback? onRemove;
  final VoidCallback? onMakeCover;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 86,
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: ProductImagePreview(image: image),
          ),
          DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isCover
                    ? Theme.of(context).colorScheme.primary
                    : Theme.of(context).colorScheme.outlineVariant,
                width: isCover ? 2 : 1,
              ),
            ),
          ),
          if (onMakeCover != null)
            Positioned.fill(
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: onMakeCover,
                ),
              ),
            ),
          if (isCover)
            const Positioned(
              left: 6,
              bottom: 6,
              child: _CoverBadge(),
            ),
          Positioned(
            top: 4,
            right: 4,
            child: IconButton.filledTonal(
              visualDensity: VisualDensity.compact,
              constraints: const BoxConstraints.tightFor(width: 30, height: 30),
              padding: EdgeInsets.zero,
              onPressed: onRemove,
              icon: const Icon(Icons.close_rounded, size: 16),
            ),
          ),
        ],
      ),
    );
  }
}

class _CoverBadge extends StatelessWidget {
  const _CoverBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        'Cover',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: Theme.of(context).colorScheme.onPrimary,
              fontWeight: FontWeight.w900,
            ),
      ),
    );
  }
}

class _AddImageTile extends StatelessWidget {
  const _AddImageTile({required this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 86,
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border:
              Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        ),
        child: const Icon(Icons.add_photo_alternate_outlined),
      ),
    );
  }
}

class _ProductImagePlaceholder extends StatelessWidget {
  const _ProductImagePlaceholder();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.add_photo_alternate_outlined, size: 34),
          SizedBox(height: 8),
          Text('Tap to upload product images'),
        ],
      ),
    );
  }
}
