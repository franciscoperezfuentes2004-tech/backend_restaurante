<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 1. Obtener notificaciones aislando por usuario (Solo ve las suyas o del sistema)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'total_no_leidas' => 0,
                'notificaciones'  => [],
                'data'            => [],
            ]);
        }

        // Aislamiento estricto de usuario (Nunca Notification::all())
        $query = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->hasAnyRole(['admin', 'super_admin', 'gerente'])) {
                    $q->orWhereNull('user_id');
                }
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $paginated = $query->paginate(50);
        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $mapped = $paginated->getCollection()->map(function ($n) {
            return [
                'id'         => $n->id,
                'tipo'       => $n->type,
                'title'      => $n->title,
                'mensaje'    => $n->message,
                'data'       => $n->data,
                'leida'      => !is_null($n->read_at),
                'read_at'    => $n->read_at ? $n->read_at->toIso8601String() : null,
                'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });

        return response()->json([
            'total_no_leidas' => $unreadCount,
            'notificaciones'  => $mapped,
            'data'            => $mapped,
            'current_page'    => $paginated->currentPage(),
            'last_page'       => $paginated->lastPage(),
            'per_page'        => $paginated->perPage(),
            'total'           => $paginated->total(),
        ]);
    }

    /**
     * Marcar notificación individual como leída
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('id', $id)
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->hasAnyRole(['admin', 'super_admin', 'gerente'])) {
                        $q->orWhereNull('user_id');
                    }
                }
            })
            ->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'mensaje' => 'Notificación marcada como leída.',
        ]);
    }

    /**
     * 2. Marcar como leídas (Botón: Marcar todo leído / Limpiar todo)
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Notification::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->hasAnyRole(['admin', 'super_admin', 'gerente'])) {
                        $q->orWhereNull('user_id');
                    }
                })
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notificaciones actualizadas',
            'mensaje' => 'Notificaciones actualizadas',
        ]);
    }

    /**
     * Soft Delete en lugar de Borrado Físico (Botón: Limpiar todo)
     * No hace DELETE físico en PostgreSQL para no perder auditoría.
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Notification::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->hasAnyRole(['admin', 'super_admin', 'gerente'])) {
                        $q->orWhereNull('user_id');
                    }
                })
                ->delete(); // Soft delete en PostgreSQL
        }

        return response()->json([
            'message' => 'Todas las notificaciones eliminadas.',
            'mensaje' => 'Todas las notificaciones eliminadas.',
        ]);
    }
}
