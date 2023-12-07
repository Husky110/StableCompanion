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
        Schema::create('embeddings', function (Blueprint $table){
            $table->id();
            $table->string('model_name');
            $table->string('image_name')->default('placeholder.png');
            $table->string('civitai_id')->nullable();
            $table->text('civitai_notes')->nullable();
            $table->text('user_notes')->nullable();
        });

        Schema::create('embedding_files', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('base_id');
            $table->string('version_name')->nullable();
            $table->string('filepath');
            $table->string('baseModelType');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();
            $table->text('trained_words')->nullable();
        });

        Schema::create('embedding_tag', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('embedding_id');
            $table->unsignedBigInteger('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
