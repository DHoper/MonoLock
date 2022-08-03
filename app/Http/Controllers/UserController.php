<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Locker;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function addAdmin(Request $request)
    {
        $response = "";
        $httpstatus = 204;
        $request["password_confirmation"] = $request["confirm"];
        $validator = Validator::make(
            $request->all(),
            [
                'mail' => 'required|unique:users|email:rfc|max:80',
                'name' => 'required|unique:users|max:40',
                'password' => 'required|confirmed',
            ],
        );
        if ($validator->fails()) {
            $response = $validator->errors();
            $httpstatus = 400;
        }
        try {
            $newUser = new user();
            $newUser->mail = $request["mail"];
            $newUser->name = $request["name"];
            $newUser->password = Hash::make($request["password"]);
            $newUser->permission = 0;
            $newUser->save();
            $response = "success";
            $httpstatus = 200;
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $httpstatus = 500;
        }
        return response($response, $httpstatus);
    }

    public function showAdmin(Request $request)
    {
        $admins = User::where("permission", 0)->get(["id", "name", "mail", "permission"]);
        return response($admins, 200);
    }

    public function updateAdmin(Request $request, $id)
    {
        $response = "";
        $httpstatus = 204;
        $request["id"] = $id;
        $request["password_confirmation"] = $request["confirm"];
        $validator = Validator::make(
            $request->all(),
            [
                'id' => [
                    'required',
                    Rule::exists('users')->where(function ($query) {
                        return $query->where('permission', 0);
                    }),
                ],
                'password' => 'required|confirmed',
            ],
        );
        if ($validator->fails()) {
            $response = $validator->errors();
            $httpstatus = 400;
        }
        try {
            $user = User::where('id', $id);
            $user->update([
                "password" => Hash::make($request["password"])
            ]);
            $response = $user->first(['id', 'name', 'mail']);
            $httpstatus = 200;
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $httpstatus = 500;
        }
        return response($response, $httpstatus);
    }

    public function login(Request $request)
    {
        $response = "";
        $httpstatus = 204;
        $credentials = $request->only('mail', 'password');

        //先確認user資訊是否正確
        if (Auth::attempt($credentials)) {
            do {
                //建立隨機亂碼
                $loginToken = Str::random(60);
                $checkTokenExist = User::where('remember_token', '=', $loginToken)
                    ->first();
            } while ($checkTokenExist);
            //建立token並寫入使用時間
            $user = User::where('mail', '=', $request["mail"])->first();
            $user->remember_token =  $loginToken;
            $user->token_expire_time = date('Y-m-d H:i:s', time() + 60 * 60);
            $user->save();
            $response = array(
                "name" => $user->name,
                "permission" => $user->permission,
                "token" => $user->remember_token,
                "expire_time" => $user->token_expire_time
            );
            $httpstatus = 200;
        } else {
            //user not exist or input infomation error
            $response = "帳號或密碼錯誤";
            $httpstatus = 400;
        }
        return response()->json(['message' => $response], $httpstatus);
    }

    public function logout(Request $request)
    {
        $Token = $request->header('token');
        $user = User::where("remember_token", $Token);
        if ($user->first() == null) {
            return response("token not found", 400);
        } else {
            $user->update(["remember_token" => null, "token_expire_time" => null]);
            return response("success", 200);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return User::get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = "";
        $httpstatus = 204;
        if (preg_match("/^09\d{8}$/", $request['phone'])) {
            $request['phone'] = "886" . ltrim($request['phone'], "0");
        }
        $validator = Validator::make(
            $request->all(),
            [
                'lockerNo' => 'required|exists:lockers',
                'mail' => 'required|unique:users|email:rfc|max:80',
                'name' => 'required|unique:users|max:40',
                'cardId' => 'required|unique:users|digits_between:0,20',
                'phone' => 'required|unique:users|digits_between:0,20',
            ],
        );
        if ($validator->fails()) {
            $response = $validator->errors();
            $httpstatus = 400;
        }
        $locker = Locker::where("lockerNo", "=", $request["lockerNo"]);
        if ($locker->first()->userId == null) {
            try {
                $newUser = new user();
                $newUser->mail = $request["mail"];
                $newUser->name = $request["name"];
                $newUser->password = Hash::make($request["password"]);
                $newUser->phone = $request["phone"];
                $newUser->cardId = $request['cardId'];
                $newUser->save();

                $locker->update(["userId" => $newUser->id]);
                return response()->json(['id' => $newUser->id], 200);
            } catch (\Exception $e) {
                $response = $e->getMessage();
                $httpstatus = 400;
            }
        } else {
            $response = "此置物櫃已被使用";
            $httpstatus = 400;
        }
        return response($response, $httpstatus);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $response = "";
        $httpstatus = 204;
        $request["id"] = $id;
        if (preg_match("/^09\d{8}$/", $request['phone'])) {
            $request['phone'] = "886" . ltrim($request['phone'], "0");
        }
        $validator = Validator::make(
            $request->all(),
            [
                'id' => [
                    'required',
                    Rule::exists('users')->where(function ($query) {
                        return $query->where('permission', 1);
                    }),
                ],
                'mail' => ['required', 'email:rfc', 'max:80', Rule::unique('users')->ignore($id)],
                'name' => ['required', 'max:40', Rule::unique('users')->ignore($id)],
                'cardId' => ['required', 'numeric', 'digits_between:6,20', Rule::unique('users')->ignore($id)],
                'phone' => ['required', 'numeric', 'digits_between:1,20', Rule::unique('users')->ignore($id)],
            ],
        );
        if ($validator->fails()) {
            $response = $validator->errors();
            $httpstatus = 400;
        }
        $user = User::where('id', $id);
        try {
            $user->update([
                'mail' => $request['mail'],
                'name' => $request['name'],
                'cardId' => $request['cardId'],
                'phone' => $request['phone']
            ]);
            $response = $user->first(['id', 'name', 'mail', 'phone', 'cardId']);
            $httpstatus = 200;
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $httpstatus = 400;
        }
        return response($response, $httpstatus);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $response = "";
        $httpstatus = 204;
        $request["id"] = $id;
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:users'
            ],
        );
        if ($validator->fails()) {
            $response = $validator->errors();
            $httpstatus = 400;
        }
        try {
            $user = User::where('id', $id);
            Locker::where('userId', $id)->update(['userId' => NULL]);
            Record::where('userId', $id)->update(['userId' => NULL]);
            $user->delete();
            $response = "success";
            $httpstatus = 200;
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $httpstatus = 400;
        }
        return response($response, $httpstatus);
    }
}
