<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Auth;
use Validator;

use App\Models\Template;


class TemplateController extends Controller
{

    public function save_template(Request $request)
    {
        $data = $request->all();

        dd($data);

        Template::updateOrCreate([
            'templateable_type' => ,
            'templateable_id' => '',
        ],
        [
            'name' => '',
            'value' => '',
        ]);


    }

}
