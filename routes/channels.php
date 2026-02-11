<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatSession;
use App\Models\MerchantTicket;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    // Debug: Log the incoming user & requested channel id
    info('[Broadcast Auth] App.Models.User Attempt', [
        'authenticated_user_id' => optional($user)->id,
        'requested_id' => $id,
    ]);
    return (int) $user->id === (int) $id;
});

// Channel for individual chat sessions
Broadcast::channel('chat-session.{sessionId}', function ($user, $sessionId) {
    // Debug: Log the request details
    info('[Broadcast Auth] chat-session Attempt', [
        'authenticated_user_id' => optional($user)->id,
        'session_id' => $sessionId,
    ]);
    $chatSession = ChatSession::find($sessionId);
    info('[Broadcast Auth] chat-session Model Lookup', ['found' => (bool) $chatSession]);

    // // If the authenticated user is an admin-type role, allow regardless of model lookup
    // if ($user && $user->hasAnyPermission(['Super Admin', 'Admin', 'Support Agent'])) {
    //     info('[Broadcast Auth] chat-session Authorization Result', ['authorized' => true, 'reason' => 'Admin override']);
    //     return true;
    // }

    if (!$chatSession) {
        info('[Broadcast Auth] chat-session Failed - Session not found');
        return false;
    }

    // Authorise if the chat session actually belongs to the authenticated user (customer side)
    $authorized = (int) $chatSession->customer_id === (int) $user->id;
    info('[Broadcast Auth] chat-session Authorization Result', ['authorized' => $authorized]);
    return $authorized;
});

Broadcast::channel('merchant-chat.{sessionId}', function ($user, $sessionId) {
    $authorized = $user;
    return $authorized;
});

Broadcast::channel('merchant-chat-sessions', function ($user) {
    return $user->hasAnyPermission(['Merchant Ticket Chat access']);
});

// Temporary: Make this a public channel for testing
Broadcast::channel('chat-admin-public', function () {
    info('[Broadcast Auth] chat-admin-public Accessed');
    return true; // Allow anyone - just for testing
});

// Channel for admin panel to receive all chat updates
Broadcast::channel('chat-admin', function ($user) {
    $authorized = $user && $user->hasAnyPermission(['Merchant Ticket Chat access']);
    return $authorized;
});

// Channel for general chat notifications
Broadcast::channel('chat-notifications.{userId}', function ($user, $userId) {
    info('[Broadcast Auth] chat-notifications Attempt', [
        'authenticated_user_id' => optional($user)->id,
        'requested_user_id' => $userId,
    ]);
    $authorized = (int) $user->id === (int) $userId;
    info('[Broadcast Auth] chat-notifications Result', ['authorized' => $authorized]);
    return $authorized;
});

Broadcast::channel('merchant-ticket.{ticketId}', function ($user, $ticketId) {
    if ($user && $user->hasAnyPermission(['Ticket access'])) {
        return true;
    }

    $ticket = MerchantTicket::find($ticketId);
    if (!$ticket) {
        info('[Broadcast Auth] merchant-ticket Failed - Ticket not found');
        return false;
    }

    $authorized = (int) $ticket->merchant_id === (int) $user->id;
    return $authorized;
});

Broadcast::channel('merchant-tickets', function ($user) {
    $authorized = $user && $user->hasAnyPermission(['Ticket access']);
    return $authorized;
});

Broadcast::channel('notifications', function ($user) {
    return auth()->check();
});

Broadcast::channel('message-notifications', function ($user) {
    $authorized = $user && $user->hasAnyPermission(['Notification access']);
    return $authorized;
});

Broadcast::channel('public-notifications-admin', function ($user) {
    $authorized = $user && $user->hasAnyPermission(['Notification access']);
    return $authorized;
});

Broadcast::channel('private-notifications-{userId}', function ($user, $userId) {
    info('[Broadcast Auth] private-notifications-* Attempt', [
        'authenticated_user_id' => $user->id,
        'requested_user_id' => $userId,
    ]);
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    info('[Broadcast Auth] notifications-* Attempt', [
        'authenticated_user_id' => $user->id,
        'requested_user_id' => $userId,
    ]);
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('merchant-tickets', function () {
    return true; // You might want to add authentication logic here later
});

Broadcast::channel('merchant-ticket.{ticketId}', function () {
    return true; // You might want to add authentication logic here later
});

Broadcast::channel('admin.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
