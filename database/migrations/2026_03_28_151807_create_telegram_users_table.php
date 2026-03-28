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
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->integer('daily_limit')->default(3);
            $table->integer('used_today')->default(0);
            $table->integer('premium_credits')->default(0);
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->date('last_active_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
