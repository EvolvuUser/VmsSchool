<?php

use App\Http\Services\SmartMailer;
use Illuminate\Http\Request;



if (!function_exists('smart_mail')) {
    function smart_mail($to, $subject, $view, $data = [])
    {
        return (new SmartMailer())->send($to, $subject, $view, $data);
    }
}
