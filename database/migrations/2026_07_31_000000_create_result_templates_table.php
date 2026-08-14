<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * result_templates stores the GrapesJS project data (grapes_json),
     * the compiled HTML/CSS used for PDF rendering, and paper settings.
     */
    public function up(): void
    {
        Schema::create('result_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->string('name');
            $table->longText('grapes_json')->nullable();
            $table->longText('compiled_html')->nullable();
            $table->longText('compiled_css')->nullable();
            $table->string('paper_size', 20)->default('A4');
            $table->string('orientation', 10)->default('portrait');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Uncomment the FK if your schools table is named `schools`.
            // $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_templates');
    }
};
