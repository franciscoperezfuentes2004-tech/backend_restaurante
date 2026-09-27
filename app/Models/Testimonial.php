<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Testimonial extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'customer_name', 'customer_email', 'customer_phone', 'rating', 'comment', 
        'status', 'is_approved', 'origen', 'ip_address', 'response',
        'tipo_experiencia', 'insignia', 'platillo_texto', 
        'reply_text', 'replied_at', 'replied_by_user_id', 'hidden_reason', 'useful_count', 
        'visit_date', 'reservation_id', 'order_id',
        'nombre', 'correo', 'telefono', 'comentario', 'fotos'
    ];

    protected $casts = [
        'rating'       => 'integer',
        'is_approved'  => 'boolean',
        'useful_count' => 'integer',
        'visit_date'   => 'date',
        'replied_at'   => 'datetime',
        'fotos'        => 'array',
    ];

    protected static function booted()
    {
        static::saving(function ($testimonial) {
            // Sincronizar is_approved con status
            if ($testimonial->isDirty('is_approved') && $testimonial->is_approved) {
                if (in_array($testimonial->status, ['pendiente', null, ''])) {
                    $testimonial->status = 'aprobada';
                }
            } elseif (in_array($testimonial->status, ['aprobada', 'published', 'respondida'])) {
                $testimonial->is_approved = true;
            } elseif (in_array($testimonial->status, ['pendiente', 'oculta', 'reportada', 'rechazada'])) {
                if (!$testimonial->isDirty('is_approved') || !$testimonial->is_approved) {
                    $testimonial->is_approved = false;
                }
            }
        });
    }

    /**
     * Scope para consultar únicamente reseñas aprobadas / publicadas por un administrador.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Calcula estadísticas de reseñas con prevención de división por cero y moderación Zero-Trust.
     */
    public static function getExperienceStats(): array
    {
        $reviews = static::where('is_approved', true)->get();
        $totalReviews = $reviews->count();

        // Prevención de división por cero
        $averageRating = $totalReviews > 0 ? round((float) $reviews->avg('rating'), 1) : 0;

        // Satisfacción = Porcentaje de reseñas de 4 y 5 estrellas
        $happyReviews = $reviews->whereIn('rating', [4, 5])->count();
        $satisfaction = $totalReviews > 0 ? (int) round(($happyReviews / $totalReviews) * 100) : 0;

        return [
            'total'        => $totalReviews,
            'promedio'     => $averageRating,
            'satisfaccion' => $satisfaction,
        ];
    }

    public function getCustomerNameMaskedAttribute()
    {
        if (empty($this->customer_name)) {
            return null;
        }
        
        $parts = explode(' ', trim($this->customer_name));
        if (count($parts) > 1) {
            $firstName = array_shift($parts);
            $lastName = array_shift($parts);
            return $firstName . ' ' . mb_substr($lastName, 0, 1) . '.';
        }
        
        return $this->customer_name;
    }

    public function images()
    {
        return $this->hasMany(TestimonialImage::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by_user_id');
    }
}
