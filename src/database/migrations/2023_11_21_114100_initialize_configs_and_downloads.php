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
        Schema::create('configs', function (Blueprint $table){
            $table->id();
            $table->string('key');
            $table->text('value');
        });

        Schema::create('civit_downloads', function (Blueprint $table){
            $table->id();
            $table->string('civit_id');
            $table->string('version');
            $table->string('url');
            $table->string('type');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->string('aria_id')->nullable();
            $table->boolean('load_examples')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
