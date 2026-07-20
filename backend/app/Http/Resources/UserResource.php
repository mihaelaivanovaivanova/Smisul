<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'newsletter_subscription' => $this->newsletter_subscription,
            'marketing_consent' => $this->marketing_consent,
            'gdpr_consent_at' => $this->gdpr_consent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Terms/Privacy versions this account hasn't agreed to yet —
            // either never accepted, or accepted an older version that's
            // since been superseded (see ConsentService::outstandingForAccount
            // and the LegalDocumentUpdated notification that's sent when
            // that happens). Present on every auth response (login,
            // register, /profile) so the frontend can show a re-acceptance
            // prompt as soon as it knows who's signed in.
            'outstanding_legal_documents' => LegalDocumentResource::collection(
                app(ConsentService::class)->outstandingForAccount($this->resource),
            ),
        ];
    }
}
