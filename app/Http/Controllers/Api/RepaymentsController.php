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

class RepaymentsController extends Controller
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
        $list = Repayments::with("loan:id,user_id")->whereHas("loan", function($query) use($user) {
            $query->where("user_id", $user->id);
        })->get();
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
        //
        $user = auth("api")->user();
        $loanId = data_get($request->all(), "loanId");

        if (!$loanId) return response()->json(["status" => false, "message" => "SELECT_LOAN_TO_PAY"]);

        $loan = Loans::where("id", $loanId)->where("user_id", $user->id)->first();

        if (!$loan) return response()->json(["status" => false, "message" => "LOAN_NOT_FOUND"]);

        if ($loan->left_over === 0) return response()->json(["status" => false, "message" => "LOAN_IS_ALREADY_PAID"]);

        $oldRepayment = Repayments::where("loan_id", $loanId)->orderBy("created_at", "DESC")->first();

        if ($oldRepayment) {
            $lastPayMonth = date("Y-m", strtotime($oldRepayment->created_at));
            $currentMonth = date("Y-m");

            if ($currentMonth <= $lastPayMonth) return response()->json(['status' => false, 'message' => 'PAID_THIS_MONTH']);
        }

        try {
            DB::beginTransaction();
            $validate = $this->validateInput($request, $loan);

            if ($validate[0]) {
                $user = auth("api")->user();
                $repayment = new Repayments();
                $repayment->payment = data_get($request->all(), "payment");
                $repayment->loan_id = $loanId;
                $loan->left_over = $loan->left_over - $repayment->payment;
                $loan->save();
                $repayment->save();
                DB::commit();
                return response()->json(['status' => true]);
            } 
            return $validate[1];
        } catch (\Exception $e) {
            DB::rollback();
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
        //
        $user = auth("api")->user();
        $repayment = Repayments::with("loan:id,user_id")->where("id", $id)->whereHas("loan", function($query) use($user) {
            $query->where("user_id", $user->id);
        })->first();
        return response()->json([
            "status" => true,
            "data" => $repayment
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
        //
        $user = auth("api")->user();
        $repayment = Repayments::with("loan:id,user_id")->where("id", $id)->whereHas("loan", function($query) use($user) {
            $query->where("user_id", $user->id);
        })->first();

        if (!$repayment) return response()->json(["status" => false, "message" => "REPAYMENT_NOT_FOUND"]);

        $lastPayMonth = date("Y-m", strtotime($repayment->created_at));
        $currentMonth = date("Y-m");

        if ($currentMonth > $lastPayMonth) return response()->json(['status' => false, 'message' => 'NOT_ALLOW_EDIT_OLD_MONTH']);

        $loanId = data_get($request->all(), "loanId");

        if (!$loanId) return response()->json(["status" => false, "message" => "SELECT_LOAN_TO_PAY"]);

        $loan = Loans::where("id", $loanId)->where("user_id", $user->id)->first();

        if (!$loan) return response()->json(["status" => false, "message" => "LOAN_NOT_FOUND"]);

        try {
            DB::beginTransaction();
            $oldPayment = (float)$repayment->payment;
            $validate = $this->validateInput($request, $loan, $oldPayment);

            if ($validate[0]) {
                $user = auth("api")->user();
                $repayment->payment = data_get($request->all(), "payment");
                $repayment->loan_id = $loanId;
                $loan->left_over = $loan->left_over - $repayment->payment + $oldPayment;
                $loan->save();
                $repayment->save();
                DB::commit();
                return response()->json(['status' => true]);
            } 
            return $validate[1];
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["status" => false, "message" => "SERVER_ERROR"]);
        }

    }

    public function validateInput($request, $loan, $oldPayment = 0) {

        Validator::extend('checkMaxPayment', function ($attribute, $value, $parameters, $validator) use($loan, $oldPayment) {
            $left_over = (float)$loan->left_over;
            $left_over = $left_over + (float)$oldPayment;
            $value = (float)$value;
            return $left_over >= $value;
        });

        Validator::extend('checkMinPayment', function ($attribute, $value, $parameters, $validator) use($loan) {
            $pay_per_month = (float)$loan->pay_per_month;
            $value = (float)$value;
            return $pay_per_month < $value;
        });

        Validator::extend('greaterThanZero', function ($attribute, $value, $parameters, $validator) {
            return $value > 0;
        });

        $rules = [
            "payment" => "bail|required|numeric|greaterThanZero|checkMaxPayment|checkMinPayment",
        ];

        $messages = [
            "required"                      => "Không được bỏ trống",
            "numeric"                       => "Phải là số",
            "greater_than_zero"             => "Phải lớn hơn 0",
            "check_max_payment"             => "Số tiền trả vượt hơn số tiền còn lại",
            "check_min_payment"             => "Số tiền trả thấp hơn định mức"
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->passes()) {
            return [true];
        }
        return [false, response()->json(['error' => $validator->errors()])];
    }
}
