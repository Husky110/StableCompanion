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
        Schema::table('tags', function (Blueprint $table){
            $table->dropColumn('checkpoint_tag');
        });
        Schema::table('tags', function (Blueprint $table){
            $table->dropColumn('lora_tag');
        });
        Schema::table('tags', function (Blueprint $table){
            $table->dropColumn('embedding_tag');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }

};
