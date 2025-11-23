<?php

namespace App\Http\Controllers;
use App\Models\Country;
use Illuminate\Http\Request;
use Validator;

class CountryController extends Controller
{

    public function index()
    {
        $countries = Country::all();
        return response(['countries' => $countries], 200);
    }
}
