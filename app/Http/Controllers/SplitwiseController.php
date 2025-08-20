<?php

namespace App\Http\Controllers;

class SplitwiseController extends Controller
{
    public function index(Category $category) {
        $user = auth()->user();
        if ($user) {
            $categories = Category::all()->sortBy('title');
        }

        $hided = $user->hidedCategories()->allRelatedIds()->all();
        $categories->filter(function($value, $key) use ($hided, $categories) {
            if(in_array($value->id, $hided)) {
                $categories->forget($key);
            }
        });


        $slogan = Slogan::all();

        if(count($slogan) > 0) {
            $randomSlogan = $slogan->random();
        } else {
            $randomSlogan["sloganPhrase"] = "Find your albums";
        }

        return view('forum.categories.index', [
            'user' => $user,
            'slogan' => $randomSlogan,
            'categories' => $categories
        ]);
    }
}