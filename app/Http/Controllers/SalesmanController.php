<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Hash;
use Illuminate\Http\Request;
use App\Models\Salesman;
use App\Models\User;
use App\Models\Order;
use function back;
use function date;
use function flash;
use function redirect;
use function translate;

class SalesmanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request ) {
        $sort_search = NULL;
        $salesman = User::where('user_type', 'salesman')->orderBy('created_at', 'desc');
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $salesman->where(function ( $q ) use ( $sort_search )
            {
                $q->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
            });
        }
        $salesmans = $salesman->paginate(15);
        return view('backend.salesman.salesmans.index', compact('salesmans'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view('backend.salesman.salesmans.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store( Request $request ) {
        if ( User::where('email', $request->email)->first() == NULL )
        {
            $user = new User;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->user_type = "salesman";
            $user->email_verified_at = date('Y-m-d H:m:s');
            $user->password = Hash::make($request->password);
            if ( $user->save() )
            {
                flash(translate('Salesman has been inserted successfully'))->success();
                return redirect()->route('salesmans.index');
            }
        }

        flash(translate('Email already used'))->error();
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show( $id ) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit( $id ) {
        $user = User::findOrFail(decrypt($id));
        return view('backend.salesman.salesmans.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update( Request $request, $id ) {
        $user = User::findOrFail($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        if ( strlen($request->password) > 0 )
        {
            $user->password = Hash::make($request->password);
        }
        if ( $user->save() )
        {
            flash(translate('Salesman has been updated successfully'))->success();
            return redirect()->route('salesmans.index');
        }

        flash(translate('Something went wrong'))->error();
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id ) {
        User::destroy($id);
        flash(translate('Salesman has been deleted successfully'))->success();
        return redirect()->route('salesmans.index');
    }

    public function bulk_salesman_delete( Request $request ) {
        if ( $request->id )
        {
            foreach ( $request->id as $salesman_id )
            {
                $this->destroy($salesman_id);
            }
        }

        return 1;
    }

    public function login( $id ) {
        $user = User::findOrFail(decrypt($id));

        auth()->login($user, true);

        return redirect()->route('dashboard');
    }

    public function ban( $id ) {
        $user = User::findOrFail(decrypt($id));

        if ( $user->banned == 1 )
        {
            $user->banned = 0;
            flash(translate('Salesman UnBanned Successfully'))->success();
        }
        else
        {
            $user->banned = 1;
            flash(translate('Salesman Banned Successfully'))->success();
        }

        $user->save();

        return back();
    }
}
