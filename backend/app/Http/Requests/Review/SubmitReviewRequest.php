<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The public, unauthenticated "Add a review" wizard — eligibility is
 * checked by the typed email against orders.customer_email (see
 * ReviewService::findEligibleOrderForEmail), not by who's logged in, so
 * there's no policy gate here the way StoreReviewRequest has one.
 */
class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'email' => ['required', 'email', 'max:255'],
            'display_name' => ['required', 'string', 'max:150'],
            'is_anonymous' => ['sometimes', 'boolean'],
            // Present only when arriving from the 30-day reminder email's
            // pre-filled review wizard (see ProductController::submitReview()
            // and OrderThirtyDayReminderMail::reviewUrl()) - re-verified
            // server-side against orders.review-identity's own signature,
            // never trusted as a bare client-asserted flag.
            'order_id' => ['sometimes', 'integer'],
            'expires' => ['sometimes', 'string'],
            'signature' => ['sometimes', 'string'],
        ];
    }
}
