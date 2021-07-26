<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Loans;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Repayments;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Validator;
use DB;

// original_payment
// duration
// interest_rate
// arrangement_fee
// pay_per_month
// final_payment
// start_date
// end_date
class LoansController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $user = auth("api")->user();
        $list = Loans::where("user_id", $user->id)->get();
        return response()->json([
            "status" => true,
            "data" => $list
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = $this->validateInput($request);

            if ($validate[0]) {
                $user = auth("api")->user();
                $loan = new Loans();
                $loan->original_payment = data_get($request->all(), "original_payment");
                $loan->duration = data_get($request->all(), "duration");
                $loan->interest_rate = data_get($request->all(), "interest_rate");
                $loan->arrangement_fee = data_get($request->all(), "arrangement_fee");
                $loan->start_date = date("Y-m-d", strtotime(data_get($request->all(), "start_date")));
                $interest_rate_total = $loan->interest_rate * $loan->duration;
                $loan->final_payment = round($loan->original_payment * (1 + $interest_rate_total));
                $loan->left_over = $loan->final_payment;
                $loan->end_date = date("Y-m-d", strtotime($loan->start_date. "+ ".$loan->duration." months" ));
                $loan->pay_per_month = round($loan->final_payment / $loan->duration);
                $loan->user_id = $user->id;
                $loan->save();
                DB::commit();
                return response()->json([
                    "status" => true,
                    "data" => $loan
                ]);
            } 
            return $validate[1];
        } catch (\Exception $e) {
            DB::rollback();
            dd($e->getMessage());
            return response()->json(["status" => false, "message" => "SERVER_ERROR"]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = auth("api")->user();
        $loan = Loans::where("user_id", $user->id)->where("id", $id)->first();
        return response()->json([
            "status" => true,
            "data" => $loan
        ]);
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
        try {
            DB::beginTransaction();
            $userId = auth("api")->user()->id;
            $loan = Loans::where("id", $id)->where("user_id", $userId)->first();

            if (!$loan) return response()->json(["status" => false, "message" => "LOAN_NOT_FOUND"]);

            if (data_get($request->all(), "userId")) {
                return $this->updateUserIdOfLoans($id, data_get($request->all(), "userId"), $loan);
            }

            $repayment = Repayments::where("loan_id", $loan->id)->get();

            if (!$repayment || count($repayment)) return response()->json(["status" => false, "message" => "LOAN_IS_PAYING"]);

            $validate = $this->validateInput($request, "update");

            if ($validate[0]) {
                $user = auth("api")->user();
                $loan->original_payment = data_get($request->all(), "original_payment");
                $loan->duration = data_get($request->all(), "duration");
                $loan->interest_rate = data_get($request->all(), "interest_rate");
                $loan->arrangement_fee = data_get($request->all(), "arrangement_fee");
                $loan->start_date = date("Y-m-d", strtotime(data_get($request->all(), "start_date")));
                $interest_rate_total = $loan->interest_rate * $loan->duration;
                $loan->final_payment = round($loan->original_payment * (1 + $interest_rate_total));
                $loan->left_over = $loan->final_payment;
                $loan->end_date = date("Y-m-d", strtotime($loan->start_date. "+ ".$loan->duration." months" ));
                $loan->pay_per_month = round($loan->final_payment / $loan->duration);
                $loan->user_id = $user->id;
                $loan->save();
                DB::commit();
                return response()->json([
                    "status" => true,
                    "data" => $loan
                ]);
            } 
            return $validate[1];
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["status" => false, "message" => "SERVER_ERROR"]);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $userId = auth("api")->user()->id;
        $loan = Loans::where("id", $id)->where("user_id", $userId)->first();

        if (!$loan) return response()->json(["status" => false, "message" => "LOAN_NOT_FOUND"]);

        $repayment = Repayments::where("loan_id", $loan->id)->get();

        if (!$repayment || count($repayment)) return response()->json(["status" => false, "message" => "LOAN_IS_PAYING"]);

        $loan->delete();

        return response()->json(['status' => true]);
    }

    public function validateInput($request) {
        Validator::extend('greaterThanZero', function ($attribute, $value, $parameters, $validator) {

            if ($attribute === "duration") {
                return $value > 0;
            }

            return $value >= 0;
        });

        Validator::extend('checkDate', function ($attribute, $value, $parameters, $validator) {

            try {
                $date = strtotime($value);

                if ($date) {
                    return true;
                }
                return false;
            } catch (\Exception $e) {
                return false;
            }

        });

        $rules = [
            "original_payment" => "bail|required|numeric|greaterThanZero",
            "duration" => "bail|required|integer|greaterThanZero",
            "interest_rate" => "bail|required|numeric|greaterThanZero",
            "arrangement_fee" => "bail|required|numeric|greaterThanZero",
            "start_date" => "bail|required|checkDate"
        ];

        $messages = [
            "required"                      => "Không được bỏ trống",
            "numeric"                       => "Phải là số",
            "integer"                       => "Phải là số nguyên",
            "duration.greater_than_zero"    => "Phải lớn hơn 0",
            "greater_than_zero"             => "Phải lớn hơn hoặc bằng 0",
            "check_date"                    => "Sai định dạng ngày tháng"
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->passes()) {
            return [true];
        }
        return [false, response()->json(['error' => $validator->errors()])];
    }

    public function updateUserIdOfLoans($loanId, $changedUserId, $loan) {
        $loan->user_id = $changedUserId;
        $loan->save();
        return response()->json(['status' => true]);
    }
}
