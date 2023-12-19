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
        $folderStructure = explode('/', $oldFilePath);
        $oldFilename = $folderStructure[count($folderStructure) -1];
        unset($folderStructure[count($folderStructure) -1]);
        $folderStructure = implode('/',$folderStructure).'/';
        if(str_starts_with($oldFilename, $modelFile->parentModel->civitai_id)){
            $newFileName = str_replace([$modelFile->parentModel->civitai_id, $modelFile->civitai_version], '', $oldFilename);
            $newFileName = str_replace('__', '', $newFileName); // to get rid of __
            $newFileName = explode('.', $newFileName);
            $extension = $newFileName[count($newFileName) - 1];
            unset($newFileName[count($newFileName) - 1]);
            $newFileName[count($newFileName) - 1] = $newFileName[count($newFileName) - 1].'_'.$modelFile->civitai_version;
            $newFileName = implode('.', $newFileName).'.'.$extension;
            $newFullPath = $folderStructure.$newFileName;
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
            $disk->move($modelFile->filepath, $newFullPath);
            $modelFile->filepath = $newFullPath;
            $modelFile->save();
        }
    }
};
