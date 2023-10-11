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
            $table->integer('id')->autoIncrement();
            $table->string('unique_key', 100)->unique();
            $table->string('product_title', 255);
            $table->text('product_description');
            $table->string('style', 125);
            $table->string('sanmar_mainframe_color', 125);
            $table->string('size', 10);
            $table->string('color_name', 125);
            $table->float('piece_price', 8, 2, true);
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
