<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * A transação guarda o valor em centavos e uma cópia do token.
 * A fila só transporta a chave de idempotência; o worker lê o token daqui.
 */
class CreateTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('BRL');
            $table->string('status', 20);
            $table->string('token', 64);
            $table->string('gateway_reference', 64)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->char('idempotency_key', 36);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index('status');
            $table->foreign('card_id')->references('id')->on('cards');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}
