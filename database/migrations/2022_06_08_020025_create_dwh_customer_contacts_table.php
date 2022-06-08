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
        Schema::create('dwh_customer_contacts', function (Blueprint $table) {
            $table->foreignId('dwh_customer_contact_type_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->foreignId('dwh_customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['dwh_customer_contact_type_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dwh_customer_contacts');
    }
};