<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Review extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'reviews';

    protected $fillable = [
        'dish_id',
        'folio',
        'nombre',
        'telefono',
        'correo',
        'rating',
        'comentario',
        'respuesta_admin',
        'fecha_respuesta',
        'fotos',
        'is_approved',
        'estado',
        // Alias de compatibilidad
        'customer_name',
        'customer_phone',
        'customer_email',
        'comment',
        'response',
        'reply_text',
    ];

    /**
     * Oculta los datos personales del cliente (privacidad / fuga de datos en endpoints públicos).
     */
    protected $hidden = [
        'telefono',
        'correo',
    ];

    protected $casts = [
        'fotos'           => 'array',
        'rating'          => 'integer',
        'is_approved'     => 'boolean',
        'fecha_respuesta' => 'datetime',
    ];

    /**
     * Generar folio único para comentarios/reseñas con prefijo COM (COMYYYYMMDDXXXX)
     */
    public static function generarFolioResena($review = null): string
    {
        $id = is_object($review) ? ($review->id ?? 1) : (is_numeric($review) ? (int)$review : 1);
        $date = (is_object($review) && $review->created_at) ? \Carbon\Carbon::parse($review->created_at) : \Carbon\Carbon::now();
        $fecha = $date->format('Ymd');
        $numeroFormateado = str_pad($id, 4, '0', STR_PAD_LEFT);
        return 'COM' . $fecha . $numeroFormateado;
    }

    public function getFolioAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }
        return static::generarFolioResena($this);
    }

    protected static function booted()
    {
        static::saving(function ($review) {
            if ($review->isDirty('is_approved') && !$review->isDirty('estado')) {
                $review->estado = $review->is_approved ? 'aprobado' : 'pendiente';
            } elseif ($review->isDirty('estado') && !$review->isDirty('is_approved')) {
                $review->is_approved = in_array(strtolower((string)$review->estado), ['aprobado', 'aprobada', 'approved', 'publicado']);
            } elseif (empty($review->estado)) {
                $review->estado = ($review->is_approved ?? true) ? 'aprobado' : 'pendiente';
            }
        });

        static::created(function ($review) {
            if (empty($review->folio)) {
                $fecha = ($review->created_at ? \Carbon\Carbon::parse($review->created_at) : \Carbon\Carbon::now())->format('Ymd');
                $numeroFormateado = str_pad($review->id, 4, '0', STR_PAD_LEFT);
                $review->folio = 'COM' . $fecha . $numeroFormateado;
                $review->saveQuietly();
            }
        });
    }

    /**
     * Sincronización de alias customer_name <-> nombre
     */
    public function setCustomerNameAttribute($value)
    {
        $this->attributes['nombre'] = $value;
    }

    public function getCustomerNameAttribute()
    {
        return $this->attributes['nombre'] ?? null;
    }

    /**
     * Sincronización de alias comment <-> comentario
     */
    public function setCommentAttribute($value)
    {
        $this->attributes['comentario'] = $value;
    }

    public function getCommentAttribute()
    {
        return $this->attributes['comentario'] ?? null;
    }

    /**
     * Sincronización de alias customer_phone <-> telefono
     */
    public function setCustomerPhoneAttribute($value)
    {
        $this->attributes['telefono'] = $value;
    }

    public function getCustomerPhoneAttribute()
    {
        return $this->attributes['telefono'] ?? null;
    }

    /**
     * Sincronización de alias customer_email <-> correo
     */
    public function setCustomerEmailAttribute($value)
    {
        $this->attributes['correo'] = $value;
    }

    public function getCustomerEmailAttribute()
    {
        return $this->attributes['correo'] ?? null;
    }

    /**
     * Sincronización de alias respuesta_admin <-> response / reply_text
     */
    public function setResponseAttribute($value)
    {
        $this->attributes['respuesta_admin'] = $value;
    }

    public function getResponseAttribute()
    {
        return $this->attributes['respuesta_admin'] ?? null;
    }

    public function setReplyTextAttribute($value)
    {
        $this->attributes['respuesta_admin'] = $value;
    }

    public function getReplyTextAttribute()
    {
        return $this->attributes['respuesta_admin'] ?? null;
    }

    public function getRespuestaAttribute()
    {
        return $this->attributes['respuesta_admin'] ?? null;
    }

    /**
     * Accesor para formatear las fotos como URLs absolutas completas.
     */
    public function getFotosAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }

        $fotos = is_array($value) ? $value : json_decode($value, true);
        if (!is_array($fotos)) {
            return [];
        }

        return array_values(array_map(function ($path) {
            if (empty($path)) return $path;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            $cleanPath = ltrim(str_replace(['public/', '/storage/', 'storage/'], '', $path), '/');
            return url('/storage/' . $cleanPath);
        }, $fotos));
    }

    /**
     * Relación con el platillo asociado a la reseña.
     */
    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    /**
     * Relación con las fotografías de la reseña.
     */
    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }

    /**
     * Relación con las fotografías de la reseña (alias en español).
     */
    public function imagenes()
    {
        return $this->hasMany(ReviewImage::class);
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
        $reviewItems = static::where('is_approved', true)->get();
        $testimonialItems = Testimonial::where('is_approved', true)->get();
        $reviews = $reviewItems->concat($testimonialItems);
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
}
