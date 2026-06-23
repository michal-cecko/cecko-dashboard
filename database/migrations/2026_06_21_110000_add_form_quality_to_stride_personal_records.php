<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form quality (1–5) on a PR: how clean the rep/hold actually was. A sloppy 1RM
 * isn't the same as a textbook one, and the coach should program accordingly.
 * Optional — older records and quick logs just leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stride_personal_records', function (Blueprint $table) {
            $table->unsignedTinyInteger('form_quality')->nullable()->after('achieved_on');
        });
    }

    public function down(): void
    {
        Schema::table('stride_personal_records', function (Blueprint $table) {
            $table->dropColumn('form_quality');
        });
    }
};
