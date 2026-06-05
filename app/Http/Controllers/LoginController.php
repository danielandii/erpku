<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function viewLogin() {
    	if(\Auth::check()){
			return redirect('/dashboard');
		}

        return view('login');
    }

    public function login(Request $request) {
	    $this->validate($request, [
	        'username' => 'required',
	        'password' => 'required',
	        ]);

	    if (\Auth::attempt([
	        'username' => $request->username,
	        'password' => $request->password])
	    ){
	    	return redirect('/dashboard');
	        
	    }
	    return redirect('/login')->with('error', 'Invalid Username or Password');
    }

    public function logout()
    {
	    if(\Auth::check())
	    {
	        \Auth::logout();
	        $request->session()->invalidate();
	    }
	    return  redirect('/login');
    }

    public function dashboard()
    {
        return view('dashboard');
    }
}
