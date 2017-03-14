<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModelMorphRelationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sent_emails', function(Blueprint $table) {
            $table->string('notification_type')->nullable();
            $table->string('notification_class')->nullable();
            $table->string('modeltable_type')->nullable();
            $table->integer('modeltable_id')->nullable()->unsigned();
            $table->timestamp('opened_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
