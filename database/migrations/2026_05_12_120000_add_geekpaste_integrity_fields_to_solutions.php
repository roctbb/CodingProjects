<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGeekpasteIntegrityFieldsToSolutions extends Migration
{
    public function up()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->string('geekpaste_code_id', 32)->nullable()->after('text')->index();
            $table->boolean('geekpaste_ai_warning')->default(false)->after('review_skipped');
            $table->string('geekpaste_ai_confidence', 20)->nullable()->after('geekpaste_ai_warning');
            $table->text('geekpaste_ai_reasons')->nullable()->after('geekpaste_ai_confidence');
            $table->unsignedTinyInteger('geekpaste_llm_probability')->nullable()->after('geekpaste_ai_reasons');
            $table->boolean('geekpaste_similarity_checked')->default(false)->after('geekpaste_llm_probability');
            $table->boolean('geekpaste_similarity_warning')->default(false)->after('geekpaste_similarity_checked');
            $table->boolean('geekpaste_similarity_critical')->default(false)->after('geekpaste_similarity_warning');
            $table->unsignedTinyInteger('geekpaste_similarity_max_percent')->nullable()->after('geekpaste_similarity_critical');
            $table->unsignedInteger('geekpaste_similarity_matches_count')->default(0)->after('geekpaste_similarity_max_percent');
            $table->dateTime('geekpaste_integrity_synced_at')->nullable()->after('geekpaste_similarity_matches_count');
        });
    }

    public function down()
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn([
                'geekpaste_code_id',
                'geekpaste_ai_warning',
                'geekpaste_ai_confidence',
                'geekpaste_ai_reasons',
                'geekpaste_llm_probability',
                'geekpaste_similarity_checked',
                'geekpaste_similarity_warning',
                'geekpaste_similarity_critical',
                'geekpaste_similarity_max_percent',
                'geekpaste_similarity_matches_count',
                'geekpaste_integrity_synced_at',
            ]);
        });
    }
}
