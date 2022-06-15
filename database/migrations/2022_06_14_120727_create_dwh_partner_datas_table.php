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
        Schema::create('dwh_partner_datas', function (Blueprint $table) {
            $table->foreignId('dwh_partner_identity_id')->constrained()->cascadeOnDelete();
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('data')->default('{}'); //data diri customer
            } else {
                $table->json('data')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('dwh_partner_identity_id');
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_partner_datas_datagin ON dwh_partner_datas USING gin ((data))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_partner_datas');
    }
};