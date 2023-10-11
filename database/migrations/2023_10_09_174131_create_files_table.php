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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('name_og', 255);
            $table->bigInteger('file_size');
            $table->tinyInteger('status');
            $table->dateTime('publish_datetime')->nullable();
            $table->string('job_batch_id', 255);
            $table->foreign('job_batch_id')->references('id')->on('job_batches');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
