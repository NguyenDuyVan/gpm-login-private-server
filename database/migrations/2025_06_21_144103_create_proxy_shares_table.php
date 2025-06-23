<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proxy_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proxy_id')->constrained('proxies')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['FULL', 'EDIT', 'VIEW'])->default('VIEW');
            $table->timestamps();

            // Ensure unique combination of proxy_id and user_id
            $table->unique(['proxy_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proxy_shares');
    }
};
