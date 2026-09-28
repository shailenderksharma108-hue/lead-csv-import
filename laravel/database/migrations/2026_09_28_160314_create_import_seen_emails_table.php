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
        Schema::create('import_seen_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_import_id')
                ->constrained('lead_imports')
                ->cascadeOnDelete();

            $table->string('email');

            $table->unique([
                'lead_import_id',
                'email',
            ]);

            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_seen_emails');
    }
};
