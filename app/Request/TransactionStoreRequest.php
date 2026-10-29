<?php

declare(strict_types=1);

namespace App\Request;

class TransactionStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'currency' => ['sometimes', 'in:BRL,brl'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor mínimo é 0,01.',
            'currency.in' => 'Nesta aula a moeda é BRL.',
        ];
    }
}
