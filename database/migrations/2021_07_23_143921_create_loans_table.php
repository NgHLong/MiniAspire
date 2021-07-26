<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id()->autoIncrement();
            $table->integer("user_id");
            $table->decimal("original_payment", $precision = 12, $scale = 2)->default(0);
            $table->integer("duration")->default(0);
            $table->decimal("interest_rate", $precision = 8, $scale = 2)->default(0);
            $table->decimal("arrangement_fee", $precision = 12, $scale = 2)->default(0);
            $table->decimal("pay_per_month", $precision = 12, $scale = 2)->default(0);
            $table->decimal("final_payment", $precision = 12, $scale = 2)->default(0);
            $table->decimal("left_over", $precision = 12, $scale = 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('loans');
    }
}
