<?php

use App\Enum\UploadStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {


    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('file_name');
            $table->string('identifier');
            $table->integer('total_chunks')->unsigned();
            $table->integer('uploaded_chunks')->default(0);
            $table->enum('status', UploadStatus::toArray())->default(UploadStatus::PENDING);
            $table->foreignIdFor(User::class)->constrained();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
