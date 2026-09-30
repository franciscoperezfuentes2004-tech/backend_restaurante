<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "REVIEWS COLUMNS:\n";
print_r(Illuminate\Support\Facades\Schema::getColumnListing('reviews'));
echo "TESTIMONIALS COLUMNS:\n";
print_r(Illuminate\Support\Facades\Schema::getColumnListing('testimonials'));
