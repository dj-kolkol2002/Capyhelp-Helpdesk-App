<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('customer_access_token', 80)->nullable()->unique()->after('channel');
        });

        DB::table('tickets')
            ->whereNull('customer_access_token')
            ->orderBy('id')
            ->each(function ($ticket): void {
                DB::table('tickets')
                    ->where('id', $ticket->id)
                    ->update(['customer_access_token' => Str::random(64)]);
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['customer_access_token']);
            $table->dropColumn('customer_access_token');
        });
    }
};
