<?php

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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('canonical_url')->unique();
            $table->string('brand');
            $table->string('name');
            $table->string('platform')->index()->comment('e.g. amazon, flipkart, nykaa');
            $table->string('vector_id')->nullable()->index()->comment('Pinecone record mapping ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
