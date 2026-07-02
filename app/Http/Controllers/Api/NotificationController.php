<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Centre de notifications in-app (PRD §3.8.4). Driver `database` : chaque ligne
 * appartient au destinataire via la relation polymorphe `notifiable`. Toujours
 * auto-scopé sur l'utilisateur authentifié (`$request->user()->notifications`),
 * jamais d'identifiant accepté du client → un membre ne lit que ses propres
 * notifications (CLAUDE.md §3.1).
 */
final class NotificationController extends Controller
{
    /** Liste paginée + compteur de non-lues (badge de la cloche). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(20);

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]])
            ->response();
    }

    /** Marque une notification comme lue (auto-scopé). */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $target = $request->user()->notifications()->findOrFail($notification);
        $target->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /** Marque toutes les notifications du membre comme lues (action bulk). */
    public function markAllAsRead(Request $request): JsonResponse
    {
        // UPDATE de masse : un seul ordre SQL (la collection `unreadNotifications`
        // ferait un UPDATE par ligne après avoir hydraté chaque notification).
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues.']);
    }
}
