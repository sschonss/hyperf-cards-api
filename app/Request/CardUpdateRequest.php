<?php

declare(strict_types=1);

namespace App\Request;

/**
 * Atualiza nome e validade. O PAN não volta: nós não o temos mais.
 */
class CardUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'holder_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}][\p{L}\s\'.-]{1,99}$/u'],
            'expiry_month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'expiry_year' => ['sometimes', 'required', 'integer', 'between:2000,2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'holder_name.regex' => 'O nome do portador só pode ter letras, espaços, apóstrofo, ponto e hífen.',
            'expiry_month.between' => 'O mês de validade deve estar entre 1 e 12.',
            'expiry_year.between' => 'O ano de validade está fora do intervalo aceito.',
        ];
    }

    public function withValidator($validator): void
    {
        $this->rejectFields(
            $validator,
            ['number', 'cvv', 'cvc', 'security_code', 'token', 'bin', 'last_four', 'brand'],
            'Este campo não pode ser alterado por aqui.'
        );

        $payload = $this->all();
        $validator->after(function ($validator) use ($payload) {
            $present = false;
            foreach (['holder_name', 'expiry_month', 'expiry_year'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                    $present = true;
                }
            }
            if (! $present) {
                $validator->errors()->add('holder_name', 'Informe o nome, o mês ou o ano para atualizar.');
            }
        });
    }
}
