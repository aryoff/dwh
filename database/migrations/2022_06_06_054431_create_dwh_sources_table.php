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
        Schema::create('dwh_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dwh_partner_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('parameter')->default('{}');
            } else {
                $table->json('parameter')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['dwh_partner_id']);
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_sources_parametergin ON dwh_sources USING gin ((parameter))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_sources');
    }
};