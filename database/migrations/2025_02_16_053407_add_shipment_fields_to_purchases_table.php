<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('shipment_name', 150)->after('reference_number'); // Adjust placement as needed
            $table->string('package_country')->after('shipment_name');
            $table->string('mode')->after('package_country');
            $table->string('barcode')->after('mode');
        });
    }

    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['shipment_name', 'package_country', 'mode', 'barcode']);
        });
    }
};
