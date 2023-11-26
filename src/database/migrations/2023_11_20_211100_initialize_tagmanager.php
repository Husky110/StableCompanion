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
        Schema::create('tags', function (Blueprint $table){
            $table->id();
            $table->string('tagname');
            $table->boolean('checkpoint_tag')->default(1);
            $table->boolean('lora_tag')->default(1);
            $table->boolean('embedding_tag')->default(1);
        });

        DB::table('tags')->insert([
            [
                'tagname' => 'anime',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'photorealistic',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'cartoon',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'nsfw',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'sd',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'xl',
                'checkpoint_tag' => true,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'pose',
                'checkpoint_tag' => false,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'concept',
                'checkpoint_tag' => false,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'artstyle',
                'checkpoint_tag' => false,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],
            [
                'tagname' => 'character',
                'checkpoint_tag' => false,
                'lora_tag' => true,
                'embedding_tag' => true,
            ],

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
