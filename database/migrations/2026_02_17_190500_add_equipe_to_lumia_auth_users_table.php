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
        if (! Schema::connection('lumia_sqlsrv')->hasTable('lumia_auth_users')) {
            return;
        }

        if (! Schema::connection('lumia_sqlsrv')->hasColumn('lumia_auth_users', 'equipe')) {
            Schema::connection('lumia_sqlsrv')->table('lumia_auth_users', function (Blueprint $table) {
                $table->string('equipe', 120)->nullable();
            });
        }

        DB::connection('lumia_sqlsrv')
            ->table('lumia_auth_users')
            ->where('id', 1)
            ->update(['equipe' => 'CEO']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection('lumia_sqlsrv')->hasTable('lumia_auth_users')) {
            return;
        }

        if (Schema::connection('lumia_sqlsrv')->hasColumn('lumia_auth_users', 'equipe')) {
            Schema::connection('lumia_sqlsrv')->table('lumia_auth_users', function (Blueprint $table) {
                $table->dropColumn('equipe');
            });
        }
    }
};
