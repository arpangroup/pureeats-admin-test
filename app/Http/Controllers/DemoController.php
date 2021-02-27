<?php
namespace App\Http\Controllers;

use App\AcceptDelivery;
use App\Address;
use App\Employee;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function getEmployee(){
        $employees = Employee::all();
         return response()->json($employees);
    }

    public function getEmployeeById($empId){
        $employee = Employee::where('id', $empId)->get();
        return response()->json($employee);
    }
    public function createEmployee(Request $request){
        $employee = new Employee();
        $employee->name = $request->name;
        $employee->email = $request->email;
        $employee->phone = $request->phone;
        $employee->address = $request->address;
        $employee->gender = $request->gender;


        $employee->save();
         return response()->json([
             'success' => true,
             'message' => "Insert successfull",
         ]);

    }

    public function deleteEmployee($empId){
        $employees = Employee::where('id', $empId)->delete();
        return response()->json([
            'success' => true,
            'message' => "Delete Successfull",
        ]);
    }

};
