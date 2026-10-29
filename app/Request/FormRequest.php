<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest as BaseFormRequest;

abstract class FormRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CVV nunca entra. Se o campo existir, a requisição é recusada.
     *
     * @param list<string> $fields
     */
    protected function rejectFields($validator, array $fields, string $message): void
    {
        $payload = $this->all();
        $validator->after(function ($validator) use ($payload, $fields, $message) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $payload)) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
