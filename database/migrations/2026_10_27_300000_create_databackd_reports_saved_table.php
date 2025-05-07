<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AFV MVP (S3): saved financial reports with optional automatic e-mail delivery
return new class extends Migration {

    public function up()
    {
        Schema::create('fin_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['pivot', 'pnl', 'cashflow', 'counterparties'])->default('pivot');
            $table->json('config')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->enum('schedule', ['none', 'daily', 'weekly', 'monthly'])->default('none');
            $table->json('recipients')->nullable();
            $table->dateTime('last_sent_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'schedule']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fin_saved_reports');
    }

};
