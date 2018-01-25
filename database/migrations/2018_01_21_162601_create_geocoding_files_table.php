<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGeocodingFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('geocoding_files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('path');
            $table->string('email');
            $table->integer('offset')->default(0);
            $table->boolean('done')->default(false);
            $table->json('indexes');
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
        Schema::dropIfExists('geocoding_files');
    }
}
