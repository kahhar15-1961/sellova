import { Form, Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { PageHeader } from '@/components/admin/PageHeader';
import { StatusBadge } from '@/components/admin/StatusBadge';
import { getEcho } from '@/realtime/echo';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

function fmtDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString(); } catch { return String(iso); }
}

function fmtShortTime(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return '';
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function postJson(url, body) {
    const r = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    if (!r.ok) {
        throw new Error('Request failed');
    }
    return r.json().catch(() => ({}));
}

function mergeThreadReads(previous, event) {
    const list = [...(previous || [])];
    const i = list.findIndex((r) => r.user_id === event.user_id);
    const incoming = Number(event.last_read_message_id);
    const row = {
        user_id: event.user_id,
        last_read_message_id: i >= 0 ? Math.max(incoming, Number(list[i].last_read_message_id)) : incoming,
        reader_name: event.reader_name,
    };
    if (i >= 0) {
        list[i] = { ...list[i], ...row };
    } else {
        list.push(row);
    }
    return list;
}

/**
 * Receipts for your own bubbles: server delivery timestamp + strict “everyone on thread” read bar.
 * Required readers = authors ∪ requester ∪ anyone with a read cursor (matches backend).
 */
function readReceiptLabel(message, myId, threadReads, requiredReaderIds) {
    const authorId = message.author_user_id;
    if (authorId == null || myId == null || Number(authorId) !== Number(myId)) {
        return null;
    }

    const deliveredIso = message.delivered_at || message.created_at;
    const serverTime = deliveredIso ? fmtShortTime(deliveredIso) : '';
    const serverBit = serverTime ? `Server ${serverTime}` : 'Saved to server';

    const required = [...new Set((requiredReaderIds || []).map((id) => Number(id)))].filter((id) => id > 0);
    const othersRequired = required.filter((id) => Number(id) !== Number(authorId));

    if (othersRequired.length === 0) {
        return {
            state: 'delivered_only',
            text: `${serverBit} · no other reviewers on this thread yet`,
            checks: 1,
        };
    }

    const readersCaughtUp = othersRequired.filter((uid) => {
        const row = (threadReads || []).find((r) => Number(r.user_id) === Number(uid));
        return row && Number(row.last_read_message_id) >= Number(message.id);
    });

    if (readersCaughtUp.length === othersRequired.length) {
        return {
            state: 'all_read',
            text: `${serverBit} · read by everyone on thread (${othersRequired.length})`,
            checks: 2,
        };
    }

    if (readersCaughtUp.length > 0) {
        const labels = readersCaughtUp.map((uid) => {
            const row = (threadReads || []).find((r) => Number(r.user_id) === Number(uid));
            return row?.reader_name || `User ${uid}`;
        });
        const shown = labels.slice(0, 2).join(', ');
        const extra = labels.length > 2 ? ` +${labels.length - 2}` : '';
        return {
            state: 'partial',
            text: `${serverBit} · read ${readersCaughtUp.length}/${othersRequired.length}: ${shown}${extra}`,
            checks: 2,
        };
    }

    return {
        state: 'delivered_await',
        text: `${serverBit} · awaiting ${othersRequired.length} reviewer(s)`,
        checks: 1,
    };
}

function receiptClassName(state) {
    if (state === 'all_read') return 'text-emerald-700 dark:text-emerald-400';
    if (state === 'partial') return 'text-sky-800 dark:text-sky-300';
    return 'text-muted-foreground';
}

export default function ApprovalsIndex({
    header,
    filters,
    index_url,
    approvals,
    selected,
    messages,
    thread_reads: initialThreadReads = [],
    required_reader_ids: initialRequiredReaderIds = [],
}) {
    const f = filters || {};
    const { auth } = usePage().props;
    const myId = auth?.user?.id ?? null;

    const [liveMessages, setLiveMessages] = useState(messages || []);
    const [threadReads, setThreadReads] = useState(initialThreadReads || []);
    const [requiredReaderIds, setRequiredReaderIds] = useState(initialRequiredReaderIds || []);
    const [liveStatus, setLiveStatus] = useState('live');
    const [transport, setTransport] = useState('polling');
    const [onlineMembers, setOnlineMembers] = useState([]);
    const [typingNames, setTypingNames] = useState([]);

    const typingTimeoutsRef = useRef(new Map());
    const readDebounceRef = useRef(null);
    const typingIdleRef = useRef(null);
    const typingPingRef = useRef(null);
    const lastReadPostedRef = useRef(0);

    useEffect(() => {
        setLiveMessages(messages || []);
    }, [messages, selected?.id]);

    useEffect(() => {
        setThreadReads(initialThreadReads || []);
        setRequiredReaderIds(initialRequiredReaderIds || []);
        const mine = (initialThreadReads || []).find((r) => myId != null && Number(r.user_id) === Number(myId));
        lastReadPostedRef.current = mine ? Number(mine.last_read_message_id) : 0;
    }, [initialThreadReads, initialRequiredReaderIds, selected?.id, myId]);

    useEffect(() => {
        if (!selected?.messages_api_url) return undefined;
        const id = setInterval(() => {
            fetch(selected.messages_api_url, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((payload) => {
                    setLiveMessages(payload.messages || []);
                    if (payload.thread_reads) {
                        setThreadReads(payload.thread_reads);
                        const mine = payload.thread_reads.find((r) => myId != null && Number(r.user_id) === Number(myId));
                        if (mine) {
                            lastReadPostedRef.current = Math.max(
                                lastReadPostedRef.current,
                                Number(mine.last_read_message_id),
                            );
                        }
                    }
                    if (payload.required_reader_ids) {
                        setRequiredReaderIds(payload.required_reader_ids);
                    }
                    setLiveStatus('live');
                })
                .catch(() => setLiveStatus('reconnecting'));
        }, 2500);
        return () => clearInterval(id);
    }, [selected?.messages_api_url, myId]);

    const flushTypingIndicator = useCallback((userId) => {
        const t = typingTimeoutsRef.current.get(userId);
        if (t) clearTimeout(t);
        typingTimeoutsRef.current.delete(userId);
        setTypingNames((prev) => prev.filter((x) => x.userId !== userId));
    }, []);

    const bumpTypingIndicator = useCallback((userId, name) => {
        if (myId != null && Number(userId) === Number(myId)) return;
        const existing = typingTimeoutsRef.current.get(userId);
        if (existing) clearTimeout(existing);
        setTypingNames((prev) => {
            const rest = prev.filter((x) => x.userId !== userId);
            return [...rest, { userId, name: name || `User ${userId}` }];
        });
        const t = setTimeout(() => flushTypingIndicator(userId), 4000);
        typingTimeoutsRef.current.set(userId, t);
    }, [flushTypingIndicator, myId]);

    useEffect(() => {
        return () => {
            typingTimeoutsRef.current.forEach((t) => clearTimeout(t));
            typingTimeoutsRef.current.clear();
        };
    }, []);

    useEffect(() => {
        if (!selected?.id) return undefined;

        const echo = getEcho();
        if (!echo) {
            setTransport('polling');
            return undefined;
        }

        const channelName = `admin.approval.${selected.id}`;
        setTransport('websocket');

        const channel = echo.join(channelName);

        channel
            .here((users) => {
                setOnlineMembers(Array.isArray(users) ? users : []);
                setLiveStatus('live');
            })
            .joining((user) => {
                setOnlineMembers((prev) => {
                    const id = user?.id;
                    if (id == null) return prev;
                    if (prev.some((m) => Number(m.id) === Number(id))) return prev;
                    return [...prev, user];
                });
            })
            .leaving((user) => {
                const id = user?.id;
                if (id == null) return;
                setOnlineMembers((prev) => prev.filter((m) => Number(m.id) !== Number(id)));
            })
            .listen('.admin.approval.message.created', (event) => {
                if (!event?.message) return;
                setLiveMessages((previous) => {
                    const existing = new Set((previous || []).map((m) => m.id));
                    if (existing.has(event.message.id)) {
                        return previous;
                    }
                    return [...(previous || []), event.message];
                });
                setLiveStatus('live');
            })
            .listen('.admin.approval.user.typing', (event) => {
                if (!event?.typing) {
                    flushTypingIndicator(event.user_id);
                    return;
                }
                bumpTypingIndicator(event.user_id, event.name);
            })
            .listen('.admin.approval.read.updated', (event) => {
                setThreadReads((prev) => mergeThreadReads(prev, event));
            })
            .error(() => {
                setLiveStatus('reconnecting');
                setTransport('polling');
            });

        return () => {
            echo.leave(channelName);
            setOnlineMembers([]);
            typingTimeoutsRef.current.forEach((t) => clearTimeout(t));
            typingTimeoutsRef.current.clear();
            setTypingNames([]);
        };
    }, [selected?.id, bumpTypingIndicator, flushTypingIndicator]);

    useEffect(() => {
        if (!selected?.read_url || myId == null) return undefined;
        const list = liveMessages || [];
        if (list.length === 0) return undefined;
        const maxId = Math.max(...list.map((m) => Number(m.id)));
        if (!Number.isFinite(maxId)) return undefined;

        if (maxId <= lastReadPostedRef.current) return undefined;

        if (readDebounceRef.current) clearTimeout(readDebounceRef.current);
        readDebounceRef.current = setTimeout(() => {
            postJson(selected.read_url, { last_read_message_id: maxId })
                .then(() => {
                    lastReadPostedRef.current = maxId;
                })
                .catch(() => {});
        }, 500);

        return () => {
            if (readDebounceRef.current) clearTimeout(readDebounceRef.current);
        };
    }, [liveMessages, selected?.read_url, myId]);

    const sendTyping = useCallback((typing) => {
        if (!selected?.typing_url) return;
        postJson(selected.typing_url, { typing }).catch(() => {});
    }, [selected?.typing_url]);

    const onMessageInput = useCallback(() => {
        if (typingIdleRef.current) clearTimeout(typingIdleRef.current);
        if (typingPingRef.current) clearInterval(typingPingRef.current);

        typingIdleRef.current = setTimeout(() => {
            if (typingPingRef.current) {
                clearInterval(typingPingRef.current);
                typingPingRef.current = null;
            }
            sendTyping(false);
        }, 2200);

        if (!typingPingRef.current) {
            sendTyping(true);
            typingPingRef.current = setInterval(() => sendTyping(true), 2000);
        }
    }, [sendTyping]);

    const onMessageBlur = useCallback(() => {
        if (typingIdleRef.current) clearTimeout(typingIdleRef.current);
        if (typingPingRef.current) {
            clearInterval(typingPingRef.current);
            typingPingRef.current = null;
        }
        sendTyping(false);
    }, [sendTyping]);

    useEffect(() => () => {
        if (typingIdleRef.current) clearTimeout(typingIdleRef.current);
        if (typingPingRef.current) clearInterval(typingPingRef.current);
    }, []);

    const othersOnline = (onlineMembers || []).filter((m) => myId == null || Number(m.id) !== Number(myId));

    return (
        <AdminLayout>
            <Head title={header.title} />
            <PageHeader title={header.title} description={header.description} breadcrumbs={header.breadcrumbs} />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader><CardTitle>Queue</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex gap-2">
                            <select
                                value={f.status || 'pending'}
                                className="h-9 flex-1 rounded-md border px-2 text-sm"
                                onChange={(e) => router.get(index_url, { ...f, status: e.target.value }, { preserveState: true, replace: true })}
                            >
                                <option value="pending">pending</option>
                                <option value="approved">approved</option>
                                <option value="rejected">rejected</option>
                                <option value="all">all</option>
                            </select>
                            <input
                                defaultValue={f.q || ''}
                                className="h-9 flex-1 rounded-md border px-2 text-sm"
                                placeholder="search"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        router.get(index_url, { ...f, q: e.currentTarget.value }, { preserveState: true, replace: true });
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            {(approvals || []).map((a) => (
                                <button
                                    key={a.id}
                                    type="button"
                                    onClick={() => router.get(index_url, { ...f, approval_id: a.id }, { preserveState: true, replace: true })}
                                    className={`w-full rounded-md border p-3 text-left ${selected?.id === a.id ? 'border-primary bg-primary/5' : ''}`}
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium text-sm">{a.action_code}</p>
                                        <StatusBadge status={a.status} />
                                    </div>
                                    <p className="text-xs text-muted-foreground">{a.target_type} #{a.target_id}</p>
                                    <p className="text-xs text-muted-foreground">{a.requested_by} · {fmtDate(a.requested_at)}</p>
                                </button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader><CardTitle>Approval workspace {selected ? `#${selected.id}` : ''}</CardTitle></CardHeader>
                    <CardContent>
                        {!selected ? (
                            <p className="text-sm text-muted-foreground">No approval selected.</p>
                        ) : (
                            <div className="space-y-6">
                                <div className="rounded-md border p-3 text-sm">
                                    <p><strong>Action:</strong> {selected.action_code}</p>
                                    <p><strong>Target:</strong> {selected.target_type} #{selected.target_id}</p>
                                    <p><strong>Reason code:</strong> {selected.reason_code ?? '—'}</p>
                                    <p><strong>Requested by:</strong> {selected.requested_by}</p>
                                    <p><strong>Requested at:</strong> {fmtDate(selected.requested_at)}</p>
                                    <pre className="mt-2 overflow-auto rounded bg-muted p-2 text-xs">{JSON.stringify(selected.proposed_payload_json, null, 2)}</pre>
                                </div>

                                {selected.status === 'pending' ? (
                                    <Form action={selected.decision_url} method="post" className="rounded-md border p-3">
                                        <p className="mb-2 text-sm font-medium">Decision</p>
                                        <input name="decision_reason" className="h-9 w-full rounded-md border px-2 text-sm" placeholder="Decision reason" />
                                        <div className="mt-2 flex gap-2">
                                            <Button type="submit" name="decision" value="approve">Approve</Button>
                                            <Button type="submit" name="decision" value="reject" variant="outline">Reject</Button>
                                        </div>
                                    </Form>
                                ) : (
                                    <div className="rounded-md border p-3 text-sm text-muted-foreground">Decision: {selected.status} by {selected.approved_by ?? '—'} at {fmtDate(selected.decided_at)}</div>
                                )}

                                <div className="rounded-md border p-3">
                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-medium">Team chat</p>
                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            <span className={`rounded-full border px-2 py-0.5 ${liveStatus === 'live' ? 'border-emerald-500/40 text-emerald-700 dark:text-emerald-400' : 'border-amber-500/40 text-amber-800 dark:text-amber-300'}`}>
                                                {liveStatus === 'live' ? 'Connected' : 'Reconnecting…'}
                                            </span>
                                            <span className="rounded-full border border-border px-2 py-0.5 capitalize">{transport}</span>
                                        </div>
                                    </div>

                                    <div className="mb-3 rounded-md border bg-muted/30 px-3 py-2">
                                        <p className="text-xs font-medium text-muted-foreground">Online in this thread</p>
                                        {onlineMembers.length === 0 ? (
                                            <p className="mt-1 text-xs text-muted-foreground">No presence data (check Reverb / Vite env keys).</p>
                                        ) : (
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {onlineMembers.map((m) => (
                                                    <span
                                                        key={m.id}
                                                        className={`inline-flex max-w-[200px] truncate rounded-full border px-2 py-0.5 text-xs ${
                                                            myId != null && Number(m.id) === Number(myId)
                                                                ? 'border-primary/50 bg-primary/10 font-medium'
                                                                : 'border-border bg-card'
                                                        }`}
                                                        title={m.name}
                                                    >
                                                        {myId != null && Number(m.id) === Number(myId) ? 'You' : (m.name || `User ${m.id}`)}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        {othersOnline.length > 0 && (
                                            <p className="mt-1.5 text-[11px] text-muted-foreground">
                                                {othersOnline.length} teammate{othersOnline.length === 1 ? '' : 's'} viewing
                                            </p>
                                        )}
                                    </div>

                                    <p className="mb-2 text-[11px] text-muted-foreground">
                                        Receipts: server delivery time + reads from every author, the requester, and anyone who has opened this thread.
                                    </p>
                                    <div className="mb-3 max-h-72 space-y-2 overflow-auto rounded border bg-muted/20 p-2">
                                        {(liveMessages || []).length === 0 ? (
                                            <p className="text-xs text-muted-foreground">No messages yet.</p>
                                        ) : (
                                            (liveMessages || []).map((m) => {
                                                const mine = myId != null && Number(m.author_user_id) === Number(myId);
                                                const receipt = readReceiptLabel(m, myId, threadReads, requiredReaderIds);
                                                return (
                                                    <div
                                                        key={m.id}
                                                        className={`flex ${mine ? 'justify-end' : 'justify-start'}`}
                                                    >
                                                        <div
                                                            className={`max-w-[85%] rounded-lg border px-3 py-2 text-sm shadow-sm ${
                                                                mine
                                                                    ? 'border-primary/30 bg-primary/10'
                                                                    : 'border-border bg-card'
                                                            }`}
                                                        >
                                                            <p className="text-[11px] text-muted-foreground">
                                                                {mine ? 'You' : m.author} · {fmtDate(m.created_at)}
                                                            </p>
                                                            <p className="whitespace-pre-wrap break-words">{m.message}</p>
                                                            {receipt ? (
                                                                <p className={`mt-1 text-[11px] ${receiptClassName(receipt.state)}`}>
                                                                    {receipt.checks >= 2 ? '✓✓ ' : '✓ '}
                                                                    {receipt.text}
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                );
                                            })
                                        )}
                                        {typingNames.length > 0 ? (
                                            <p className="text-xs italic text-muted-foreground">
                                                {typingNames.map((t) => t.name).join(', ')}
                                                {typingNames.length === 1 ? ' is ' : ' are '}
                                                typing…
                                            </p>
                                        ) : null}
                                    </div>
                                    <Form action={selected.message_url} method="post" className="flex gap-2">
                                        <input
                                            name="message"
                                            className="h-9 flex-1 rounded-md border px-2 text-sm"
                                            placeholder="Write a message for approvers/reviewers"
                                            autoComplete="off"
                                            onInput={onMessageInput}
                                            onBlur={onMessageBlur}
                                        />
                                        <Button type="submit" size="sm">Send</Button>
                                    </Form>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
