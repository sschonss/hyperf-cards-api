<?php

declare(strict_types=1);

namespace App\Request;

/**
 * Aceita o PAN só para a criação. O serviço deriva BIN, final e bandeira
 * e encaminha o número ao cofre. CVV é proibido.
 */
class CardStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'holder_name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}][\p{L}\s\'.-]{1,99}$/u'],
            'number' => ['required', 'string', 'regex:/^\d{13,19}$/'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'holder_name.required' => 'Informe o nome impresso no cartão.',
            'holder_name.regex' => 'O nome do portador só pode ter letras, espaços, apóstrofo, ponto e hífen.',
            'number.required' => 'Informe o número do cartão.',
            'number.regex' => 'O número do cartão deve ter entre 13 e 19 dígitos.',
            'expiry_month.required' => 'Informe o mês de validade.',
            'expiry_month.between' => 'O mês de validade deve estar entre 1 e 12.',
            'expiry_year.required' => 'Informe o ano de validade.',
            'expiry_year.between' => 'O ano de validade está fora do intervalo aceito.',
        ];
    }

    public function withValidator($validator): void
    {
        $this->rejectFields(
            $validator,
            ['cvv', 'cvc', 'security_code'],
            'O CVV não pode ser enviado. Ele nunca é armazenado.'
        );
    }
}
