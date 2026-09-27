<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::logAuditEvent($model, 'create', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            if (empty($changes)) {
                return;
            }

            // Exclude timestamp updates if nothing else changed
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }

            $before = [];
            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            self::logAuditEvent($model, 'update', $before, $changes);
        });

        static::deleted(function ($model) {
            self::logAuditEvent($model, 'delete', $model->getOriginal(), null);
        });
    }

    protected static function logAuditEvent($model, string $accion, ?array $before, ?array $after): void
    {
        try {
            $user = Auth::user();
            $rawModule = property_exists($model, 'auditModule') ? $model->auditModule : class_basename($model);
            
            $moduleMap = [
                'Category'           => 'Categorías',
                'IngredientCategory' => 'Categorías de Ingredientes',
                'Dish'               => 'Platillos',
                'Extra'              => 'Extras',
                'Order'              => 'Pedidos',
                'Reservation'        => 'Reservaciones',
                'Promotion'          => 'Promociones',
                'Testimonial'        => 'Reseñas',
                'Ingredient'         => 'Ingredientes',
                'Stock'              => 'Stock',
                'StockMovement'      => 'Movimientos de Stock',
                'Supplier'           => 'Proveedores',
                'User'               => 'Usuarios',
                'Area'               => 'Áreas',
            ];

            $modulo = $moduleMap[$rawModule] ?? $rawModule;

            $identifier = $model->name 
                       ?? $model->nombre 
                       ?? $model->customer_name 
                       ?? $model->title 
                       ?? $model->folio 
                       ?? ("ID #" . $model->getKey());

            $actionTextMap = [
                'create' => "Se creó el registro '{$identifier}' en el módulo de {$modulo}",
                'update' => "Se actualizó el registro '{$identifier}' en el módulo de {$modulo}",
                'delete' => "Se eliminó el registro '{$identifier}' en el módulo de {$modulo}",
            ];

            $descripcion = $actionTextMap[$accion] ?? "Evento en {$modulo}: {$identifier}";

            // Sanitize sensitive fields
            $sensitiveKeys = ['password', 'remember_token', 'secret_key', 'token'];
            if ($before) {
                foreach ($sensitiveKeys as $sKey) {
                    if (array_key_exists($sKey, $before)) {
                        $before[$sKey] = '[REDACTED]';
                    }
                }
            }
            if ($after) {
                foreach ($sensitiveKeys as $sKey) {
                    if (array_key_exists($sKey, $after)) {
                        $after[$sKey] = '[REDACTED]';
                    }
                }
            }

            AuditLog::create([
                'user_id'         => $user?->id,
                'user_name'       => $user?->name ?? 'Sistema',
                'role'            => $user?->role ?? 'sistema',
                'modulo'          => $modulo,
                'accion'          => $accion,
                'descripcion'     => $descripcion,
                'valores_antes'   => $before,
                'valores_despues' => $after,
                'ip_address'      => request()->ip() ?? '127.0.0.1',
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Auditable trait error: " . $e->getMessage());
        }
    }
}
