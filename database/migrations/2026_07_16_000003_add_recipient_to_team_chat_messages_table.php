<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('team_chat_messages', 'recipient_id')) {
            return;
        }

        Schema::table('team_chat_messages', function (Blueprint $table) {
            $table->foreignId('recipient_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('team_chat_messages', 'recipient_id')) {
            return;
        }

        Schema::table('team_chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
        });
    }
};
