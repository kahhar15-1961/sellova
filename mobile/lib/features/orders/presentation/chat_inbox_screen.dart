import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/providers/repository_providers.dart';
import '../data/order_repository.dart';

class ChatInboxScreen extends ConsumerStatefulWidget {
  const ChatInboxScreen({super.key});

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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        title: const Text('Messages'),
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
                        onTap: () {
                          if (orderId != null && orderId > 0) {
                            context.push('/orders/$orderId/chat');
                            return;
                          }
                          context.push('/chats/thread/${t.id}?title=${Uri.encodeComponent(t.subject)}');
                        },
                        contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
                        leading: CircleAvatar(
                          backgroundColor: const Color(0xFFF1F5F9),
                          child: Icon(
                            t.kind == 'support' ? Icons.support_agent_rounded : Icons.chat_bubble_outline_rounded,
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

