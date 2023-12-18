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
        // I'm doing this again, because of the embeddings... It would be bad if you have the version-number in there
        // as standard... makes it harder to share...
        $checkpointFiles = \App\Models\CheckpointFile::with('parentModel')->get();
        $loraFiles = \App\Models\LoraFile::with('parentModel')->get();
        $embeddingFiles = \App\Models\EmbeddingFile::with('parentModel')->get();
        foreach ($checkpointFiles as $checkpointFile){
            $this->fixFilename($checkpointFile);
        }
        foreach ($loraFiles as $loraFile){
            $this->fixFilename($loraFile);
        }
        foreach ($embeddingFiles as $embeddingFile){
            $this->fixFilename($embeddingFile);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }

    private function fixFilename($modelFile)
    {
        $oldFilePath = $modelFile->filepath;
        if(str_contains($oldFilePath, '_'.$modelFile->civitai_version) && $modelFile->civitai_version != ''){
            $newFilePath = str_replace('_'.$modelFile->civitai_version, '', $oldFilePath);
            switch (get_class($modelFile)){
                case \App\Models\CheckpointFile::class:
                    $disk = \Illuminate\Support\Facades\Storage::disk('checkpoints');
                    break;
                case \App\Models\LoraFile::class:
                    $disk = \Illuminate\Support\Facades\Storage::disk('loras');
                    break;
                case \App\Models\EmbeddingFile::class:
                    $disk = \Illuminate\Support\Facades\Storage::disk('embeddings');
                    break;
            }
            $disk->move($modelFile->filepath, $newFilePath);
            $modelFile->filepath = $newFilePath;
            $modelFile->save();

            $filePathStructure = explode('.', $oldFilePath);
            unset($filePathStructure[count($filePathStructure) - 1]); // remove extension
            $filePathStructure = implode('.', $filePathStructure);
            if($disk->exists($filePathStructure.'.preview.png')){
                $newPreviwFilePath = explode('.', $newFilePath);
                unset($newPreviwFilePath[count($newPreviwFilePath) - 1]);
                $newPreviwFilePath = implode('.', $newPreviwFilePath).'.preview.png';
                $disk->move($filePathStructure.'.preview.png', $newPreviwFilePath);
            }
        }
    }
};
