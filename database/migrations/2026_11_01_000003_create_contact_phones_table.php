<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 16);
            $table->timestamps();

            $table->unique(['contact_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_phones');
    }
};
