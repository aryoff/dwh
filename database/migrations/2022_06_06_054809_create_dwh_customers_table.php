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
        Schema::create('dwh_customers', function (Blueprint $table) {
            $table->id();
            //TODO unique id pelanggan (simduk?)
            //TODO trigger perubahan data pelanggan
            //TODO pelanggan bisa dibedakan masuk untuk layanan apa ???
            //TODO data id sosmed / HP / email / Contact pelanggan
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('profile')->default('{}'); //data diri customer
            } else {
                $table->json('profile')->default('{}');
            }
            $table->timestamps();
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_customers_profilegin ON dwh_customers USING gin ((profile))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_customers');
    }
};