<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require 'vendor/autoload.php';

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => 'database.sqlite',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Drop existing tables
Capsule::schema()->dropIfExists('test_users');
Capsule::schema()->dropIfExists('test_posts');

// Create test_users table
Capsule::schema()->create('test_users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->text('bio')->nullable();
    $table->boolean('is_active')->default(true);
    $table->integer('age')->nullable();
    $table->decimal('rating', 2, 1)->nullable();
    $table->json('preferences')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->timestamps();
});

// Create test_posts table
Capsule::schema()->create('test_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('test_users');
    $table->string('title');
    $table->text('content');
    $table->enum('status', ['draft', 'published', 'archived']);
    $table->dateTime('published_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

echo "Test database tables created successfully!\n";
