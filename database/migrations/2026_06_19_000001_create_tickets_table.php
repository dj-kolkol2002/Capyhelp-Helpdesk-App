<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('subject');
            $table->string('assignee')->default('unassigned');
            $table->string('status')->default('Open');
            $table->string('priority')->default('Normal');
            $table->string('channel')->default('Email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
