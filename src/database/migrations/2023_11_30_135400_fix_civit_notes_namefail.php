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
        Schema::rename('checkpoint_files', 'checkpoint_files_old');

        Schema::create('checkpoint_files', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('base_id');
            $table->string('version_name')->nullable();
            $table->string('filepath');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();
            $table->string('baseModel')->nullable();
            $table->text('trained_words')->nullable();
        });

        $checkpointFiles = DB::table('checkpoint_files_old')->get();
        foreach ($checkpointFiles as $file){
            $entry = json_decode(json_encode($file),true);
            $entry['base_id'] = $entry['checkpoint_id'];
            unset($entry['checkpoint_id']);
            DB::table('checkpoint_files')->insert($entry);
        }

        Schema::dropIfExists('checkpoint_files_old');

        Schema::dropIfExists('lora_files');

        Schema::create('lora_files', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('base_id');
            $table->string('version_name')->nullable();
            $table->string('filepath');
            $table->string('baseModelType');
            $table->string('civitai_version')->nullable();
            $table->text('civitai_description')->nullable();
            $table->text('trained_words')->nullable();
        });

        Schema::rename('checkpoint_tag', 'checkpoint_tag_old');

        Schema::create('checkpoint_tag', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('checkpoint_id');
            $table->unsignedBigInteger('tag_id');
        });

        $checkpointTags = DB::table('checkpoint_tag_old')->get();
        foreach ($checkpointTags as $tag){
            DB::table('checkpoint_tag')->insert(json_decode(json_encode($tag),true));
        }

        Schema::dropIfExists('checkpoint_tag_old');

        Schema::dropIfExists('lora_tag');

        Schema::create('lora_tag', function (Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('lora_id');
            $table->unsignedBigInteger('tag_id');
        });

        // Split up cause of SQLite:  SQLite doesn't support multiple calls to dropColumn / renameColumn in a single modification.
        Schema::table('checkpoints', function (Blueprint $table){
            $table->renameColumn('civit_notes','civitai_notes');
        });

        Schema::table('checkpoints', function (Blueprint $table){
            $table->renameColumn('checkpoint_name', 'model_name');
        });

        Schema::table('loras', function (Blueprint $table){
            $table->dropColumn('lora_style_type');
        });

        Schema::table('loras', function (Blueprint $table){
            $table->renameColumn('lora_name', 'model_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
