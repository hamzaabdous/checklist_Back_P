<?php

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fonction\Models\Fonction;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use \stdClass;
use App\Modules\ProfileGroup\Models\ProfileGroup;
use Carbon\Carbon;


class UserController extends Controller
{

    public function index()
    {

        $users = User::with('fonction.department')
            ->with('profileGroups')
            ->get();

        return [
            "payload" => $users,
            "status" => "200_00"
        ];
    }

    public function get($id)
    {
        $user = User::find($id);
        if (!$user) {
            return [
                "payload" => "The searched row does not exist !",
                "status" => "404_1"
            ];
        } else {
            $user->fonction = $user->fonction;
            $user->fonction->department = $user->fonction->department;
            $user->profileGroups = $user->profileGroups()->get();
            return [
                "payload" => $user,
                "status" => "200_1"
            ];
        }
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "username" => "required|string|unique:users,username",
            "fonction_id" => "required",
        ]);
        if ($validator->fails()) {
            return [
                "payload" => $validator->errors(),
                "status" => "406_2"
            ];
        }
        $fonction = Fonction::find($request->fonction_id);
        if (!$fonction) {
            return [
                "payload" => "fonction is not exist !",
                "status" => "fonction_404",
            ];
        }
        $user = User::make($request->all());
        // $user->password="Initial123";
        $user->save();
        $user->fonction = $user->fonction;
        $user->fonction->department = $user->fonction->department;

        return [
            "payload" => $user,
            "status" => "200"
        ];
    }

    public function addArrayUsers(Request $request)
    {

        for ($i = 0; $i < $request->countUsers; $i++) {
            $fonction = Fonction::where("name", '=', $request->users[$i]["fonction"])->first();

            if (!$fonction) {
                return [
                    "payload" => "fonction is not exist !",
                    "status" => "fonction_404",
                ];
            }
            $user = new User;
            $user->username = $request->users[$i]["username"];
            $user->lastName = $request->users[$i]["lastName"];
            $user->firstName = $request->users[$i]["firstName"];
            $user->company = $request->users[$i]["company"];
            if (isset($request->users[$i]["email"])) {
                $user->email = $request->users[$i]["email"];
            }
            if (isset($request->users[$i]["phoneNumber"])) {
                $user->phoneNumber = $request->users[$i]["phoneNumber"];
            }
            $user->password = $request->users[$i]["password"];

            $fonctionsListID = array("ADMIN IT" => "1", "TECHNICIEN IT" => "2", "SUPERVISOR IT" => "3", "ADMIN TECHNIQUE" => "4", "TECHNICIEN TECHNIQUE" => "5", "SUPERVISOR TECHNIQUE" => "6", "DRIVER" => "7", "FOREMAN" => "8","SHIFT MANAGER"=>"9","CHECKER"=>"10");

            $user->fonction_id = $fonctionsListID[$request->users[$i]["fonction"]] ;

            $user->save();
            $user->fonction = $user->fonction;
            $user->fonction->department = $user->fonction->department;

            // add profile groups into  user

            if (array_key_exists("profilegroup", $request->users[$i])) {

                $profilegroupList = explode(',', $request->users[$i]["profilegroup"]);

                for ($j = 0; $j < count($profilegroupList); $j++) {

                    $profileGroup = ProfileGroup::where("name", '=', $profilegroupList[$j])->first();
                    //  $profileGroup=DB::table('profile_groups')->where('name', $profilegroupList[$j])->get();
                    if (!$profileGroup) {
                        return [
                            "payload" => "Profile Group is not exist !",
                            "status" => "profileGroup_404",
                        ];
                    }
                    $profileGroup->users()->attach($user);
                    $user->profileGroups = $user->profileGroups;
                }
            } else {
            }
        }


        return [
            "payload" => "done",
            "status" => "200"
        ];
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id" => "required",
            "fonction_id" => "required",
        ]);
        if ($validator->fails()) {
            return [
                "payload" => $validator->errors(),
                "status" => "406_2"
            ];
        }
        $user = User::find($request->id);
        if (!$user) {
            return [
                "payload" => "The searched row does not exist !",
                "status" => "404_3"
            ];
        }
        if ($request->username != $user->username) {
            if (User::where("username", $request->username)->count() > 0)
                return [
                    "payload" => "The user has been already taken ! ",
                    "status" => "406_2"
                ];
        }

        $fonction = Fonction::find($request->fonction_id);
        if (!$fonction) {
            return [
                "payload" => "fonction is not exist !",
                "status" => "fonction_404",
            ];
        }

        $user->username = $request->username;
        $user->lastName = $request->lastName;
        $user->firstName = $request->firstName;
        $user->email = $request->email;
        $user->phoneNumber = $request->phoneNumber;
        $user->fonction_id = $request->fonction_id;

        $user->save();
        $user->fonction = $user->fonction;
        $user->fonction->department = $user->fonction->department;

        return [
            "payload" => $user,
            "status" => "200"
        ];
    }

    public function delete(Request $request)
    {
        $user = User::find($request->id);
        if (!$user) {
            return [
                "payload" => "The searched row does not exist !",
                "status" => "404_4"
            ];
        } else {
            $user->delete();
            return [
                "payload" => "Deleted successfully",
                "status" => "200_4"
            ];
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "username" => "required|string",
            "password" => "required|string",
        ]);
        if ($validator->fails()) {
            return [
                "payload" => $validator->errors(),
                "status" => "406"
            ];
        }
        $user = User::where('username', $request->username)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return [
                "payload" => "Incorrect username or password !",
                "status" => "401",
                "user" => $user,

            ];
        }
        $token = $user->createToken($user->fonction->department->name . $user->fonction->name, [$user->fonction->department->name . $user->fonction->name])->plainTextToken;
        $user->fonction = $user->fonction;
        $user->fonction->department = $user->fonction->department;
        $user->profileGroups = $user->profileGroups()->with('department')->get();

        $dateNow = Carbon::now()->format('Y/m/d H:i');
        // $dateLogin=Carbon::createFromFormat('Y-m-d H', $dateNow)->toDateTimeString();
        $response = [
            'user' => $user,
            'token' => $token,
            'dateLogin' => $dateNow,
            'dateLogout' => Carbon::now()->addHour(8)->format('Y/m/d H:i')

        ];

        return [
            "payload" => $response,
            "status" => "200"
        ];
    }

    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();

        return [
            "payload" => "User Logged out successfully !",
            "status" => "200"
        ];
    }

    public function changePassword(Request $request)
    {

        $validator = Validator::make($request->all(), [
            "id" => "required",
            "password" => "required|string",

        ]);
        if ($validator->fails()) {
            return [
                "payload" => $validator->errors(),
                "status" => "406"
            ];
        }
        $user = User::find($request->id);
        if (!$user) {
            return [
                "payload" => "The searched row does not exist !",
                "status" => "404"
            ];
        }


        $user->password = $request->password;

        $user->save();
        return [
            "payload" => $user,
            "status" => "200"
        ];
    }
    public function resetPassword(Request $request)
    {


        $user = User::find($request->id);
        if (!$user) {
            return [
                "payload" => "The searched row does not exist !",
                "status" => "404"
            ];
        }

        $user->password = "Initial123";

        $user->save();
        return [
            "payload" => $user,
            "status" => "200"
        ];
    }
}
