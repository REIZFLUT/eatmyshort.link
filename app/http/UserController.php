<?php

namespace App\Http;


use App\Models\User;
use Continy\Core\Collection;
use Continy\Core\Request;
use Continy\Core\Validation;

class UserController
{

    public function index()
    {
        $collection = new Collection('ListableUser');
        $user_list = $collection->all(false, 0, '', 'ORDER BY id ASC');

        return view('default', 'emsl/userlist', ['list' => $user_list]);
    }

    public function edit(Request $request)
    {
        $userId = $request->path->id;
        $user = new User(false);
        $user->findById($userId);
        return view('default', 'emsl/useredit', ['user' => $user, 'create' => false, 'postTo' => '/user/edit/' . $user->id]);
    }

    public function create(Request $request)
    {
        
        $user = new User(false);
        $user->id = 0;
        $user->roles = [];
        return view('default', 'emsl/useredit', ['user' => $user, 'create' => true, 'postTo' => '/user/create']);
    }

    public function make(Request $request){

        $keep = [];
        $user = new User(false);
        $user->email = $keep['email'] = $request->iVal('email');
        $user->firstname = $keep['firstname'] = $request->iVal('firstname');
        $user->lastname  = $keep['lastname'] = $request->iVal('lastname');

        $newRoles = [];

        if (!empty($request->iVal('user')))    $newRoles[]  = 'user';
        if (!empty($request->iVal('manager'))) $newRoles[]  = 'manager';
        if (!empty($request->iVal('admin')))   $newRoles[]  = 'admin';

        $user->roles = $newRoles;

        if(!User::exists($user->email)){
            if (!empty($request->input->password_new)) {
                if ($request->input->password_new == $request->input->password_cfm) {
                    $valid = new Validation([
                        'email'         => ['email', 'required'],
                        'password_new'  => ['required', 'secure_password']
                    ]);
    
                    if ($valid->check($request)) {
                        $user->setNewPwd($request->input->password_new);
                        
                        $user->create();
    
    
                        return redirect('/user/edit/' . $user->id, ['success' => 'User successfully created']);
                    } else {
                        return redirect('/user/create/', ['fail_insecure' => 'Insecure password'], $keep);
                    }
                } else {
                    return redirect('/user/create/', ['fail_missmatch' => 'Password confirmation failed'], $keep);
                }
            } else {
                $user->create();
                return redirect('/user/create/', ['success' => 'User successfully updated'], $keep);
            } 
        } else {
            return redirect('/user/create/', ['success' => 'User already exists'], $keep);
        }
       


    }


    public function update(Request $request, $id)
    {

        $user = new User(false);
        $user->findById($id);
        $user->firstname = $request->iVal('firstname');
        $user->lastname  = $request->iVal('lastname');

        $newRoles = [];

        if (!empty($request->iVal('user')))    $newRoles[]  = 'user';
        if (!empty($request->iVal('manager'))) $newRoles[]  = 'manager';
        if (!empty($request->iVal('admin')))   $newRoles[]  = 'admin';

        $user->roles = $newRoles;

        if (!empty($request->input->password_new)) {
            if ($request->input->password_new == $request->input->password_cfm) {

                $valid = new Validation([
                    'password_new' => ['secure_password']
                ]);

                if ($valid->check($request)) {
                    $user->setNewPwd($request->input->password_new);
                    
                    $user->update();


                    return redirect('/user/edit/' . $user->id, ['success' => 'User successfully updated']);
                } else {
                    return redirect('/user/edit/' . $user->id, ['fail_insecure' => 'Insecure password']);
                }
            } else {
                return redirect('/user/edit/' . $user->id, ['fail_missmatch' => 'Password confirmation failed']);
            }
        } else {
            $user->update();
            return redirect('/user/edit/' . $user->id, ['success' => 'User successfully updated']);
        }
    }


    public function myAccount(Request $request)
    {
        $user = new User();
        return view('default', 'emsl/myaccount', ['user' => $user]);
    }

    public function updateMyAccount(Request $request)
    {
        $user = new User();
        $user->firstname = $request->iVal('firstname');
        $user->lastname  = $request->iVal('lastname');

        if (!empty($request->input->password_new)) {
            if ($user->verifyPwd($request->input->password_old)) {
                if ($request->input->password_new == $request->input->password_cfm) {

                    $valid = new Validation([
                        'password_new' => ['secure_password']
                    ]);

                    if ($valid->check($request)) {
                        $user->setNewPwd($request->input->password_new);
                        $user->update();
                        return redirect('/user/account', ['success' => 'User successfully updated']);
                    } else {
                        return redirect('/user/account', ['fail_insecure' => 'Insecure password']);
                    }
                } else {
                    return redirect('/user/account', ['fail_missmatch' => 'Password confirmation failed']);
                }
            } else {
                return redirect('/user/account', ['fail_old_password' => 'Wrong current password']);
            }
        } else {
            $user->update();
            return redirect('/user/account', ['success' => 'User successfully updated']);
        }
    }
}
