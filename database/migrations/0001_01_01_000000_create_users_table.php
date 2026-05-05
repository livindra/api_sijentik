<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('address');
            $table->string('profile_photo')->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->string('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();

            $table->string('rtrw');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};