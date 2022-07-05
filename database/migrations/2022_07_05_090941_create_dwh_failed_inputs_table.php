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
        Schema::create('dwh_failed_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('input_type');
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('data')->default('{}');
            } else {
                $table->json('data')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->index('input_type');
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_failed_inputs_datagin ON dwh_failed_inputs USING gin ((data))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_failed_inputs');
    }
};