<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Session;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Выкидывает исключение с сообщением при ошибке валидации
     *
     * @param $validator
     * @param string $action
     * @param int $id
     */
    public function checkValidator($validator, $action = '', $id = -1)
    {
        $request = request();
        if ($validator->fails()) {
            if ($action != '') {
                Session::flash('action', $action);
                Session::flash('lastID', $id);
            }
            $this->throwValidationException($request, $validator);
        }
    }

    /**
     * Возвращает редирект назад с сообщением
     *
     * @param string $action
     * @param int $id
     * @return $this
     */
    public function backException($action = '', $id = -1)
    {
        if ($action != '') {
            Session::flash('action', $action);
            Session::flash('lastID', $id);
        }
        return redirect()->back()->withInput();
    }

    public function make_error_alert($title, $text, $destination = 'head')
    {
        Session::flash('alert-class', 'alert-danger');
        Session::flash('alert-title', $title);
        Session::flash('alert-text', $text);
        Session::flash('alert-destination', $destination);
    }
    public function make_success_alert($title, $text, $destination = 'head')
    {
        Session::flash('alert-class', 'alert-success');
        Session::flash('alert-title', $title);
        Session::flash('alert-text', $text);
        Session::flash('alert-destination', $destination);
    }
    public function make_info_alert($title, $text, $destination = 'head')
    {
        Session::flash('alert-class', 'alert-info');
        Session::flash('alert-title', $title);
        Session::flash('alert-text', $text);
        Session::flash('alert-destination', $destination);
    }
}
