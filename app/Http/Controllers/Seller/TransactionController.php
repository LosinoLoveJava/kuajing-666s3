<?php
namespace App\Http\Controllers\Seller;

use App\Http\Requests\SellerProfileRequest;
use App\Models\User;
use Auth;
use Hash;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        return view('seller.transaction.index',compact('user'));
    }
    
    public function find()
    {

    }
    
    public function update()
    {
        $user = Auth::user();
        $userModel = User::findOrFail($user->id);  
        if ($_POST["type"] == 1) {
     
            if ($user->tpwd) {
                 flash(translate('You have set a trading password .'))->error();
                 return back();
            }
            // 设置密码
            if (!$_POST["password"]) {
                flash(translate('password empty.'))->error();
                return back();
            }
            if (!$_POST["confirm_password"]) {
                flash(translate('confirm password empty.'))->error();
                return back();
            }
            if ($_POST["confirm_password"] != $_POST["password"]) {
                flash(translate('Password does not match.'))->error();
                return back();
            }
                       $reg = "/^[0-9]{6}$/";
            $result = preg_match($reg, $_POST["password"]);

            if (!$result) {
                flash(translate('The transaction password is a six-digit pure number .'))->error();
                return back();
            }
            $pwd = md5($_POST["password"]);
            $userModel->tpwd = $pwd;
            $userModel->save();
            flash(translate('Your password has been updated successfully!'))->success();
            return back();
        } else {
            if (!$_POST["spwd"]) {
                flash(translate('original password empty.'))->error();
                return back();
            }
            if (md5($_POST["spwd"]) != $user->tpwd) {
                flash(translate('original password error.'))->error();
                return back();
            }
            // 设置密码
            if (!$_POST["password"]) {
                flash(translate('password empty.'))->error();
                return back();
            }
            if (!$_POST["confirm_password"]) {
                flash(translate('confirm password empty.'))->error();
                return back();
            }
            if ($_POST["confirm_password"] != $_POST["password"]) {
                flash(translate('Password does not match.'))->error();
                return back();
            }
            
            $reg = "/^[0-9]{6}$/";
            $result = preg_match($reg, $_POST["password"]);

            if (!$result) {
                flash(translate('The transaction password is a six-digit pure number .'))->error();
                return back();
            }
            $pwd = md5($_POST["password"]);
            $userModel->tpwd = $pwd;
            $userModel->save();
            flash(translate('Your password has been updated successfully!'))->success();
            return back();
        }
        
 

    }

}
