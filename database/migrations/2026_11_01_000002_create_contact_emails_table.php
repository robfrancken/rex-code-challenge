<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('domain', 253)->index();
            $table->timestamps();

            $table->unique(['contact_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_emails');
    }
};
