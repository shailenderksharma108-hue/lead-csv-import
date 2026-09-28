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
        Schema::table('lead_imports', function (Blueprint $table) {
            $table->unsignedInteger('processed_records')
                ->default(0)
                ->after('total_records');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_imports', function (Blueprint $table) {
            $table->dropColumn('processed_records');
        });
    }

};
