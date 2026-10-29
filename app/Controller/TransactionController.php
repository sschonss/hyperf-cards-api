<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\TransactionStoreRequest;
use App\Service\TransactionService;

/**
 * A criação responde 202 porque o adquirente ainda não foi chamado.
 * O status final aparece em GET /transactions/{id}.
 */
class TransactionController extends AbstractController
{
    public function __construct(private TransactionService $transactions)
    {
    }

    public function store(TransactionStoreRequest $request, int $id)
    {
        $data = $request->validated();
        $transaction = $this->transactions->create(
            $id,
            $data['amount'],
            (string) ($data['currency'] ?? 'BRL'),
        );

        return $this->response->json(['data' => $transaction])->withStatus(202);
    }

    public function show(int $id)
    {
        return $this->response->json(['data' => $this->transactions->show($id)]);
    }
}
