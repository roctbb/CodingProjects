<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_review_installations', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary(); $t->uuid('uuid')->unique(); $t->string('signing_key',64);
        });
        Schema::create('material_review_revisions', function (Blueprint $t) {
            $t->bigIncrements('sequence'); $t->uuid('id')->unique(); $t->unsignedInteger('program_id')->index(); $t->unsignedInteger('lesson_id')->index();
            $t->uuid('base_id')->nullable(); $t->string('hash', 64); $t->string('source');
            $t->unsignedInteger('actor_id')->nullable(); $t->longText('snapshot'); $t->timestamp('created_at');
        });
        Schema::create('material_review_drafts', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->unsignedInteger('program_id')->index(); $t->string('title');
            $t->text('reason'); $t->unsignedInteger('version')->default(1); $t->string('state')->default('draft');
            $t->unsignedInteger('actor_id'); $t->longText('bases'); $t->longText('targets');
            $t->longText('metadata'); $t->string('reviewed_hash',64)->nullable(); $t->timestamps();
        });
        Schema::create('material_review_proposals', function (Blueprint $t) {
            $t->bigIncrements('sequence'); $t->uuid('id')->unique(); $t->uuid('draft_id')->index(); $t->string('external_id');
            $t->string('fingerprint',64); $t->string('kind'); $t->string('title'); $t->text('reason');
            $t->string('decision')->default('pending'); $t->longText('payload'); $t->text('comment')->nullable();
            $t->timestamp('published_at')->nullable(); $t->timestamps();
            $t->unique(['draft_id','external_id','fingerprint'],'material_review_proposal_identity');
        });
        Schema::create('material_review_events', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('object_id')->index(); $t->string('type');
            $t->unsignedInteger('actor_id')->nullable(); $t->longText('payload'); $t->timestamp('created_at');
        });
        Schema::create('material_review_publications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('draft_id')->unique(); $t->unsignedInteger('program_id')->index();
            $t->unsignedInteger('actor_id'); $t->longText('before'); $t->longText('after');
            $t->longText('id_map'); $t->longText('score_changes'); $t->longText('deadline_changes'); $t->timestamp('created_at');
        });
        Schema::create('material_review_resources', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->unsignedInteger('program_id')->index(); $t->string('mime',100);
            $t->string('sha256',64); $t->string('name'); $t->longText('data'); $t->timestamps();
            $t->string('original_sha256',64); $t->longText('original_data');
        });
        Schema::create('material_review_legacy', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('source',64); $t->string('table_name'); $t->string('row_key',64);
            $t->longText('payload'); $t->unique(['source','table_name','row_key'],'material_review_legacy_identity');
        });
        Schema::create('material_review_mappings', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('source',64); $t->text('source_path'); $t->string('path_hash',64);
            $t->unsignedInteger('program_id'); $t->unsignedInteger('lesson_id'); $t->string('entity');
            $t->unsignedInteger('entity_id'); $t->string('field'); $t->timestamps();
            $t->unique(['source','path_hash'],'material_review_mapping_identity');
        });
        Schema::create('material_review_progress', function (Blueprint $t) {
            $t->unsignedInteger('program_id'); $t->unsignedInteger('lesson_id'); $t->string('reviewed_hash',64)->nullable();
            $t->unsignedInteger('actor_id')->nullable(); $t->timestamps(); $t->primary(['program_id','lesson_id']);
        });
        Schema::table('program_steps', fn (Blueprint $t) => $t->timestamp('review_archived_at')->nullable());
        if (Schema::hasTable('solutions')) Schema::table('solutions',fn (Blueprint $t)=>$t->unsignedInteger('material_score_cap')->nullable());
        foreach (['solutions','code_submissions'] as $table) {
            if (Schema::hasTable($table)) Schema::table($table, fn (Blueprint $t) => $t->longText('material_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['solutions','code_submissions'] as $table) if (Schema::hasColumn($table,'material_snapshot')) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('material_snapshot'));
        }
        Schema::table('program_steps', fn (Blueprint $t) => $t->dropColumn('review_archived_at'));
        if (Schema::hasColumn('solutions','material_score_cap')) Schema::table('solutions',fn (Blueprint $t)=>$t->dropColumn('material_score_cap'));
        foreach (['progress','mappings','legacy','resources','publications','events','proposals','drafts','revisions','installations'] as $name) Schema::dropIfExists('material_review_'.$name);
    }
};
