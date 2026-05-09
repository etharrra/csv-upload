<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['job_batch_id']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->string('storage_disk', 50)->default('s3')->after('file_size');
            $table->string('storage_path', 1024)->nullable()->unique()->after('storage_disk');
            $table->string('job_batch_id', 255)->nullable()->change();

            $table->foreign('job_batch_id')->references('id')->on('job_batches');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['job_batch_id']);
            $table->dropUnique(['storage_path']);
            $table->dropColumn(['storage_disk', 'storage_path']);
            $table->string('job_batch_id', 255)->nullable(false)->change();

            $table->foreign('job_batch_id')->references('id')->on('job_batches');
        });
    }
};
