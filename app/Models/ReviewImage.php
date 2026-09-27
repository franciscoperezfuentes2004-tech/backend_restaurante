<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $table = 'review_images';

    protected $fillable = [
        'review_id',
        'image_path',
        'is_approved',
        'status',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
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
