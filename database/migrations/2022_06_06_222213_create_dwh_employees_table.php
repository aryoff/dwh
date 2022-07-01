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
        Schema::create('dwh_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merge_id')->nullable();
            $table->foreign('merge_id')->references('id')->on('dwh_employees')->cascadeOnDelete();
            $table->string('name');
            $table->string('simduk')->unique()->nullable();
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('profile')->default('{"dwh_source":{}}');
            } else {
                $table->json('profile')->default('{"dwh_source":{}}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('merge_id');
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_employees_profilegin ON dwh_employees USING gin ((profile))');
            DB::statement('CREATE INDEX dwh_employees_profilesourcegin ON dwh_employees USING gin ((profile->\'dwh_source\'))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_employees');
    }
};