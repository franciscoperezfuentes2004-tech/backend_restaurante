<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestimonialRequest;
use App\Models\Testimonial;
use App\Models\TestimonialImage;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\Reservation;
use App\Models\Order;
use App\Services\ImageCompressionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TestimonialController extends Controller
{
    private function containsProfanity($text)
    {
        if (empty($text)) return false;
        
        $profanities = [
            'mierda', 'puta', 'puto', 'cabron', 'cabrón', 'pendejo', 'pendeja', 'chinga', 'chingar',
            'verga', 'culo', 'jodido', 'jodida', 'coño', 'cojones', 'polla', 'zorra', 'maricon', 'maricón',
            'estupido', 'estúpido', 'idiota', 'imbecil', 'imbécil', 'bastardo', 'ramera', 'putilla',
            'mamar', 'mamada', 'cagada', 'cagar'
        ];

        $lowerText = mb_strtolower($text, 'UTF-8');
        
        foreach ($profanities as $word) {
            // Check for whole word match to avoid false positives
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lowerText)) {
                return true;
            }
        }
        
        return false;
    }

    public function index(Request $request)
    {
        $query = Testimonial::where('status', 'aprobada')->with(['images' => function($q) {
            $q->where('status', 'aprobada');
        }]);

        if ($request->has('tipo_experiencia')) {
            $query->where('tipo_experiencia', $request->tipo_experiencia);
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('has_images') && filter_var($request->has_images, FILTER_VALIDATE_BOOLEAN)) {
            $query->has('images', '>', 0);
        }

        if ($request->has('filter')) {
            $f = $request->filter;
            if ($f === 'Con fotos' || $f === 'con_fotos') {
                $query->has('images', '>', 0);
            } elseif ($f === '5 estrellas' || $f === '5_estrellas') {
                $query->where('rating', 5);
            }
        }

        $sort = $request->input('sort', $request->input('filter', 'recientes'));
        if (in_array($sort, ['antiguas', 'viejas', 'Más antiguas', 'Más viejas', 'asc', 'oldest'])) {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'calificacion') {
            $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $testimonials = $query->paginate(15);
        $testimonials->getCollection()->transform(function ($testimonial) {
            $testimonial->customer_name_masked = $testimonial->customer_name_masked;
            return $testimonial;
        });

        // Summary stats (Zero-Trust Moderation & Division-by-Zero prevention)
        $stats = Testimonial::getExperienceStats();

        return response()->json([
            'data' => $testimonials->items(),
            'current_page' => $testimonials->currentPage(),
            'last_page' => $testimonials->lastPage(),
            'next_page_url' => $testimonials->nextPageUrl(),
            'links' => [
                'next' => $testimonials->nextPageUrl(),
                'prev' => $testimonials->previousPageUrl(),
            ],
            'meta' => [
                'current_page' => $testimonials->currentPage(),
                'last_page' => $testimonials->lastPage(),
                'per_page' => $testimonials->perPage(),
                'total' => $testimonials->total(),
            ],
            'stats_experiencias' => $stats,
            'summary' => [
                'total_count' => $stats['total'],
                'avg_rating' => $stats['promedio'],
                'satisfaction_percentage' => $stats['satisfaccion']
            ]
        ]);
    }

    public function gallery(Request $request)
    {
        $images = TestimonialImage::where('status', 'aprobada')
            ->whereHas('testimonial', function($q) {
                $q->where('status', 'aprobada');
            })
            ->with(['testimonial' => function($q) {
                $q->with(['images' => function($imgQ) {
                    $imgQ->where('status', 'aprobada');
                }]);
            }])
            ->latest()
            ->paginate(12);

        $images->getCollection()->transform(function ($img) {
            if ($img->testimonial) {
                $img->testimonial->customer_name_masked = $img->testimonial->customer_name_masked;
            }
            return $img;
        });

        return response()->json($images);
    }

    public function useful($id)
    {
        $testimonial = Testimonial::findOrFail($id);
        $testimonial->increment('useful_count');
        return response()->json(['message' => 'Contador útil incrementado']);
    }

    public function store(StoreTestimonialRequest $request)
    {
        Log::info('TestimonialController@store payload:', $request->all());
        Log::info('TestimonialController@store fotos:', [
            'fotos'  => $request->file('fotos'),
            'images' => $request->file('images'),
        ]);

        $folio = $request->folio;
        $reservation = null;
        $order = null;
        $tipoExperiencia = null;
        $insignia = null;
        $visitDate = null;
        $customerName = $request->nombre ?? $request->customer_name ?? 'Cliente';
        $userId = null;

        if (!empty($folio)) {
            $folioLimpio = str_replace('-', '', $folio);
            if (str_starts_with($folio, 'RES-') || str_starts_with($folio, 'RES')) {
                $reservation = Reservation::where('folio', $folio)
                    ->orWhere('folio', $folioLimpio)
                    ->orWhereRaw("REPLACE(folio, '-', '') = ?", [$folioLimpio])
                    ->first();
                if (!$reservation) return response()->json(['error' => 'Folio de reserva no encontrado'], 404);
                if ($reservation->status !== 'completed' && $reservation->status !== 'completada') {
                    return response()->json(['error' => 'La reserva debe estar completada para dejar una reseña'], 400);
                }
                if (Testimonial::where('reservation_id', $reservation->id)->exists()) {
                    return response()->json(['error' => 'Ya existe una reseña para esta reserva'], 400);
                }
                $tipoExperiencia = 'restaurante';
                $insignia = 'reserva_verificada';
                $visitDate = $reservation->reservation_date ?? $reservation->date;
                $customerName = $reservation->customer_name ?? $customerName;
                $userId = $reservation->user_id;
            } elseif (str_starts_with($folio, 'PED-') || str_starts_with($folio, 'PED')) {
                $order = Order::where('folio', $folio)
                    ->orWhere('folio', $folioLimpio)
                    ->orWhereRaw("REPLACE(folio, '-', '') = ?", [$folioLimpio])
                    ->first();
                if (!$order) return response()->json(['error' => 'Folio de pedido no encontrado'], 404);
                if ($order->status !== 'completed' && $order->status !== 'completado' && $order->status !== 'delivered' && $order->status !== 'entregado') {
                    return response()->json(['error' => 'El pedido debe estar completado para dejar una reseña'], 400);
                }
                if (Testimonial::where('order_id', $order->id)->exists()) {
                    return response()->json(['error' => 'Ya existe una reseña para este pedido'], 400);
                }
                $tipoExperiencia = 'pedido';
                $insignia = $order->modality === 'delivery' ? 'pedido_verificado' : 'pedido_llevar';
                $visitDate = $order->created_at->toDateString();
                $customerName = $order->customer_name ?? $customerName;
                $userId = $order->user_id;
            } else {
                return response()->json(['error' => 'Formato de folio inválido. Debe comenzar con RES o PED'], 400);
            }
        }

        $rutasFotos = [];
        $filesToSave = $request->file('fotos') ?? $request->file('images') ?? [];
        if (!is_array($filesToSave) && $filesToSave) {
            $filesToSave = [$filesToSave];
        }

        foreach ($filesToSave as $foto) {
            $compressed = ImageCompressionService::compressAndStore($foto, 'reviews', 800, 75);
            $rutasFotos[] = $compressed['url'];
        }

        // Guardar en tabla reviews (PostgreSQL)
        $review = Review::create([
            'nombre'      => $request->nombre,
            'telefono'    => $request->telefono,
            'correo'      => $request->correo,
            'rating'      => $request->rating,
            'comentario'  => $request->comentario,
            'fotos'       => $rutasFotos,
            'is_approved' => true,
        ]);

        $fecha = \Carbon\Carbon::now()->format('Ymd');
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

        // Sincronizar en tabla testimonials para retrocompatibilidad
        $isProfane = $this->containsProfanity($request->comentario) || $this->containsProfanity($request->platillo_texto);
        $status = $isProfane ? 'pendiente' : 'aprobada';

        $validInsignias = ['reserva_verificada', 'pedido_verificado', 'visita_restaurante', 'pedido_llevar'];
        $assignedInsignia = ($insignia && in_array($insignia, $validInsignias)) ? $insignia : 'visita_restaurante';

        $testimonial = null;
        try {
            $testimonial = Testimonial::create([
                'user_id'          => $userId,
                'customer_name'    => $customerName,
                'customer_email'   => $request->correo,
                'customer_phone'   => $request->telefono,
                'rating'           => $request->rating,
                'comment'          => $request->comentario,
                'status'           => $status,
                'tipo_experiencia' => $tipoExperiencia ?? 'restaurante',
                'insignia'         => $assignedInsignia,
                'platillo_texto'   => $request->platillo_texto,
                'visit_date'       => $visitDate ?? now()->toDateString(),
                'reservation_id'   => $reservation ? $reservation->id : null,
                'order_id'         => $order ? $order->id : null,
            ]);

            if (!empty($rutasFotos)) {
                foreach ($rutasFotos as $url) {
                    $rawPath = ltrim(str_replace('/storage/', '', $url), '/');
                    TestimonialImage::create([
                        'testimonial_id' => $testimonial->id,
                        'image_path'     => $rawPath,
                        'status'         => 'aprobada',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error sincronizando modelo Testimonial (legacy): ' . $e->getMessage());
        }

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'resena_nueva',
                'Nueva Reseña Recibida',
                "Se recibió una nueva reseña de {$review->nombre} ({$review->rating} estrellas)",
                [
                    'review_id'      => $review->id,
                    'testimonial_id' => $testimonial?->id,
                ]
            );
        }

        return response()->json([
            'message'     => 'Reseña creada exitosamente',
            'data'        => $review,
            'review'      => $review,
            'testimonial' => $testimonial,
            'status'      => $status,
        ], 201);
    }

    public function adminIndex(Request $request)
    {
        $testimonials = Testimonial::with('images')->latest()->paginate(15);
        
        $stats = [
            'pending_reviews' => Testimonial::where('status', 'pendiente')->count(),
            'pending_photos' => TestimonialImage::where('status', 'pendiente')->count(),
            'pending_replies' => Testimonial::where('status', 'aprobada')->whereNull('reply_text')->count(),
            'avg_rating' => round(Testimonial::where('status', 'aprobada')->avg('rating') ?? 0, 1),
            'total_verified' => Testimonial::where('status', 'aprobada')->count(),
            'star_distribution' => [
                5 => Testimonial::where('status', 'aprobada')->where('rating', 5)->count(),
                4 => Testimonial::where('status', 'aprobada')->where('rating', 4)->count(),
                3 => Testimonial::where('status', 'aprobada')->where('rating', 3)->count(),
                2 => Testimonial::where('status', 'aprobada')->where('rating', 2)->count(),
                1 => Testimonial::where('status', 'aprobada')->where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'data' => $testimonials->items(),
            'meta' => [
                'current_page' => $testimonials->currentPage(),
                'last_page' => $testimonials->lastPage(),
                'per_page' => $testimonials->perPage(),
                'total' => $testimonials->total(),
            ],
            'stats' => $stats
        ]);
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'reply_text' => 'required|string'
        ]);

        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update([
            'reply_text' => $request->reply_text,
            'replied_at' => now()
        ]);

        NotificationService::create(
            'resena_respondida',
            'Reseña Respondida',
            "Se respondió a la reseña de {$testimonial->customer_name}",
            ['testimonial_id' => $testimonial->id]
        );

        return response()->json(['message' => 'Respuesta guardada', 'data' => $testimonial]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:aprobada,oculta',
            'hidden_reason' => 'required_if:status,oculta|string|nullable'
        ]);

        $user = $request->user();
        if ($request->status === 'oculta' && $user && $user->role === 'admin') {
            return response()->json(['error' => 'Solo el Gerente de sucursal o Super Admin puede ocultar reseñas.'], 403);
        }

        $testimonial = Testimonial::findOrFail($id);
        $testimonial->update([
            'status' => $request->status,
            'is_approved' => ($request->status === 'aprobada'),
            'hidden_reason' => $request->status === 'oculta' ? $request->hidden_reason : null
        ]);

        return response()->json(['message' => 'Estado actualizado', 'data' => $testimonial]);
    }

    public function moderateImage(Request $request, $testimonialId, $imageId)
    {
        $request->validate([
            'status' => 'required|in:aprobada,rechazada'
        ]);

        $image = TestimonialImage::where('testimonial_id', $testimonialId)->findOrFail($imageId);
        $image->update(['status' => $request->status]);

        return response()->json(['message' => 'Estado de imagen actualizado', 'data' => $image]);
    }

    /**
     * GET /api/testimonials/stats
     *
     * Estadísticas agregadas seguras (Zero-Trust Moderation) con prevención de división por cero.
     */
    public function stats()
    {
        return response()->json([
            'stats_experiencias' => Testimonial::getExperienceStats()
        ]);
    }
}
