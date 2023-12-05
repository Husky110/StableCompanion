<?php

use App\Http\Helpers\CivitAIConnector;
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
            $table->string('lora_style_type');
            $table->string('image_name')->default('placeholder.png');
            $table->string('civitai_id')->nullable();
            $table->text('civitai_notes')->nullable();
            $table->text('user_notes')->nullable();
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
            $table->string('version_name')->nullable();
            $table->string('filepath');
            $table->string('baseModelType');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();

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
            $newImage = json_decode(json_encode($image, JSON_UNESCAPED_UNICODE), true);
            $newImage['model_file_type'] = \App\Models\CheckpointFile::class;
            $newImage['model_file_id'] = $newImage['checkpoint_file_id'];
            unset($newImage['checkpoint_file_id']);
            DB::table('ai_images')->insert($newImage);
        }

        Schema::dropIfExists('ai_images_old');

        (new \App\Models\Config(['key' => 'A1111-URL', 'value' => 'http://localhost:7860']))->save();

        Schema::table('checkpoint_files', function (Blueprint $table){
            $table->string('version_name')->nullable();
        });

        $checkpointFiles = \App\Models\CheckpointFile::with(['parentModel'])->get();
        foreach ($checkpointFiles as $file){
            $parentModel = \App\Models\Checkpoint::findOrFail($file->checkpoint_id); // cause we have new names for everything...
            $metaData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($parentModel->civitai_id, $file->civitai_version);
            if(isset($metaData['name'])){
                $file->version_name = $metaData['name'];
                $file->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
