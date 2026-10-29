<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * O que a tabela guarda de propósito: token, BIN e final.
 * Não há coluna para o PAN nem para o CVV.
 */
class CreateCardsTable extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('holder_name', 100);
            $table->char('bin', 6);
            $table->char('last_four', 4);
            $table->string('brand', 20);
            $table->unsignedTinyInteger('expiry_month');
            $table->unsignedSmallInteger('expiry_year');
            $table->string('token', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('token');
            $table->index('brand');
            $table->index(['bin', 'last_four', 'expiry_month', 'expiry_year'], 'cards_fingerprint_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
}
