<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Services\NotificationService;

class PromotionController extends Controller
{
    /**
     * Format a promotion for API response.
     */
    private function formatPromotion(Promotion $promo): array
    {
        return [
            'id'              => $promo->id,
            'name'            => $promo->name,
            'type'            => $promo->type,
            'scheme'          => $promo->scheme,
            'benefit'         => $promo->benefit,
            'products'        => $promo->products ?? [],
            'days'            => $promo->days ?? [],
            'date_start'      => $promo->date_start?->format('Y-m-d'),
            'date_end'        => $promo->date_end?->format('Y-m-d'),
            'time_start'      => $promo->time_start,
            'time_end'        => $promo->time_end,
            'active'          => (bool) $promo->active,
            'show_on_landing' => (bool) $promo->show_on_landing,
            'aplica_en'       => $promo->aplica_en ?? 'pedidos',
            'mensaje_banner'  => $promo->mensaje_banner,
            'fecha_inicio'    => $promo->fecha_inicio?->format('Y-m-d'),
            'fecha_fin'       => $promo->fecha_fin?->format('Y-m-d'),
        ];
    }

    /**
     * GET /api/admin/promotions
     */
    public function index()
    {
        $promotions = Promotion::orderBy('created_at', 'desc')->get();

        $formatted = $promotions->map(fn($p) => $this->formatPromotion($p));

        $activePromos = $promotions->where('active', true);
        $masPopular = $activePromos->first();

        return response()->json([
            'promotions' => $formatted,
            'resumen' => [
                'activas'      => $activePromos->count(),
                'mas_popular'  => $masPopular ? $masPopular->name : null,
                'ventas_total' => 0.00,
            ],
        ]);
    }

    /**
     * POST /api/admin/promotions
     */
    public function store(StorePromotionRequest $request)
    {
        $validated = $request->validated();

        // ── Mapeo campos frontend → columnas reales de BD ───────────────────
        $data = [
            'name'           => trim(strip_tags($validated['name'])),
            'type'           => $validated['type'],
            'scheme'         => $validated['scheme'] ?? null,
            'benefit'        => isset($validated['benefit'])
                ? trim(strip_tags($validated['benefit']))
                : null,
            'products'       => $validated['applicable_products'],
            'days'           => $validated['valid_days'],
            'active'         => $validated['is_active'],
            'show_on_landing' => true,
            'aplica_en'      => $validated['aplica_en'] ?? 'pedidos',
            'mensaje_banner' => isset($validated['mensaje_banner'])
                ? trim(strip_tags($validated['mensaje_banner']))
                : null,
        ];

        // ── Lógica de negocio: si no hay rango de fechas → forzar null ──────
        if ($validated['has_date_range']) {
            $data['date_start'] = $validated['start_date'];
            $data['date_end']   = $validated['end_date'];
        } else {
            $data['date_start'] = null;
            $data['date_end']   = null;
        }

        // ── Lógica de negocio: si no hay rango de horario → forzar null ─────
        if ($validated['has_time_range']) {
            $data['time_start'] = $validated['start_time'];
            $data['time_end']   = $validated['end_time'];
        } else {
            $data['time_start'] = null;
            $data['time_end']   = null;
        }

        $promotion = Promotion::create($data);

        NotificationService::create(
            'promotion_created',
            'Promoción Creada',
            "Se creó la promoción '{$promotion->name}'",
            ['promotion_id' => $promotion->id]
        );

        return response()->json($this->formatPromotion($promotion), 201);
    }

    /**
     * PUT /api/admin/promotions/{id}
     */
    public function update(UpdatePromotionRequest $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $validated = $request->validated();

        // ── Mapeo campos frontend → columnas reales de BD ───────────────────
        $data = [];

        if (array_key_exists('name', $validated)) {
            $data['name'] = trim(strip_tags($validated['name']));
        }
        if (array_key_exists('type', $validated)) {
            $data['type'] = $validated['type'];
        }
        if (array_key_exists('scheme', $validated)) {
            $data['scheme'] = $validated['scheme'];
        }
        if (array_key_exists('benefit', $validated)) {
            $data['benefit'] = $validated['benefit'] !== null
                ? trim(strip_tags($validated['benefit']))
                : null;
        }
        if (array_key_exists('applicable_products', $validated)) {
            $data['products'] = $validated['applicable_products'];
        }
        if (array_key_exists('valid_days', $validated)) {
            $data['days'] = $validated['valid_days'];
        }
        if (array_key_exists('is_active', $validated)) {
            $data['active'] = $validated['is_active'];
        }
        if (array_key_exists('aplica_en', $validated)) {
            $data['aplica_en'] = $validated['aplica_en'];
        }
        if (array_key_exists('mensaje_banner', $validated)) {
            $data['mensaje_banner'] = $validated['mensaje_banner'] !== null
                ? trim(strip_tags($validated['mensaje_banner']))
                : null;
        }

        // Siempre forzar show_on_landing = true
        $data['show_on_landing'] = true;

        // ── Lógica de negocio: si no hay rango de fechas → forzar null ──────
        if (array_key_exists('has_date_range', $validated)) {
            if ($validated['has_date_range']) {
                $data['date_start'] = $validated['start_date'] ?? null;
                $data['date_end']   = $validated['end_date']   ?? null;
            } else {
                $data['date_start'] = null;
                $data['date_end']   = null;
            }
        }

        // ── Lógica de negocio: si no hay rango de horario → forzar null ─────
        if (array_key_exists('has_time_range', $validated)) {
            if ($validated['has_time_range']) {
                $data['time_start'] = $validated['start_time'] ?? null;
                $data['time_end']   = $validated['end_time']   ?? null;
            } else {
                $data['time_start'] = null;
                $data['time_end']   = null;
            }
        }

        $promotion->update($data);

        NotificationService::create(
            'promotion_updated',
            'Promoción Actualizada',
            "Se actualizó la promoción '{$promotion->name}'",
            ['promotion_id' => $promotion->id]
        );

        return response()->json($this->formatPromotion($promotion));
    }

    /**
     * DELETE /api/admin/promotions/{id}
     */
    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $name = $promotion->name;

        $promotion->delete();

        NotificationService::create(
            'promotion_deleted',
            'Promoción Eliminada',
            "Se eliminó la promoción '{$name}'",
            ['promotion_id' => $id]
        );

        return response()->json(['message' => 'Promoción eliminada correctamente']);
    }

    /**
     * PATCH /api/admin/promotions/{id}/toggle
     */
    public function toggle($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->active = !$promotion->active;
        $promotion->save();

        $status = $promotion->active ? 'activada' : 'desactivada';

        NotificationService::create(
            'promotion_toggled',
            'Promoción ' . ucfirst($status),
            "Se {$status} la promoción '{$promotion->name}'",
            ['promotion_id' => $promotion->id]
        );

        return response()->json($this->formatPromotion($promotion));
    }

    /**
     * GET /api/promotions (Público)
     */
    public function publicIndex()
    {
        $promotions = Promotion::where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $promotions->map(fn($p) => $this->formatPromotion($p));

        return response()->json($formatted);
    }
}
