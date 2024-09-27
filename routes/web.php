<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $arr = [1, 2, 3, 4, 5];

    $randNumber = Arr::random($arr);

    $text = "The number this time is $randNumber";

    return $text;
});
