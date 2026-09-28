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
        Schema::create('lead_import_failures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_import_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('row_number');

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();

            $table->text('reason');

            $table->timestamps();

            $table->index('lead_import_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_import_failures');
    }
};
