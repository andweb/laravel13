<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->date('for_date')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('request');
            $table->text('response')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['kind', 'for_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
