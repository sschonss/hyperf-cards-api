<?php

declare(strict_types=1);

namespace App\Request;

use App\Service\CardBrandDetector;

class CardIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'nullable', 'in:' . implode(',', CardBrandDetector::BRANDS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand.in' => 'Bandeira desconhecida. Use visa, mastercard, elo, amex ou hipercard.',
            'per_page.max' => 'per_page máximo é 50.',
        ];
    }
}
