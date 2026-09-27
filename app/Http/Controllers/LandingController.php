<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    /**
     * Get statistics for experiences / reviews (Landing Page).
     */
    public function getStatistics(): JsonResponse
    {
        if (Review::count() > 0) {
            $total = Review::count();
            $promedio = $total > 0 ? round((float) Review::avg('rating'), 1) : 5.0;
            $cincoEstrellas = Review::where('rating', 5)->count();
            $cuatroEstrellas = Review::where('rating', 4)->count();
            $tresEstrellas = Review::where('rating', 3)->count();
            $dosEstrellas = Review::where('rating', 2)->count();
            $unaEstrella = Review::where('rating', 1)->count();
            $recomendacion = $total > 0 ? round((($cincoEstrellas + $cuatroEstrellas) / $total) * 100) : 100;

            return response()->json([
                'total_opiniones'          => $total,
                'total_resenas'            => $total,
                'total_experiences'        => $total,
                'promedio_general'         => $promedio,
                'average_rating'           => $promedio,
                'calificacion_promedio'    => $promedio,
                'porcentaje_recomendacion' => $recomendacion,
                'satisfaction_rate'        => $recomendacion,
                'cinco_estrellas'          => $cincoEstrellas,
                'cuatro_estrellas'         => $cuatroEstrellas,
                'tres_estrellas'           => $tresEstrellas,
                'dos_estrellas'            => $dosEstrellas,
                'una_estrella'             => $unaEstrella,
                'distribucion'             => [
                    ['nivel' => 5, 'estrellas' => 5, 'conteo' => $cincoEstrellas, 'porcentaje' => $total > 0 ? round(($cincoEstrellas / $total) * 100) : 0],
                    ['nivel' => 4, 'estrellas' => 4, 'conteo' => $cuatroEstrellas, 'porcentaje' => $total > 0 ? round(($cuatroEstrellas / $total) * 100) : 0],
                    ['nivel' => 3, 'estrellas' => 3, 'conteo' => $tresEstrellas, 'porcentaje' => $total > 0 ? round(($tresEstrellas / $total) * 100) : 0],
                    ['nivel' => 2, 'estrellas' => 2, 'conteo' => $dosEstrellas, 'porcentaje' => $total > 0 ? round(($dosEstrellas / $total) * 100) : 0],
                    ['nivel' => 1, 'estrellas' => 1, 'conteo' => $unaEstrella, 'porcentaje' => $total > 0 ? round(($unaEstrella / $total) * 100) : 0],
                ],
            ]);
        }

        $totalAprobadas = Testimonial::where('status', 'aprobada')->count();
        $total = $totalAprobadas > 0 ? $totalAprobadas : Testimonial::count();
        $query = $totalAprobadas > 0 ? Testimonial::where('status', 'aprobada') : Testimonial::query();

        $promedio = $total > 0 ? round((float) (clone $query)->avg('rating'), 1) : 5.0;
        $c5 = (clone $query)->where('rating', 5)->count();
        $c4 = (clone $query)->where('rating', 4)->count();
        $c3 = (clone $query)->where('rating', 3)->count();
        $c2 = (clone $query)->where('rating', 2)->count();
        $c1 = (clone $query)->where('rating', 1)->count();
        $recomendacion = $total > 0 ? round((($c5 + $c4) / $total) * 100) : 100;

        return response()->json([
            'total_opiniones'          => $total,
            'total_resenas'            => $total,
            'total_experiences'        => $total,
            'promedio_general'         => $promedio,
            'average_rating'           => $promedio,
            'calificacion_promedio'    => $promedio,
            'porcentaje_recomendacion' => $recomendacion,
            'satisfaction_rate'        => $recomendacion,
            'cinco_estrellas'          => $c5,
            'cuatro_estrellas'         => $c4,
            'tres_estrellas'           => $c3,
            'dos_estrellas'            => $c2,
            'una_estrella'             => $c1,
            'distribucion'             => [
                ['nivel' => 5, 'estrellas' => 5, 'conteo' => $c5, 'porcentaje' => $total > 0 ? round(($c5 / $total) * 100) : 0],
                ['nivel' => 4, 'estrellas' => 4, 'conteo' => $c4, 'porcentaje' => $total > 0 ? round(($c4 / $total) * 100) : 0],
                ['nivel' => 3, 'estrellas' => 3, 'conteo' => $c3, 'porcentaje' => $total > 0 ? round(($c3 / $total) * 100) : 0],
                ['nivel' => 2, 'estrellas' => 2, 'conteo' => $c2, 'porcentaje' => $total > 0 ? round(($c2 / $total) * 100) : 0],
                ['nivel' => 1, 'estrellas' => 1, 'conteo' => $c1, 'porcentaje' => $total > 0 ? round(($c1 / $total) * 100) : 0],
            ],
        ]);
    }

    /**
     * Get reviews for Landing Page (maximum 15 reviews).
     */
    public function getLandingReviews(): JsonResponse
    {
        // Obtenemos solo 15 reseñas aprobadas, ordenadas por las más recientes
        if (Review::count() > 0) {
            $reviews = Review::where(function ($q) {
                $q->where('estado', 'aprobado')
                  ->orWhere('is_approved', true)
                  ->orWhereNull('is_approved');
            })
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

            return response()->json($reviews);
        }

        $reviews = Testimonial::where(function ($q) {
                $q->where('status', 'aprobada')
                  ->orWhere('is_approved', true)
                  ->orWhereNull('status');
            })
            ->with(['images' => function ($q) {
                $q->where('status', 'aprobada')->orWhereNull('status');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json($reviews);
    }

    /**
     * Alias for getLandingReviews.
     */
    public function getReviews(): JsonResponse
    {
        return $this->getLandingReviews();
    }
}
