<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Dish;

class MenuController extends Controller
{
    public function index()
    {
        $dishes = Dish::all()->map(function ($d) {
            return [
                'id' => $d->id,
                'cat' => $d->category,
                'img' => asset($d->photo_path),
                'bn' => $d->name_bn,
                'en' => $d->name_en,
                'price' => $d->price,
            ];
        });
        return Inertia::render('Menu/index', [
            'dishes' => $dishes,
        ]);
    }

}
