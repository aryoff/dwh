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
        Schema::create('dwh_interactions', function (Blueprint $table) {
            $table->foreignId('dwh_source_id')->constrained(); //sumber data (index?)
            $table->foreignId('dwh_customer_id')->constrained()->cascadeOnDelete();
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('data')->default('{}'); //data interaksi
            } else {
                $table->json('data')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_interactions_datagin ON dwh_interactions USING gin ((data))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_interactions');
    }
};