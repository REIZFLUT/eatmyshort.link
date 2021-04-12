<?php

namespace App\Http;

use Continy\Core\Request;
use Continy\Core\Auth;
use Continy\Core\Validation;
use Continy\Core\Language;
use App\Models\User;
use App\Models\UserBlock;

class ApiController
{

    public function requestToken(Request $request)
    {

        if (!Auth::ipBlocked()) {

            $email = $request->iVal('email', 'email');
            $passw = $request->iVal('password');

            $validator = new Validation(
                [
                    'email'    => ['email', 'required'],
                    'password' => ['required']
                ]
            );

            if ($validator->check($request)) {

                $user = new User(false);
                $user->findByEmail($email);

                if ($user->id > 0) {
                    if (!Auth::blocked($email)) {
                        if ($user->verifyPwd($passw, $user->passwd)) {
                            $token = Auth::issueToken($user);
                            Auth::unsetAttempts($user->email);
                            return view_json(['token' => $token, 'message' => '', 'status' => 'success', 'validation' => []]);
                        } else {
                            Auth::addAttempt($email);
                            Auth::checkAttempts($email);
                            return view_json(['token' => NULL, 'message' => Language::get('login/error/failed'), 'status' => 'error', 'validation' => $validator->getErrors()]);
                        }
                    } else {
                        return view_json(['token' => NULL, 'message' => UserBlock::msg(), 'status' => 'error', 'validation' => []]);
                    }
                } else {
                    Auth::addAttempt($email); //user not found
                    Auth::checkAttempts($email);
                    return view_json(['token' => NULL, 'message' => Language::get('login/error/failed'), 'status' => 'error', 'validation' => []]);
                }
            } else {
                Auth::addAttempt($email); //user not found
                Auth::checkAttempts($email);
                return view_json(['token' => NULL, 'message' => Language::get('login/error/validation'), 'status' => 'error', 'validation' => [$validator->getErrorMessages()]]);
            }
        } else {
            return view_json(['token' => NULL, 'message' => UserBlock::msg(), 'status' => 'error', 'validation' => []]);
        }
    }
}
