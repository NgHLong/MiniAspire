<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Validator;


class UserController extends Controller
{
    //
    public function login(Request $request) {

        $validate = $this->validateInput($request);

        if ($validate[0]) {
            $credentials = request(['email', 'password']);

            if (! $token = auth("api")->attempt($credentials)) {
                return response()->json(['error' => [
                    "input"=> ["Thông tin đăng nhập không chính xác"]
                ]], 401);
            }

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth("api")->factory()->getTTL() * 60
            ]);
        }
        return $validate[1];
    }

    public function register(Request $request) {

        $validate = $this->validateInput($request, "register");

        if ($validate[0]) {
            $user = new User();
            $email = data_get($request->all(), "email");
            $name = data_get($request->all(), "name");
            $password = Hash::make(data_get($request->all(), "password"));
            $user->email = $email;
            $user->password = $password;
            $user->name = $name;
            $user->save();
            $response = ["message" => "Success"];
            return response($response, 200);
        } 
        return $validate[1];
    }

    public function updateUser(Request $request) {

        $validate = $this->validateInput($request, "update");

        if ($validate[0]) {
            $user = auth("api")->user();
            $email = data_get($request->all(), "email");
            $name = data_get($request->all(), "name");
            $password = data_get($request->all(), "password") ? Hash::make(data_get($request->all(), "password")) : "";

            if ($email !== $user->email) {
                $user->email = $email;
            }

            if ($password && $password !== $user->password) {
                $user->password = $password;
            }

            if ($name !== $user->name) {
                $user->name = $name;
            }

            $user->save();
            $response = ["message" => "Success"];
            return response($response, 200);
        } 
        return $validate[1];
    }

    public function logout()
    {
        auth("api")->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function user()
    {
        return response()->json(auth("api")->user());
    }

    public function validateInput($request, $type = "") {
        $rules = [
            'email' => 'bail|required',
            'password' => 'bail|required|min:4|max:20',
        ];

        if ($type === 'register' || $type === 'update') {

            Validator::extend('uniqueEmail', function ($attribute, $value, $parameters, $validator) use($type) {
                $user = User::where("email", mb_strtolower($value));

                if ($type === 'update') {
                    $currentUser = auth("api")->user();
                    $user = $user->where("id", "!=", $currentUser->id);
                }
                $user = $user->get()->toArray();

                if (count($user) > 0) {
                    return false;
                }
                return true;
            });
            $rules = [
                'email' => 'bail|required|uniqueEmail'
            ];
        }

      
        $messages = [
            'required'  => 'Không được bỏ trống',
            'min'       => 'Có ít nhất 4 kí tự',
            'max'       => 'Không quá 20 kí tự'
        ];

        if ($type === 'register' || $type === 'update') {
            $rules['name'] = 'bail|required|min:4|max:20';
            $messages['unique_email'] = 'Đã được sử dụng';
        }
      
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->passes()) {
            return [true];
        }
        
        return [false, response()->json(['error' => $validator->errors()])];
    }

}
