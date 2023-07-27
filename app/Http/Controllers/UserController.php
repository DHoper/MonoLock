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
    /**
     * Display a listing of the resource.
     *
     * @OA\Post(
     *     tags={"login"},
     *     path="/api/login",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="mail", type="string"),
     *                 @OA\Property(property="password", type="string"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     */
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

    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     tags={"logout"},
     *     path="/api/logout",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     */
    public function logout(Request $request)
    {
        $Token = $request->header('token');
        $user = User::where("remember_token", $Token);
        if ($user->first() == null) {
            return response()->json("token not found", 400);
        } else {
            $user->update(["remember_token" => null, "token_expire_time" => null]);
            return response()->json("success", 200);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     tags={"user"},
     *     path="/api/user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     *
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $json = $request->all();
        $query = User::where("permission", 1);
        if (isset($json['has_lock'])) {
            if ($json['has_lock']) {
                return response()->json($query->with('Locker')->has('Locker')->get());
            }
            else {
                return response()->json($query->doesnthave('Locker')->get());
            }
        }
        return response()->json($query->with('Locker')->get());
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
     * Display a listing of the resource.
     *
     * @OA\Post(
     *     tags={"user"},
     *     path="/api/user",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="lockerNo", type="string"),
     *                 @OA\Property(property="mail", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="cardId", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     *
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
                'name' => 'required|max:40',
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
                $response = ['message' => 'getRepeatData:' . $e->getMessage()];
                $httpstatus = 411;
            }
        } else {
            $response = "此置物櫃已被使用";
            $httpstatus = 400;
        }
        return response()->json($response, $httpstatus);
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
     * Display a listing of the resource.
     *
     * @OA\Patch(
     *     tags={"user"},
     *     path="/api/user/{id}",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="User ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="mail", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="cardId", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     *
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
                'mail' => 'required|unique:users|email:rfc|max:80',
                'name' => 'required|max:40',
                'cardId' => 'required|unique:users|digits_between:0,20',
                'phone' => 'required|unique:users|digits_between:0,20',
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
        return response()->json($response, $httpstatus);
    }

    /**
     * Display a listing of the resource.
     *
     * @OA\Delete(
     *     tags={"admin"},
     *     path="/api/admin/{id}",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="User ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     * 
     * Display a listing of the resource.
     *
     * @OA\Delete(
     *     tags={"user"},
     *     path="/api/user/{id}",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="User ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer"),
     *     ),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     * )
     *
     * @return AnonymousResourceCollection
     *
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
            Record::where('userId', $id)->delete();
            $user->delete();
            $response = "success";
            $httpstatus = 200;
        } catch (\Exception $e) {
            $response = $e->getMessage();
            $httpstatus = 400;
        }
        return response()->json($response, $httpstatus);
    }
}
