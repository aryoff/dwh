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
        Schema::create('dwh_partner_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dwh_partner_id')->constrained()->cascadeOnDelete();
            $table->string('identity');
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('profile')->default('{}'); //data diri customer
            } else {
                $table->json('profile')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['dwh_partner_id', 'identity']);
        });
        if (env('DB_CONNECTION', false) == 'pgsql') {
            DB::statement('CREATE INDEX dwh_partner_identities_profilegin ON dwh_partner_identities USING gin ((profile))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_partner_identities');
    }
};