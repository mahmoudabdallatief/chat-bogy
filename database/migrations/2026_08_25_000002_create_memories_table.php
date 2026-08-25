<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMemoriesTable extends Migration
{
    public function up()
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_id')->index();
            $table->string('key')->index();
            $table->text('value');
            $table->string('type')->nullable()->index();
            $table->json('tags')->nullable();
            $table->boolean('is_recallable')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->index(['device_id', 'key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('memories');
    }
}
