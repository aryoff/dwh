<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dwh_customer', function (Blueprint $table) {
            $table->id();
            //TODO unique id pelanggan (simduk?)
            //TODO data diri pelanggan
            //TODO trigger perubahan data pelanggan
            //TODO pelanggan bisa dibedakan masuk untuk layanan apa ???
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
        Schema::dropIfExists('dwh_customer');
    }
};