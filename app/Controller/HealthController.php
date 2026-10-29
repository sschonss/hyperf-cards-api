<?php

declare(strict_types=1);

namespace App\Controller;

class HealthController extends AbstractController
{
    public function index()
    {
        return $this->response->json([
            'name' => 'cards-api',
            'health' => '/health',
            'cards' => '/cards',
            'transactions' => '/transactions/{id}',
        ]);
    }

    public function health()
    {
        return $this->response->json(['status' => 'ok']);
    }
}
