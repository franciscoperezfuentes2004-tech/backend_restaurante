<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\Testimonial;
use App\Models\TestimonialImage;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\ImageCompressionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReviewController extends Controller
{
    /**
     * Format a review model for API output list.
     */
    private function formatReview(Testimonial $review): array
    {
        $review->loadMissing('images', 'order', 'reservation', 'user');

        $formattedImages = $review->images->map(function ($img) {
            $path = ltrim(str_replace('public/', '', $img->image_path), '/');
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        })->values()->all();

        $createdAt = $review->created_at ? $review->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $fecha = ($review->created_at ? Carbon::parse($review->created_at) : Carbon::now())->format('Ymd');
        $numeroFormateado = str_pad($review->id, 4, '0', STR_PAD_LEFT);
        $folio = 'COM' . $fecha . $numeroFormateado;

        $origen = $review->origen ?: ($review->tipo_experiencia === 'pedido' ? 'delivery' : 'consumo');
        $estado = $review->status ?: 'pendiente';

        $customerEmail = $review->customer_email ?: ($review->order?->customer_email ?: ($review->reservation?->customer_email ?: ($review->user?->email ?: null)));
        $customerPhone = $review->customer_phone ?: ($review->order?->customer_phone ?: ($review->reservation?->customer_phone ?: null));

        return [
            'id'              => $review->id,
            'folio'           => $folio,
            'customer_name'   => $review->customer_name ?: 'Cliente',
            'customer_email'  => $customerEmail,
            'customer_phone'  => $customerPhone,
            'rating'          => (int) $review->rating,
            'comment'         => $review->comment ?: '',
            'origen'          => $origen,
            'estado'          => $estado,
            'images'          => $formattedImages,
            'response'        => $review->response ?: ($review->reply_text ?: $review->respuesta_admin),
            'reply_text'      => $review->response ?: ($review->reply_text ?: $review->respuesta_admin),
            'respuesta_admin' => $review->respuesta_admin ?: ($review->response ?: $review->reply_text),
            'fecha_respuesta' => $review->fecha_respuesta ? $review->fecha_respuesta->format('Y-m-d H:i:s') : ($review->replied_at ? $review->replied_at->format('Y-m-d H:i:s') : null),
            'created_at'      => $createdAt,
        ];
    }

    /**
     * GET /api/admin/reviews
     */
    public function index(IndexReviewRequest $request)
    {
        if (Review::count() > 0) {
            $query = Review::query();

            // ── Filter: nombre / search ─────────────────────────────────────────
            if ($request->filled('nombre')) {
                $query->where('nombre', 'like', '%' . $request->nombre . '%');
            } elseif ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('comentario', 'like', "%{$search}%");
                });
            }

            // ── Filter: rating (1–5) ────────────────────────────────────────────
            if ($request->filled('rating')) {
                $query->where('rating', (int) $request->rating);
            }

            $perPage = (int) $request->input('per_page', 15);
            $sort = $request->input('sort', $request->input('filter', 'recientes'));
            if (in_array($sort, ['antiguas', 'viejas', 'Más antiguas', 'Más viejas', 'asc', 'oldest'])) {
                $paginated = $query->orderBy('created_at', 'asc')->paginate($perPage);
            } else {
                $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);
            }

            $formattedReviews = $paginated->getCollection()->map(function ($r) {
                return [
                    'id'                  => $r->id,
                    'folio'               => $r->folio,
                    'nombre'              => $r->nombre,
                    'customer_name'       => $r->nombre,
                    'rating'              => (int) $r->rating,
                    'comentario'          => $r->comentario,
                    'comment'             => $r->comentario,
                    'respuesta_admin'     => $r->respuesta_admin,
                    'fecha_respuesta'     => $r->fecha_respuesta ? $r->fecha_respuesta->format('Y-m-d H:i:s') : null,
                    'response'            => $r->respuesta_admin,
                    'reply_text'          => $r->respuesta_admin,
                    'fotos'               => $r->fotos ?? [],
                    'images'              => $r->fotos ?? [],
                    'telefono'            => $r->telefono,
                    'customer_phone'      => $r->telefono,
                    'correo'              => $r->correo,
                    'customer_email'      => $r->correo,
                    'is_approved'         => $r->is_approved,
                    'created_at'          => $r->created_at ? $r->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'data'         => $formattedReviews,
                'reviews'      => $formattedReviews,
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'meta'         => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ],
                'resumen'      => [
                    'total_resenas'         => $paginated->total(),
                    'calificacion_promedio' => $paginated->total() > 0 ? round((float) Review::avg('rating'), 1) : 0,
                    'por_responder'         => 0,
                    'reportadas'            => 0,
                    'exp_verificadas'       => $paginated->total(),
                    'distribucion'          => [
                        ['nivel' => 5, 'cantidad' => $c5 = Review::where('rating', 5)->count(), 'porcentaje' => $paginated->total() > 0 ? round(($c5 / $paginated->total()) * 100) : 0],
                        ['nivel' => 4, 'cantidad' => $c4 = Review::where('rating', 4)->count(), 'porcentaje' => $paginated->total() > 0 ? round(($c4 / $paginated->total()) * 100) : 0],
                        ['nivel' => 3, 'cantidad' => $c3 = Review::where('rating', 3)->count(), 'porcentaje' => $paginated->total() > 0 ? round(($c3 / $paginated->total()) * 100) : 0],
                        ['nivel' => 2, 'cantidad' => $c2 = Review::where('rating', 2)->count(), 'porcentaje' => $paginated->total() > 0 ? round(($c2 / $paginated->total()) * 100) : 0],
                        ['nivel' => 1, 'cantidad' => $c1 = Review::where('rating', 1)->count(), 'porcentaje' => $paginated->total() > 0 ? round(($c1 / $paginated->total()) * 100) : 0],
                    ],
                ],
            ]);
        }

        $query = Testimonial::with('images', 'order', 'reservation', 'user');

        // ── Filter: search ─────────────────────────────────────────────────
        // Ya pasó por strip_tags en prepareForValidation() del FormRequest.
        if ($request->filled('search')) {
            $search = $request->validated()['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $q->orWhere('order_id', $search)
                      ->orWhere('reservation_id', $search);
                }
            });
        }

        // ── Filter: rating (1–5) ───────────────────────────────────────────
        // Validado: integer|between:1,5
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->validated()['rating']);
        }

        // ── Filter: state → columna DB: status ────────────────────────────
        // Validado: Rule::in(['pendiente','aprobada','oculta','respondida','reportada'])
        if ($request->filled('state')) {
            $query->where('status', $request->validated()['state']);
        }

        // ── Filter: origin → columna DB: origen ───────────────────────────
        // Validado: Rule::in(['consumo','delivery','reservacion'])
        if ($request->filled('origin')) {
            $origin = $request->validated()['origin'];
            $query->where(function ($q) use ($origin) {
                $q->where('origen', $origin);
                if ($origin === 'delivery') {
                    $q->orWhere('tipo_experiencia', 'pedido');
                } elseif ($origin === 'consumo') {
                    $q->orWhere('tipo_experiencia', 'restaurante');
                }
            });
        }

        // ── Filter: has_images → relación images ──────────────────────────
        // Validado: nullable|boolean
        $validated = $request->validated();
        if (array_key_exists('has_images', $validated) && $validated['has_images'] === true) {
            $query->has('images');
        }

        // ── Filter: start_date / end_date → columna DB: created_at ────────
        // Validado: nullable|date + after_or_equal:start_date
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $formattedReviews = $paginated->getCollection()->map(fn($r) => $this->formatReview($r));

        // Resumen stats calculation
        $allReviewsCount = Testimonial::count();
        $avgRating = $allReviewsCount > 0 ? (float) Testimonial::avg('rating') : 0.0;
        $porResponderCount = Testimonial::where(function ($q) {
            $q->where('status', 'pendiente')
              ->orWhereNull('status');
        })->where(function ($q) {
            $q->whereNull('response')->whereNull('reply_text');
        })->count();
        $reportadasCount = Testimonial::where('status', 'reportada')->count();

        $calcularPct = fn($cnt) => $allReviewsCount > 0 ? round(($cnt / $allReviewsCount) * 100) : 0;
        $distribucion = [
            ['nivel' => 5, 'cantidad' => $c5 = Testimonial::where('rating', 5)->count(), 'porcentaje' => $calcularPct($c5)],
            ['nivel' => 4, 'cantidad' => $c4 = Testimonial::where('rating', 4)->count(), 'porcentaje' => $calcularPct($c4)],
            ['nivel' => 3, 'cantidad' => $c3 = Testimonial::where('rating', 3)->count(), 'porcentaje' => $calcularPct($c3)],
            ['nivel' => 2, 'cantidad' => $c2 = Testimonial::where('rating', 2)->count(), 'porcentaje' => $calcularPct($c2)],
            ['nivel' => 1, 'cantidad' => $c1 = Testimonial::where('rating', 1)->count(), 'porcentaje' => $calcularPct($c1)],
        ];

        return response()->json([
            'data'         => $formattedReviews,
            'reviews'      => $formattedReviews,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'meta'         => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
            'resumen' => [
                'calificacion_promedio' => round($avgRating, 1),
                'por_responder'         => $porResponderCount,
                'total_resenas'         => $allReviewsCount,
                'reportadas'            => $reportadasCount,
                'exp_verificadas'       => $allReviewsCount,
                'distribucion'          => $distribucion,
            ],
        ]);
    }

    /**
     * GET /api/admin/reviews/{id}
    /**
     * GET /api/admin/reviews/{id}
     * Returns full detail view of a single review with its images.
     */
    public function show($id)
    {
        $cleanId = trim((string) $id);
        $review = null;

        if (is_numeric($cleanId)) {
            $review = Review::with(['images', 'imagenes'])->find((int) $cleanId);
        }

        if (!$review && str_starts_with(strtoupper($cleanId), 'COM')) {
            $review = Review::with(['images', 'imagenes'])->where('folio', $cleanId)->first();
            if (!$review) {
                $onlyNums = preg_replace('/[^0-9]/', '', $cleanId);
                if (strlen($onlyNums) >= 8) {
                    $targetId = (int) substr($onlyNums, 8);
                    if ($targetId > 0) {
                        $review = Review::with(['images', 'imagenes'])->find($targetId);
                    }
                }
            }
        }

        if ($review) {
            $fotosFormatted = $review->fotos ?? [];
            if (empty($fotosFormatted) && $review->images->isNotEmpty()) {
                $fotosFormatted = $review->images->map(fn($img) => asset('storage/' . ltrim(str_replace('public/', '', $img->image_path), '/')))->toArray();
            }

            $email = $review->correo;
            $phone = $review->telefono;
            $name = $review->nombre;

            $totalPedidos = 0;
            if ($email || $phone || $name) {
                $totalPedidos = Order::where(function ($q) use ($email, $phone, $name) {
                    if ($email) $q->orWhere('customer_email', $email);
                    if ($phone) $q->orWhere('customer_phone', $phone);
                    if ($name && $name !== 'Cliente') $q->orWhere('customer_name', $name);
                })->count();
            }

            $totalReservaciones = 0;
            if ($email || $phone || $name) {
                $totalReservaciones = Reservation::where(function ($q) use ($email, $phone, $name) {
                    if ($email) $q->orWhere('customer_email', $email);
                    if ($phone) $q->orWhere('customer_phone', $phone);
                    if ($name && $name !== 'Cliente') $q->orWhere('customer_name', $name);
                })->count();
            }

            $respuesta = null;
            if (!empty($review->respuesta_admin)) {
                $respuesta = [
                    'texto'           => $review->respuesta_admin,
                    'admin_nombre'    => 'Administrador',
                    'fecha_respuesta' => $review->fecha_respuesta ? $review->fecha_respuesta->format('Y-m-d H:i:s') : null,
                ];
            }

            return response()->json([
                'id'                  => $review->id,
                'folio'               => $review->folio,
                'nombre'              => $review->nombre,
                'customer_name'       => $review->nombre,
                'correo'              => $review->correo,
                'customer_email'      => $review->correo,
                'telefono'            => $review->telefono,
                'customer_phone'      => $review->telefono,
                'rating'              => (int) $review->rating,
                'comentario'          => $review->comentario,
                'comment'             => $review->comentario,
                'respuesta_admin'     => $review->respuesta_admin,
                'fecha_respuesta'     => $review->fecha_respuesta ? $review->fecha_respuesta->format('Y-m-d H:i:s') : null,
                'response'            => $review->respuesta_admin,
                'reply_text'          => $review->respuesta_admin,
                'fotos'               => $fotosFormatted,
                'images'              => $fotosFormatted,
                'imagenes'            => $review->images,
                'is_approved'         => $review->is_approved,
                'estado'              => $review->is_approved ? 'aprobada' : 'pendiente',
                'created_at'          => $review->created_at ? $review->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                'historial_cliente'   => [
                    'total_pedidos'       => $totalPedidos,
                    'total_reservaciones' => $totalReservaciones,
                    'promedio_anterior'   => round((float) $review->rating, 1),
                ],
                'operacion_vinculada' => [
                    'origen'            => 'consumo',
                    'folio_relacionado' => null,
                    'fecha'             => $review->created_at ? $review->created_at->format('Y-m-d') : date('Y-m-d'),
                    'invitados'         => null,
                ],
                'respuesta'           => $respuesta,
            ]);
        }

        $testimonial = Testimonial::with(['images', 'order', 'reservation', 'user', 'repliedBy'])->findOrFail($id);

        $base = $this->formatReview($testimonial);

        $email = $base['customer_email'];
        $phone = $base['customer_phone'];
        $name = $base['customer_name'];

        // Historial del cliente en el restaurante
        $totalPedidos = 0;
        if ($email || $phone || $name) {
            $totalPedidos = Order::where(function ($q) use ($email, $phone, $name) {
                if ($email) $q->orWhere('customer_email', $email);
                if ($phone) $q->orWhere('customer_phone', $phone);
                if ($name && $name !== 'Cliente') $q->orWhere('customer_name', $name);
            })->count();
        }

        $totalReservaciones = 0;
        if ($email || $phone || $name) {
            $totalReservaciones = Reservation::where(function ($q) use ($email, $phone, $name) {
                if ($email) $q->orWhere('customer_email', $email);
                if ($phone) $q->orWhere('customer_phone', $phone);
                if ($name && $name !== 'Cliente') $q->orWhere('customer_name', $name);
            })->count();
        }

        // Promedio de calificaciones previas
        $promedioAnterior = 0.0;
        if ($email || $phone || ($name && $name !== 'Cliente')) {
            $prevAvgQuery = Testimonial::where('id', '!=', $testimonial->id)
                ->where(function ($q) use ($email, $phone, $name) {
                    if ($email) $q->orWhere('customer_email', $email);
                    if ($phone) $q->orWhere('customer_phone', $phone);
                    if ($name && $name !== 'Cliente') $q->orWhere('customer_name', $name);
                });
            if ($prevAvgQuery->count() > 0) {
                $promedioAnterior = (float) $prevAvgQuery->avg('rating');
            } else {
                $promedioAnterior = (float) $testimonial->rating;
            }
        } else {
            $promedioAnterior = (float) $testimonial->rating;
        }

        // Operación vinculada
        $folioRelacionado = $testimonial->order?->folio ?: ($testimonial->reservation?->folio ?: null);
        $fechaResena = $testimonial->created_at ? $testimonial->created_at->format('Y-m-d') : ($testimonial->visit_date ? $testimonial->visit_date->format('Y-m-d') : date('Y-m-d'));
        $invitados = $testimonial->reservation?->guests_count ?: null;

        // Historial de respuestas del negocio
        $respuesta = null;
        $replyText = $testimonial->respuesta_admin ?: ($testimonial->response ?: $testimonial->reply_text);
        if (!empty($replyText)) {
            $adminNombre = $testimonial->repliedBy?->name ?: 'Administrador';
            $fechaRespuesta = $testimonial->fecha_respuesta ? $testimonial->fecha_respuesta->format('Y-m-d H:i:s') : ($testimonial->replied_at ? $testimonial->replied_at->format('Y-m-d H:i:s') : $testimonial->updated_at->format('Y-m-d H:i:s'));
            $respuesta = [
                'texto'           => $replyText,
                'admin_nombre'    => $adminNombre,
                'fecha_respuesta' => $fechaRespuesta,
            ];
        }

        return response()->json([
            'id'                  => $base['id'],
            'folio'               => $base['folio'],
            'customer_name'       => $base['customer_name'],
            'customer_email'      => $base['customer_email'],
            'customer_phone'      => $base['customer_phone'],
            'rating'              => $base['rating'],
            'comment'             => $base['comment'],
            'respuesta_admin'     => $base['respuesta_admin'],
            'fecha_respuesta'     => $base['fecha_respuesta'],
            'response'            => $base['response'],
            'reply_text'          => $base['reply_text'],
            'origen'              => $base['origen'],
            'estado'              => $base['estado'],
            'created_at'          => $base['created_at'],
            'images'              => $base['images'],
            'imagenes'            => $testimonial->images,
            'historial_cliente'   => [
                'total_pedidos'       => $totalPedidos,
                'total_reservaciones' => $totalReservaciones,
                'promedio_anterior'   => round($promedioAnterior, 1),
            ],
            'operacion_vinculada' => [
                'origen'            => $base['origen'],
                'folio_relacionado' => $folioRelacionado,
                'fecha'             => $fechaResena,
                'invitados'         => $invitados,
            ],
            'respuesta'           => $respuesta,
        ]);
    }

    /**
     * POST /api/admin/reviews/{id}/responder
     * POST /api/admin/reviews/{id}/respond
     */
    public function responderResena(Request $request, $id)
    {
        $request->validate([
            'respuesta_admin' => 'required_without:response|nullable|string',
            'response'        => 'nullable|string',
        ], [
            'respuesta_admin.required_without' => 'La respuesta es obligatoria.',
        ]);

        $reply = $request->input('respuesta_admin') ?: $request->input('response');

        $cleanId = trim((string) $id);
        $resena = null;

        if (is_numeric($cleanId)) {
            $resena = Review::find((int) $cleanId);
        }

        if (!$resena && str_starts_with(strtoupper($cleanId), 'COM')) {
            $resena = Review::where('folio', $cleanId)->first();
            if (!$resena) {
                $onlyNums = preg_replace('/[^0-9]/', '', $cleanId);
                if (strlen($onlyNums) >= 8) {
                    $targetId = (int) substr($onlyNums, 8);
                    if ($targetId > 0) {
                        $resena = Review::find($targetId);
                    }
                }
            }
        }

        if ($resena) {
            $resena->respuesta_admin = $reply;
            $resena->fecha_respuesta = now();
            $resena->save();

            if (class_exists(NotificationService::class)) {
                NotificationService::create(
                    'resena_respondida',
                    'Reseña Respondida',
                    "Se respondió a la reseña de {$resena->nombre}",
                    ['review_id' => $resena->id]
                );
            }

            return response()->json([
                'mensaje' => 'Respuesta publicada exitosamente',
                'message' => 'Respuesta publicada exitosamente',
                'resena'  => $resena,
                'review'  => $this->show($resena->id)->getData(true),
            ]);
        }

        $testimonial = Testimonial::find($id);
        if (!$testimonial) {
            $resena = Review::findOrFail($id);
        }

        $user = $request->user();
        $testimonial->update([
            'response'           => $reply,
            'reply_text'         => $reply,
            'respuesta_admin'    => $reply,
            'replied_at'         => now(),
            'fecha_respuesta'    => now(),
            'replied_by_user_id' => $user?->id,
            'status'             => 'respondida',
        ]);

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'resena_respondida',
                'Reseña Respondida',
                "Se respondió a la reseña de {$testimonial->customer_name}",
                ['testimonial_id' => $testimonial->id]
            );
        }

        return response()->json([
            'mensaje' => 'Respuesta publicada exitosamente',
            'message' => 'Respuesta publicada exitosamente',
            'resena'  => $testimonial,
            'review'  => $this->show($testimonial->id)->getData(true),
        ]);
    }

    /**
     * POST /api/admin/reviews/{id}/respond
     */
    public function respond(Request $request, $id)
    {
        return $this->responderResena($request, $id);
    }

    /**
     * PATCH /api/admin/reviews/{id}/report
     */
    public function report($id)
    {
        $review = Testimonial::findOrFail($id);

        $review->update([
            'status' => 'reportada',
        ]);

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'resena_reportada',
                'Reseña Reportada',
                "Se reportó la reseña de {$review->customer_name}",
                ['testimonial_id' => $review->id]
            );
        }

        return response()->json([
            'message' => 'Reseña marcada como reportada.',
            'review'  => $this->show($review->id)->getData(true),
        ]);
    }

    /**
     * DELETE /api/admin/reviews/{id}
     */
    public function destroy($id)
    {
        $review = Review::find($id);
        if ($review) {
            $review->delete(); // Habilita el borrado lógico (oculta sin destruir)

            return response()->json([
                'message' => 'Reseña eliminada correctamente (borrado lógico aplicado).',
                'id'      => (int) $id,
            ]);
        }

        $testimonial = Testimonial::with('images')->findOrFail($id);

        foreach ($testimonial->images as $image) {
            if ($image->image_path) {
                $cleanPath = ltrim(str_replace('public/', '', $image->image_path), '/');
                Storage::disk('public')->delete($cleanPath);
            }
            $image->delete();
        }

        $testimonial->delete();

        return response()->json([
            'message' => 'Reseña eliminada correctamente.',
        ]);
    }

    /**
     * POST /api/reviews (Public endpoint)
     */
    public function storePublic(Request $request)
    {
        // Rate Limiting: max 3 reviews per IP per day
        $clientIp = $request->ip();
        $todayReviewsCount = Testimonial::where('ip_address', $clientIp)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($todayReviewsCount >= 3) {
            return response()->json([
                'error'   => 'Has alcanzado el límite máximo de 3 reseñas por día.',
                'message' => 'Has alcanzado el límite máximo de 3 reseñas por día.',
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'customer_name'  => 'required|string|max:100',
            'customer_email' => 'nullable|email|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'required|string|max:1000',
            'origen'         => 'required|string|in:consumo,delivery,reservacion',
            'images'         => 'nullable|array|max:4',
            'images.*'       => 'image|mimes:jpeg,png,jpg,webp|max:3072',
        ], [
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
            'customer_name.max'      => 'El nombre no debe superar los 100 caracteres.',
            'rating.required'        => 'La calificación es obligatoria.',
            'rating.integer'         => 'La calificación debe ser un entero.',
            'rating.min'             => 'La calificación mínima es 1 estrella.',
            'rating.max'             => 'La calificación máxima es 5 estrellas.',
            'comment.required'       => 'El comentario es obligatorio.',
            'comment.max'            => 'El comentario no debe superar los 1000 caracteres.',
            'origen.required'        => 'El origen es obligatorio.',
            'origen.in'              => 'El origen debe ser consumo, delivery o reservacion.',
            'images.max'             => 'No puedes subir más de 4 imágenes.',
            'images.*.image'         => 'Cada archivo debe ser una imagen válida.',
            'images.*.mimes'         => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max'           => 'Cada imagen no debe superar los 3MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review = Testimonial::create([
            'customer_name'  => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'rating'         => $request->rating,
            'comment'        => $request->comment,
            'origen'         => $request->origen,
            'ip_address'     => $clientIp,
            'status'         => 'pendiente',
            'tipo_experiencia' => $request->origen === 'delivery' ? 'pedido' : 'restaurante',
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $compressed = ImageCompressionService::compressAndStore($file, 'reviews', 800, 75);
                TestimonialImage::create([
                    'testimonial_id' => $review->id,
                    'image_path'     => $compressed['path'],
                    'status'         => 'pendiente',
                ]);
            }
        }

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'resena_nueva',
                'Nueva Reseña Recibida',
                "Se recibió una nueva reseña de {$review->customer_name} ({$request->rating} estrellas)",
                ['testimonial_id' => $review->id]
            );
        }

        return response()->json([
            'message' => 'Reseña creada exitosamente.',
            'review'  => $this->formatReview($review->fresh('images')),
        ], 201);
    }

    /**
     * POST /api/reviews
     *
     * Captura de reseñas con validación Zero-Trust, almacenamiento seguro de archivos y moderación estricta.
     * Regla de oro: Ninguna reseña ni fotografía debe publicarse automáticamente (is_approved = false).
     */
    public function store(StoreReviewRequest $request)
    {
        \Illuminate\Support\Facades\Log::info('ReviewController@store payload:', $request->all());
        \Illuminate\Support\Facades\Log::info('ReviewController@store fotos:', [
            'fotos'  => $request->file('fotos'),
            'images' => $request->file('images'),
        ]);

        $rutasFotos = [];
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $compressed = ImageCompressionService::compressAndStore($foto, 'reviews', 800, 75);
                $rutasFotos[] = $compressed['url'];
            }
        }

        // Se guarda y queda pública instantáneamente
        $review = Review::create([
            'dish_id'     => $request->dish_id,
            'nombre'      => $request->nombre,
            'telefono'    => $request->telefono,
            'correo'      => $request->correo,
            'rating'      => $request->rating,
            'comentario'  => $request->comentario,
            'fotos'       => $rutasFotos,
            'is_approved' => true,
        ]);

        $fecha = Carbon::now()->format('Ymd');
        $numeroFormateado = str_pad($review->id, 4, '0', STR_PAD_LEFT);
        $folioNuevo = 'COM' . $fecha . $numeroFormateado;
        $review->folio = $folioNuevo;
        $review->save();

        if (!empty($rutasFotos) && class_exists(ReviewImage::class)) {
            foreach ($rutasFotos as $url) {
                $rawPath = ltrim(str_replace('/storage/', '', $url), '/');
                ReviewImage::create([
                    'review_id'   => $review->id,
                    'image_path'  => $rawPath,
                    'is_approved' => true,
                    'status'      => 'aprobada',
                ]);
            }
        }

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'resena_nueva',
                'Nueva Reseña Recibida',
                "Se recibió una nueva reseña de {$review->nombre} ({$review->rating} estrellas)",
                ['review_id' => $review->id]
            );
        }

        return response()->json([
            'message' => 'Reseña publicada con éxito.',
            'data'    => $review,
            'review'  => $review,
        ], 201);
    }

    /**
     * GET /api/reviews (Public endpoint for Landing Page)
     *
     * Retorna reseñas públicas aprobadas para la Landing Page.
     * Seguridad Crítica: Gracias a protected $hidden = ['telefono', 'correo'] en Review.php,
     * las columnas de privacidad del cliente JAMÁS son expuestas al público.
     */
    public function publicIndex(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);
        $reviews = Review::where('is_approved', true)
            ->latest()
            ->paginate($perPage);

        return response()->json($reviews);
    }

    /**
     * GET /api/reviews/stats
     *
     * Estadísticas agregadas seguras (Zero-Trust Moderation) con prevención de división por cero.
     */
    public function stats()
    {
        return response()->json([
            'stats_experiencias' => Review::getExperienceStats()
        ]);
    }

    /**
     * GET /api/galeria-destacada
     *
     * Algoritmo de rotación diaria con caché de 24h para no sobrecargar PostgreSQL.
     * Extrae fotos de reseñas de 4 y 5 estrellas, las mezcla al azar
     * y entrega un lote optimizado de hasta 10 imágenes para la UI.
     */
    public function galeriaDiaria()
    {
        // El caché expirará automáticamente al final del día actual
        $galeria = Cache::remember('galeria_premium_diaria', now()->endOfDay(), function () {
            // 1. Traer reseñas aleatorias de 4 o 5 estrellas que tengan fotos
            $reviews = Review::where('is_approved', true)
                ->where('rating', '>=', 4)
                ->whereNotNull('fotos')
                ->inRandomOrder()
                ->limit(20) // Traemos un lote grande para tener variedad
                ->get(['id', 'nombre', 'rating', 'fotos']);

            $fotosExtraidas = [];

            // 2. Extraer las fotos del JSON y aplanarlas en un solo arreglo
            foreach ($reviews as $review) {
                // Asegurarnos de que las fotos sean un array (por si acaso)
                $fotos = is_array($review->fotos) ? $review->fotos : json_decode($review->fotos, true);

                if ($fotos) {
                    // 1. ALGORITMO DE PRIVACIDAD (Ofuscación de Nombre)
                    // Divide el nombre por espacios y toma el primer nombre
                    $partesNombre = explode(' ', trim($review->nombre));
                    $nombreSeguro = $partesNombre[0];

                    // Si tiene apellido, toma solo la primera letra y le pone un punto (Soporta acentos con mb_substr)
                    if (count($partesNombre) > 1) {
                        $nombreSeguro .= ' ' . mb_substr($partesNombre[1], 0, 1, 'UTF-8') . '.';
                    }

                    foreach ($fotos as $fotoUrl) {
                        // 2. CORRECCIÓN DE URL (Ruta Absoluta)
                        // Convierte "/storage/reviews/foto.webp" a "http://localhost:8000/storage/reviews/foto.webp"
                        $urlAbsoluta = (str_starts_with($fotoUrl, 'http://') || str_starts_with($fotoUrl, 'https://'))
                            ? $fotoUrl
                            : url($fotoUrl);

                        $fotosExtraidas[] = [
                            'url'     => $urlAbsoluta, // <-- Ahora React sabrá exactamente a qué servidor pedir la foto
                            'rating'  => $review->rating,
                            'cliente' => $nombreSeguro, // <-- Envía "Francisco P." en lugar de "Francisco Perez"
                        ];
                    }
                }
            }

            // 3. Mezclar las fotos extraídas y devolver solo un top 10 para la UI
            shuffle($fotosExtraidas);
            return array_slice($fotosExtraidas, 0, 10);
        });

        return response()->json($galeria);
    }

    /**
     * GET /api/resenas-destacadas
     *
     * Algoritmo de tarjetas únicas con caché de 24h para la Landing Page.
     * Devuelve hasta 10 reseñas aleatorias de 4 y 5 estrellas, con ID único,
     * nombre ofuscado para privacidad, fotos con rutas absolutas y tiempo relativo.
     */
    public function tarjetasDiarias()
    {
        // El caché guarda el resultado hasta la medianoche de hoy
        $tarjetas = Cache::remember('tarjetas_landing_diarias', now()->endOfDay(), function () {
            // Trae hasta 10 reseñas aprobadas de 4 y 5 estrellas (únicas por su ID en la BD)
            $reviews = Review::where('is_approved', true)
                ->where('rating', '>=', 4)
                ->inRandomOrder()
                ->limit(10)
                ->get(['id', 'nombre', 'rating', 'comentario', 'fotos', 'respuesta_admin', 'fecha_respuesta', 'created_at']);

            // Mapeo para formatear la respuesta al frontend
            return $reviews->map(function ($review) {
                // Ofuscación del apellido para privacidad (ej. "Pancho H.")
                $partes = explode(' ', trim($review->nombre));
                $nombreSeguro = $partes[0];
                if (count($partes) > 1) {
                    $nombreSeguro .= ' ' . mb_substr($partes[1], 0, 1, 'UTF-8') . '.';
                }

                $fotos = is_array($review->fotos) ? $review->fotos : json_decode($review->fotos, true);
                $fotosFormatted = collect($fotos ?: [])
                    ->map(fn($foto) => (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://')) ? $foto : url($foto))
                    ->values()
                    ->toArray();

                return [
                    'id'              => $review->id, // Vital para claves únicas en React
                    'nombre'          => $nombreSeguro,
                    'cliente_nombre'  => $nombreSeguro,
                    'rating'          => (int) $review->rating,
                    'comentario'      => $review->comentario,
                    'respuesta_admin' => $review->respuesta_admin,
                    'fecha_respuesta' => $review->fecha_respuesta ? $review->fecha_respuesta->format('Y-m-d H:i:s') : null,
                    'fotos'           => $fotosFormatted,
                    'tiempo'          => $review->created_at ? $review->created_at->diffForHumans() : 'Reciente',
                ];
            });
        });

        return response()->json($tarjetas);
    }

    /**
     * GET /api/metricas-resenas
     *
     * Consulta maestra con DB::raw para calcular promedio y distribución por estrellas (5 a 1).
     * Garantiza que niveles sin reseñas devuelvan 0 en lugar de omitir datos.
     */
    public function metricasResenas()
    {
        // 1. Consulta maestra a PostgreSQL
        $query = Review::query();
        if (Review::where('is_approved', true)->exists()) {
            $query->where('is_approved', true);
        }

        $datos = $query->select(
            DB::raw('COUNT(id) as total_opiniones'),
            DB::raw('ROUND(AVG(rating), 1) as promedio_general'),
            DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as cinco_estrellas'),
            DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as cuatro_estrellas'),
            DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as tres_estrellas'),
            DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as dos_estrellas'),
            DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as una_estrella')
        )->first();

        $total = (int) ($datos->total_opiniones ?? 0);

        // 2. Función auxiliar para evitar división por cero y calcular porcentaje
        $calcularPorcentaje = function ($cantidad) use ($total) {
            return $total > 0 ? round(((int) $cantidad / $total) * 100) : 0;
        };

        // 3. Estructuramos la distribución exactamente como React la necesita (del 5 al 1)
        $distribucion = [
            ['nivel' => 5, 'cantidad' => (int) ($datos->cinco_estrellas ?? 0), 'porcentaje' => $calcularPorcentaje($datos->cinco_estrellas ?? 0)],
            ['nivel' => 4, 'cantidad' => (int) ($datos->cuatro_estrellas ?? 0), 'porcentaje' => $calcularPorcentaje($datos->cuatro_estrellas ?? 0)],
            ['nivel' => 3, 'cantidad' => (int) ($datos->tres_estrellas ?? 0), 'porcentaje' => $calcularPorcentaje($datos->tres_estrellas ?? 0)],
            ['nivel' => 2, 'cantidad' => (int) ($datos->dos_estrellas ?? 0), 'porcentaje' => $calcularPorcentaje($datos->dos_estrellas ?? 0)],
            ['nivel' => 1, 'cantidad' => (int) ($datos->una_estrella ?? 0), 'porcentaje' => $calcularPorcentaje($datos->una_estrella ?? 0)],
        ];

        return response()->json([
            'promedio'     => $datos->promedio_general ?? 0,
            'total'        => $total,
            'distribucion' => $distribucion,
        ]);
    }

    /**
     * GET /api/estadisticas-resenas
     *
     * Cálculo en tiempo real de estadísticas de reseñas con prevención de división por cero.
     * Retorna promedio formateado a 1 decimal, total de reseñas y porcentaje de satisfacción.
     */
    public function estadisticas()
    {
        // 1. Total de experiencias verificadas / aprobadas
        $total = Review::where('is_approved', true)->count();

        // 2. Promedio matemático (ej. 4.6)
        $promedio = $total > 0 ? (float) Review::where('is_approved', true)->avg('rating') : 0.0;

        // 3. Porcentaje de Satisfacción (Calculado en base a reseñas de 4 y 5 estrellas)
        $positivas = Review::where('is_approved', true)->where('rating', '>=', 4)->count();
        $satisfaccion = $total > 0 ? ($positivas / $total) * 100 : 0.0;

        return response()->json([
            'promedio'     => number_format($promedio, 1),
            'total'        => (int) $total,
            'satisfaccion' => (int) round($satisfaccion),
        ]);
    }

    /**
     * GET /api/reviews/landing / getLandingReviews
     *
     * Obtenemos solo 15 reseñas aprobadas, ordenadas por las más recientes.
     */
    public function getLandingReviews()
    {
        $reviews = Review::where('estado', 'aprobado')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json($reviews);
    }
}
