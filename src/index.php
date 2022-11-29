<?php

namespace App;

const __PROJECT_ROOT__  = __DIR__;

use App\Controllers\Hotel\HotelListController;
use App\Entities\Database;
use App\Services\Hotel\UnoptimizedHotelService;

require_once __DIR__ . "/vendor/autoload.php";

$hotelService = UnoptimizedHotelService::getInstance();

$controller = new HotelListController( $hotelService );
$controller->render();
