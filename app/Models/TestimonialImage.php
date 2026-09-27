<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestimonialImage extends Model
{
    protected $fillable = [
        'testimonial_id', 'image_path', 'status'
    ];

    public function testimonial()
    {
        return $this->belongsTo(Testimonial::class);
    }

    public function getImagePathAttribute($value)
    {
        if (empty($value)) return $value;
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        $cleanPath = ltrim(str_replace(['public/', '/storage/', 'storage/'], '', $value), '/');
        return url('/storage/' . $cleanPath);
    }
}
