<?php

namespace App\Http\Requests;

use App\Http\Requests\StoreReviewRequest;

class StoreTestimonialRequest extends StoreReviewRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
