<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('zone')->nullable(); // Replace with an actual column name
            $table->unsignedBigInteger('delivery_id')->nullable()->after('zone');

            $table->foreign('delivery_id')->references('id')->on('deliveries')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_id']);
            $table->dropColumn(['zone', 'delivery_id']);
        });
    }
};
