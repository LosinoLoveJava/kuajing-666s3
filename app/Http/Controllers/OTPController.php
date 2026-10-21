<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OtpConfiguration;

class OTPController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function configure_index()
    {
        return view('otp_systems.configurations.activation');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function credentials_index()
    {
        return view('otp_systems.configurations.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateActivationSettings(Request $request)
    {
        $business_settings = OtpConfiguration::where('type', $request->type)->first();
        if($business_settings!=null){
            $business_settings->value = $request->value;
            $business_settings->save();
        }
        else{
            $business_settings = new OtpConfiguration;
            $business_settings->type = $request->type;
            $business_settings->value = $request->value;
            $business_settings->save();
        }
        return '1';
    }

    /**
     * Update the specified resource in .env
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update_credentials(Request $request)
    {
        foreach ($request->types as $key => $type) {
                $this->overWriteEnvFile($type, $request[$type]);
        }

        flash("Settings updated successfully")->success();
        return back();
    }

    /**
    *.env file overwrite
    */
    public function overWriteEnvFile($type, $val)
    {
        \App\Support\EnvironmentFile::update(app()->environmentFilePath(), [$type => trim((string) $val)]);
    }
}
