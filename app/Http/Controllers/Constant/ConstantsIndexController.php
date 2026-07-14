<?php

namespace App\Http\Controllers\Constant;

use App\Http\Controllers\Controller;
use Helper\Response\Response;
use Helper\Type\Gender\Gender;

class ConstantsIndexController extends Controller {
    /**
     * Gender constants used by the frontend forms.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGender() {
        $data = [
            'gender' => Gender::idAndName(),
        ];

        return Response::_200($data);
    }
}
