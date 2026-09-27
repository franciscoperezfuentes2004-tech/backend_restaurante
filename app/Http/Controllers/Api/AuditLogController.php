<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    /**
     * Display a paginated listing of audit logs with filters.
     * GET /api/admin/bitacora
     */
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderBy('id', 'desc');

        // Búsqueda de texto libre sobre descripción, módulo y user_name
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                  ->orWhere('modulo', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('accion', 'like', "%{$search}%");
            });
        }

        // Filtro por Módulo / Grupo de Módulos
        if ($request->filled('modulo') && $request->query('modulo') !== 'all') {
            $this->applyModuleFilter($query, trim($request->query('modulo')));
        }

        // Filtro por Usuario
        if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
            $query->where('user_id', $request->query('user_id'));
        } elseif ($request->filled('usuario') && $request->query('usuario') !== 'all') {
            $query->where('user_name', $request->query('usuario'));
        } elseif ($request->filled('user') && $request->query('user') !== 'all') {
            $query->where('user_name', $request->query('user'));
        }

        // Filtro por Fechas (Directas o por preset)
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->query('fecha_inicio'));
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->query('fecha_fin'));
        }

        if ($request->filled('date_range') && $request->query('date_range') !== 'all') {
            $range = $request->query('date_range');
            if ($range === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($range === 'week') {
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($range === 'month') {
                $query->whereDate('created_at', '>=', Carbon::now()->startOfMonth());
            }
        }

        $perPage = max(1, min((int)$request->query('per_page', 20), 100));
        $paginated = $query->paginate($perPage);

        // Listas únicas para los selectores de filtro
        $modulos = AuditLog::select('modulo')->distinct()->orderBy('modulo')->pluck('modulo')->filter()->values();
        $usuarios = AuditLog::select('user_name')->distinct()->orderBy('user_name')->pluck('user_name')->filter()->values();

        return response()->json([
            'audit_logs'   => $paginated->items(),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'modulos'      => $modulos,
            'usuarios'     => $usuarios,
        ]);
    }

    /**
     * Get distinct list of modules present in audit_logs.
     * GET /api/admin/bitacora/modulos
     */
    public function getModulos()
    {
        $modulos = AuditLog::select('modulo')->distinct()->orderBy('modulo')->pluck('modulo')->filter()->values();
        return response()->json(['modulos' => $modulos]);
    }

    /**
     * Export audit logs in Excel / CSV / PDF format.
     * GET /api/admin/bitacora/export/{format}
     */
    public function export(Request $request, string $format)
    {
        $format = strtolower($format);
        $query = AuditLog::query()->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                  ->orWhere('modulo', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('accion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('modulo') && $request->query('modulo') !== 'all') {
            $this->applyModuleFilter($query, trim($request->query('modulo')));
        }

        if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
            $query->where('user_id', $request->query('user_id'));
        } elseif ($request->filled('usuario') && $request->query('usuario') !== 'all') {
            $query->where('user_name', $request->query('usuario'));
        } elseif ($request->filled('user') && $request->query('user') !== 'all') {
            $query->where('user_name', $request->query('user'));
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->query('fecha_inicio'));
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->query('fecha_fin'));
        }

        if ($request->filled('date_range') && $request->query('date_range') !== 'all') {
            $range = $request->query('date_range');
            if ($range === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($range === 'week') {
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($range === 'month') {
                $query->whereDate('created_at', '>=', Carbon::now()->startOfMonth());
            }
        }

        $logs = $query->limit(5000)->get();

        $headers = [
            'ID', 'Fecha', 'Usuario', 'Rol', 'Módulo', 'Acción', 'Descripción', 'Dirección IP'
        ];

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = [
                $log->id,
                $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                $log->user_name ?? 'Sistema',
                $log->role ?? '—',
                $log->modulo,
                strtoupper($log->accion),
                $log->descripcion,
                $log->ip_address ?? '—',
            ];
        }

        $dateStr = now()->format('Y-m-d');

        if ($format === 'excel' || $format === 'csv') {
            $output = "\u{FEFF}"; // UTF-8 BOM
            $output .= implode(',', $headers) . "\n";
            foreach ($rows as $row) {
                $escaped = array_map(function ($val) {
                    $str = str_replace('"', '""', (string)$val);
                    return '"' . $str . '"';
                }, $row);
                $output .= implode(',', $escaped) . "\n";
            }

            $ext = 'csv';
            $filename = "bitacora_aurum_{$dateStr}.{$ext}";

            return response($output, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Default / PDF format: Returns CSV for download
        $output = "\u{FEFF}";
        $output .= implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $escaped = array_map(function ($val) {
                $str = str_replace('"', '""', (string)$val);
                return '"' . $str . '"';
            }, $row);
            $output .= implode(',', $escaped) . "\n";
        }

        return response($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"bitacora_aurum_{$dateStr}.csv\"",
        ]);
    }

    /**
     * Translates a module group or exact module string into database query conditions
     */
    private function applyModuleFilter($query, string $moduloInput): void
    {
        if (empty($moduloInput) || $moduloInput === 'all') {
            return;
        }

        $groups = [
            'General'        => ['Dashboard', 'General'],
            'Menú'           => ['Categorías', 'Platillos', 'Extras', 'Menu', 'Menú'],
            'Operaciones'    => ['Pedidos', 'Reservaciones', 'Delivery', 'Operaciones'],
            'Marketing'      => ['Promociones', 'Reseñas', 'Marketing'],
            'Inventario'     => ['Ingredientes', 'Stock', 'Proveedores', 'Categorías de Ingredientes', 'Movimientos de Stock', 'Inventario'],
            'Finanzas'       => ['Ventas', 'Pagos', 'Reportes', 'Costos', 'Finanzas'],
            'Administración' => ['Bitácora', 'Bitácora de Auditoría', 'Usuarios', 'Usuarios y roles', 'Permisos', 'Autenticación', 'Administración'],
            'Configuración'  => ['Áreas', 'Áreas del local', 'Configuración General', 'Configuración', 'Personalizar Landing', 'Landing'],
        ];

        $matchedViews = null;
        foreach ($groups as $groupName => $views) {
            if (mb_strtolower($groupName) === mb_strtolower($moduloInput)) {
                $matchedViews = $views;
                break;
            }
        }

        if ($matchedViews) {
            $query->whereIn('modulo', $matchedViews);
        } else {
            $query->where(function ($q) use ($moduloInput) {
                $q->where('modulo', $moduloInput)
                  ->orWhere('modulo', 'like', "%{$moduloInput}%");
            });
        }
    }
}
