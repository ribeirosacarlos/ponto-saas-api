<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'sometimes|uuid|exists:plans,id',
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (SubscriptionStatus $status) => $status->value, SubscriptionStatus::cases()))],
            'trial_ends_at' => 'sometimes|nullable|date',
            'current_period_start' => 'sometimes|nullable|date',
            'current_period_end' => 'sometimes|nullable|date',
            'canceled_at' => 'sometimes|nullable|date',
            'past_due_since' => 'sometimes|nullable|date',
            'grace_period_days' => 'sometimes|integer|min:0',
            'stripe_customer_id' => 'sometimes|nullable|string',
            'stripe_subscription_id' => 'sometimes|nullable|string',
            'metadata' => 'sometimes|array',
        ];
    }
}
