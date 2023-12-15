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
        $checkpointFiles = \App\Models\CheckpointFile::with('parentModel')->get();
        $loraFiles = \App\Models\LoraFile::with('parentModel')->get();
        $embeddingFiles = \App\Models\EmbeddingFile::with('parentModel')->get();
        foreach ($checkpointFiles as $checkpointFile){
            $checkpointFile->changePreviewImage();
        }
        foreach ($loraFiles as $loraFile){
            $loraFile->changePreviewImage();
        }
        foreach ($embeddingFiles as $embeddingFile){
            $embeddingFile->changePreviewImage();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
