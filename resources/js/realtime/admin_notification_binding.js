import { getEcho } from '@/realtime/echo';

export function subscribeAdminNotifications(userId, onNotificationCreated) {
    if (!userId) {
        return () => {};
    }

    const echo = getEcho();
    if (!echo) {
        return () => {};
    }

    const channelName = `App.Models.User.${userId}`;
    const channel = echo.private(channelName);

    const listener = (payload) => {
        const data = payload && typeof payload === 'object' ? payload : {};
        const notification = data.notification && typeof data.notification === 'object' ? data.notification : {};
        const unreadCount = Number.isFinite(Number(data.unread_count)) ? Number(data.unread_count) : 0;

        onNotificationCreated(notification, unreadCount);
    };

    channel.listen('.notification.created', listener);

    return () => {
        channel.stopListening('.notification.created');
        echo.leave(channelName);
    };
}
