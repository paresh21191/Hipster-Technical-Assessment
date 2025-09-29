<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
          Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_image_id')->nullable()->after('price');
            $table->foreign('primary_image_id')
                  ->references('id')
                  ->on('images')
                  ->nullOnDelete();
        });
    }

    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['primary_image_id']);
            $table->dropColumn('primary_image_id');
        });
    }
};
