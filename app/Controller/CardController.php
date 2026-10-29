<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\CardIndexRequest;
use App\Request\CardStoreRequest;
use App\Request\CardUpdateRequest;
use App\Service\CardService;

/**
 * Só HTTP: valida com FormRequest, chama o serviço e devolve JSON.
 * Não detecta bandeira e não fala com o banco.
 */
class CardController extends AbstractController
{
    public function __construct(private CardService $cards)
    {
    }

    public function store(CardStoreRequest $request)
    {
        $data = $request->validated();
        $card = $this->cards->create(
            (string) $data['holder_name'],
            (string) $data['number'],
            (int) $data['expiry_month'],
            (int) $data['expiry_year'],
        );

        return $this->response->json(['data' => $card])->withStatus(201);
    }

    public function index(CardIndexRequest $request)
    {
        $data = $request->validated();
        $brand = isset($data['brand']) && $data['brand'] !== '' ? (string) $data['brand'] : null;
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 15);

        return $this->response->json($this->cards->list($brand, $page, $perPage));
    }

    public function show(int $id)
    {
        return $this->response->json(['data' => $this->cards->show($id)]);
    }

    public function update(CardUpdateRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->response->json(['data' => $this->cards->update($id, $data)]);
    }

    public function destroy(int $id)
    {
        $this->cards->delete($id);

        return $this->response->raw('')->withStatus(204);
    }
}
