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
        Schema::create('checkpoints', function (Blueprint $table){
            $table->id();
            $table->string('checkpoint_name');
            $table->string('image_name')->default('placeholder.png');
            $table->string('civitai_id')->nullable();
            $table->text('civit_notes')->nullable();
            $table->text('user_notes')->nullable();
        });

        Schema::create('checkpoint_files', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('checkpoint_id');
            $table->string('filepath');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();
            $table->string('baseModel')->nullable();

            $table->foreign('checkpoint_id')->references('id')->on('checkpoints');
        });

        Schema::create('checkpoint_tag', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('checkpoint_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('checkpoint_id')->references('id')->on('checkpoints');
            $table->foreign('tag_id')->references('id')->on('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
