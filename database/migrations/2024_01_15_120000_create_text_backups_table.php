<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTextBackupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('text_backups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('backup_id')->index(); // e.g., 'course_1_2024-01-15_12-00-00'
            $table->integer('course_id')->index();
            $table->string('entity_type'); // 'course', 'lesson', 'step', 'task'
            $table->integer('entity_id');
            $table->string('field_name'); // 'name', 'description', 'text', 'solution', etc.
            $table->longText('original_text')->nullable();
            $table->timestamps();
            
            // Index for efficient querying
            $table->index(['backup_id', 'entity_type', 'entity_id']);
            $table->index(['course_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('text_backups');
    }
}