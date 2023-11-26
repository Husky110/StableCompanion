<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/testAria', function (){
//   $x = new Aria2();
//    $y = $x->addUri(
//        ['https://civitai.com/api/download/models/233520'],
//        [
//            'dir'=>'/data/checkpoints',
//            //'on-download-complete' => 'cd /var/www && php /var/www/artisan stablecompanion:clear-civitaicache'
//        ],
//    );
//    dd($y);
    dd(\App\Http\Helpers\Aria2Connector::getInstance()->tellStatus('f569c51a8d6fd912'));
    //dd($x->tellStatus('c7e1d4887887d1c9'));
});

Route::get('/testCivit', function (){
    //dd(\App\Http\Helpers\CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID(85731, 222685));
    //dd(\Illuminate\Support\Facades\Storage::disk('checkpoints')->path(''));
   dd(\App\Http\Helpers\CivitAIConnector::getModelMetaByID(85731));
});

Route::get('/test', function (){
    \App\Models\Checkpoint::scanCheckpointFolderForNewFiles();
});
