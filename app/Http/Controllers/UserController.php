<?php

namespace App\Http\Controllers;

use App\Models\MRole;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Contracts\DataTable;
use Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $results['users'] = User::all();
        return view('users.index', $results);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $results['roles'] = MRole::orderBy('id', 'asc')->get();
        return view('users.create', $results);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required',
            'username'=>'required',
            'password'=>'required',
        ]);

        $data = $request->except(['_token', '_method']);

        if($request->get('password')!=''){
            $data['password'] = bcrypt($request->get('password'));
        }

        User::create($data);

        return redirect('/users')->with('success', 'User saved!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $results['user'] = User::findOrFail($id);
        $results['roles'] = MRole::orderBy('id', 'asc')->get();
        return view('users.edit', $results);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'=>'required',
            'username'=>'required',
        ]);

        $user = User::find($id);
        $data = $request->except(['_token', '_method', 'password']);

        if($request->get('password')!=''){
            $data['password'] = bcrypt($request->get('password'));
        }

        $user->update($data);

        return redirect('/users')->with('success', 'User updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();

        return redirect('/users')->with('success', 'User deleted!');
    }

    public function datatableUser(){
        $users = User::with('roles')->orderBy('updated_at', 'asc')->get();

        return datatables()->of($users)->addIndexColumn()->toJson();
    }

    public function changePass()
    {
        return view('changepass');
    }

    public function changePassSubmit(Request $request, $id)
    {
        $request->validate([
            'old_pass'=>'required',
            'new_pass'=>'required',
            'con_pass'=>'required',
        ]);

        $user = User::find($id);
        if($request->get('new_pass') != $request->get('con_pass')){
            return redirect('/changepass')->with('error', 'Password baru tidak sama dengan konfirmasi password');
        }

        if(Hash::check($request->get('old_pass'), $user->password)){
            $user->password = bcrypt($request->get('new_pass'));
            $user->save();

            return redirect('/changepass')->with('success', 'Password updated!');
        } else {
            return redirect('/changepass')->with('error', 'Password lama salah');
        }
    }
}
