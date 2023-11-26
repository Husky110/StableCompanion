<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_images', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('checkpoint_file_id');
            $table->string('filename');
            $table->text('positive');
            $table->text('negative');
            $table->string('sampler');
            $table->float('cfg');
            $table->integer('steps');
            $table->string('seed');
            $table->string('initial_size');
            $table->string('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
