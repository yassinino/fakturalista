<?php

namespace App\Http\Controllers;
use App\Models\Family;
use Illuminate\Http\Request;
use App\Http\Requests\FamilyRequest;

use Validator;

class FamilyController extends Controller
{

    public function index()
    {
        $families = Family::all();
        return response(['families' => $families], 200);
    }

    public function store(FamilyRequest $request)
    {

        $new_family = Family::create([
            'name' => $request->name,
        ]);

        return response(['family' => $new_family], 200);
    }
}
