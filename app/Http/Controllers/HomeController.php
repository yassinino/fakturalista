<?php

namespace App\Http\Controllers;
use App\Models\Country;
use Illuminate\Http\Request;
use Validator;

class HomeController extends Controller
{

    public function index()
    {
        return view('index');
    }
}
