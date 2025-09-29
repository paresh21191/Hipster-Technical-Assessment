<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserDiscountsTable extends Migration
{
    public function up()
    {
        Schema::create('user_discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('discount_id')->index();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('discount_id')->references('id')->on('discounts')->onDelete('cascade');
            $table->unique(['user_id', 'discount_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_discounts');
    }
}