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
        Schema::create('loras', function (Blueprint $table){
            $table->id();
            $table->string('lora_name');
            $table->string('lora_type');
            $table->string('image_name');
            $table->string('civitai_id');
            $table->text('civitai_notes');
            $table->text('user_notes');
        });

        Schema::create('lora_tag', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('lora_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('lora_id')->references('id')->on('loras');
            $table->foreign('tag_id')->references('id')->on('tags');
        });

        Schema::create('lora_files', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('lora_id');
            $table->string('filepath');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();
            $table->string('baseModel')->nullable();

            $table->foreign('lora_id')->references('id')->on('loras');
        });

        Schema::rename('ai_images', 'ai_images_old');

        // crap... we have to recreate the whole thing, cause SQLite has problems adding a column...
        Schema::create('ai_images', function (Blueprint $table){
            $table->id();
            $table->string('model_file_type');
            $table->unsignedBigInteger('model_file_id');
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

        $currentImages = DB::table('ai_images_old')->get();
        foreach($currentImages as $image){
            $image['model_file_type'] = \App\Models\CheckpointFile::class;
            DB::table('ai_images')->insert($image);
        }

        Schema::dropIfExists('ai_images_old');

        (new \App\Models\Config(['key' => 'A1111-URL', 'value' => 'http://localhost:7860']))->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
