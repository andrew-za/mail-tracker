<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSentEmailsAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sent_emails_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sent_email_id')->unsigned();
            $table->foreign('sent_email_id')->references('id')->on('sent_emails')->onDelete('cascade');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
