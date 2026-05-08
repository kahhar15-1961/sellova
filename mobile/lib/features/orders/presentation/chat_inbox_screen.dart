import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../application/chat_unread_provider.dart';
import '../data/order_repository.dart';

class ChatInboxScreen extends ConsumerStatefulWidget {
  const ChatInboxScreen({super.key, this.panel = 'buyer'});

  final String panel;

  @override
  ConsumerState<ChatInboxScreen> createState() => _ChatInboxScreenState();
}

class _ChatInboxScreenState extends ConsumerState<ChatInboxScreen> {
  bool _loading = true;
  List<ChatThreadDto> _threads = const <ChatThreadDto>[];

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_load);
  }

  bool get _sellerPanel => widget.panel == 'seller';

  void _goBack() {
    if (context.canPop()) {
      context.pop();
      return;
    }
    context.go(_sellerPanel ? '/seller/dashboard' : '/home');
  }

  void _openPrimaryDestination() {
    context.go(_sellerPanel ? '/seller/dashboard' : '/home');
  }

  void _openMenuDestination() {
    context.go(_sellerPanel ? '/seller/menu' : '/profile');
  }

  Future<void> _load() async {
    try {
      final threads = await ref.read(orderRepositoryProvider).listChatThreads();
      if (!mounted) return;
      setState(() {
        _threads = threads;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  Future<void> _markThreadRead(ChatThreadDto thread) async {
    if (!thread.hasUnread) {
      return;
    }
    await ref.read(orderRepositoryProvider).markChatThreadRead(thread.id);
    ref.invalidate(chatUnreadCountProvider);
    if (!mounted) {
      return;
    }
    setState(() {
      _threads = _threads
          .map(
            (item) => item.id == thread.id
                ? ChatThreadDto(<String, dynamic>{
                    ...item.raw,
                    'has_unread': false,
                  })
                : item,
          )
          .toList();
    });
  }

  Future<void> _markAllRead() async {
    final unreadIds =
        _threads.where((thread) => thread.hasUnread).map((thread) => thread.id);
    if (unreadIds.isEmpty) {
      return;
    }
    await ref.read(orderRepositoryProvider).markChatThreadsRead(unreadIds);
    ref.invalidate(chatUnreadCountProvider);
    if (!mounted) {
      return;
    }
    setState(() {
      _threads = _threads
          .map(
            (item) => ChatThreadDto(<String, dynamic>{
              ...item.raw,
              'has_unread': false,
            }),
          )
          .toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    final hasUnread = _threads.any((thread) => thread.hasUnread);
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        title: const Text('Messages'),
        leading: IconButton(
          icon: Icon(_sellerPanel
              ? Icons.menu_rounded
              : Icons.arrow_back_ios_new_rounded),
          onPressed: _sellerPanel ? _openMenuDestination : _goBack,
          tooltip: _sellerPanel ? 'Seller menu' : 'Back',
        ),
        actions: <Widget>[
          if (hasUnread)
            IconButton(
              icon: const Icon(Icons.done_all_rounded),
              onPressed: _markAllRead,
              tooltip: 'Mark all read',
            ),
          IconButton(
            icon: const Icon(Icons.home_outlined),
            onPressed: _openPrimaryDestination,
            tooltip: _sellerPanel ? 'Seller dashboard' : 'Home',
          ),
          IconButton(
            icon: Icon(_sellerPanel
                ? Icons.storefront_outlined
                : Icons.person_outline_rounded),
            onPressed: _openMenuDestination,
            tooltip: _sellerPanel ? 'Seller menu' : 'Profile',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _threads.isEmpty
                ? ListView(
                    children: const <Widget>[
                      SizedBox(height: 120),
                      Center(child: Text('No conversations yet.')),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(14, 10, 14, 20),
                    itemBuilder: (_, i) {
                      final t = _threads[i];
                      final orderId = (t.raw['order_id'] as num?)?.toInt();
                      return ListTile(
                        onTap: () async {
                          final router = GoRouter.of(context);
                          await _markThreadRead(t);
                          if (!mounted) {
                            return;
                          }
                          if (orderId != null && orderId > 0) {
                            await router.push('/orders/$orderId/chat');
                            if (mounted) {
                              await _load();
                            }
                            return;
                          }
                          await router.push(
                            '/chats/thread/${t.id}'
                            '?title=${Uri.encodeComponent(t.subject)}'
                            '&panel=${Uri.encodeComponent(widget.panel)}',
                          );
                          if (mounted) {
                            await _load();
                          }
                        },
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 4, vertical: 6),
                        leading: CircleAvatar(
                          backgroundColor: const Color(0xFFF1F5F9),
                          child: Icon(
                            t.kind == 'support'
                                ? Icons.support_agent_rounded
                                : Icons.chat_bubble_outline_rounded,
                            color: const Color(0xFF334155),
                          ),
                        ),
                        title: Text(
                          t.subject,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          t.preview.isEmpty ? t.counterparty : t.preview,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: t.hasUnread
                            ? Container(
                                width: 10,
                                height: 10,
                                decoration: const BoxDecoration(
                                  color: Color(0xFF7C3AED),
                                  shape: BoxShape.circle,
                                ),
                              )
                            : null,
                      );
                    },
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemCount: _threads.length,
                  ),
      ),
    );
  }
}
