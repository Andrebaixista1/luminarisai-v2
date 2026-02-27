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
        Schema::create('whitelabel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_name', 120)->default('Lumi.A');
            $table->string('logo_path')->nullable();
            $table->string('proprietary_slug', 80)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whitelabel_settings');
    }
};

