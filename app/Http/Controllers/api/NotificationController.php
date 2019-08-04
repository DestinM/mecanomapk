<?php

namespace App\Http\Controllers\api;

use App\Models\Garage;
use App\Models\Location;
use App\Models\Mechanic;
use App\Models\Notification;
use App\Models\RequestEmergency;
use App\Models\Vehicle;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;


class NotificationController extends Controller
{

    public $successStatus = 200;

    public function getAllNotif(Request $request) {


        $notifications = Notification::where('recipient_id',$request->id)->orderByDesc('created_at')->get();


        foreach ($notifications as $notification){
            $notification->process_fail = $notification->request_emergency->process_fail;
            $notification->process_success = $notification->request_emergency->process_success;
            $notification->is_rate = $notification->request_emergency->is_rate;
            $notification->mechanic_user_id = $notification->request_emergency->mechanic_user_id;
            $notification->driver_user_id = $notification->request_emergency->driver_user_id;

            $delay = $notification->delay;
            $date_start = Carbon::parse($notification->created_at);
            $date_now =  Carbon::now();


            $minutes = 0;

            if (strpos($delay, 'hr') !== false) {
                $delay = substr($delay,0,-3);
                $minutes = (int)$delay * 60;
            }
            if (strpos($delay, 'min') !== false) {
                $delay = substr($delay,0,-4);
                $minutes = (int)$delay;
            }
            $date_start->addMinutes( $minutes);


            if( $date_start->greaterThan($date_now))
                $notification->end_time = false;

            else
                $notification->end_time = true;


        }

        return response()->json($notifications);
    }

    public function getAllHisto(Request $request) {

        if($request->isMechanic == 0)
            $notifications = Notification::where('recipient_id', '!=' ,$request->id)->orderByDesc('created_at')->get()->all();
        else
            $notifications = Notification::where('recipient_id',$request->id)->orderByDesc('created_at')->get()->all();

        foreach ($notifications as $notification){
            $notification->process_fail = $notification->request_emergency->process_fail;
            $notification->process_success = $notification->request_emergency->process_success;
            $notification->is_rate = $notification->request_emergency->is_rate;
            $notification->mechanic_user_id = $notification->request_emergency->mechanic_user_id;
            $notification->driver_user_id = $notification->request_emergency->driver_user_id;
            $notification->mechanic_name = User::find($notification->request_emergency->mechanic_user_id)->name;


        }

        return response()->json($notifications);
    }

    public function getNotifInfo(Request $request) {


        $notif_id = $request->notif_id;


        $notification =  Notification::find($notif_id);

        $notification->mechanic_name = User::find($notification->request_emergency->mechanic_user_id)->email;
        $notification->driver_name = User::find($notification->request_emergency->driver_user_id)->email;
        $user =  User::find($notification->request_emergency->mechanic_user_id);

        $mechanic = Mechanic::where("user_id",$user->id)->first();

        $garage =  Garage::where("mechanic_id",$mechanic->id)->first();
        $notification->garage_name = $garage->name;

        $notification->garage_address = $garage->addresse;
        $notificationInfos = User::find($notification->request_emergency->driver_user_id);


        $notificationInfos->addHidden(["password","token"]);
        $requestEmergency = RequestEmergency::find($notification->request_emergency_id);
        $notification->process_success = $requestEmergency->process_success;
        $notification->process_fail = $requestEmergency->process_fail;
        $notification->mechanic_user_id = $requestEmergency->mechanic_user_id;
        $notification->driver_user_id = $requestEmergency->driver_user_id;

        $vehiculeDetail = Vehicle::find($requestEmergency->vehicule_id);
        $location = Location::find($requestEmergency->location_id);


        $arrayDV  = array();
        $arrayDV["detailVehicule"] = $vehiculeDetail  ;
        $arrayDV["remainingTime"] = $this->remainingTime($notification) ;

        $arrayDV["trouble"] =  $requestEmergency->trouble ;
        $arrayLoc = array("locations" =>$location) ;
        $arrayNotif = array_merge($arrayDV, $arrayLoc,$notification->toArray());

        $arrayNotification = array("notifications" =>$arrayNotif) ;



        $arrayAllData = array_merge($arrayNotification, $notificationInfos->toArray() );




        return response()->json($arrayAllData);
    }

    public function remainingTime($notification)
    {
        $delay = $notification->delay;
        $date_start = Carbon::parse($notification->created_at);
        $date_now = Carbon::now();


        $minutes = 0;
        $seconds_total = 0;

        if (strpos($delay, 'hr') !== false) {
            $delay = substr($delay, 0, -3);
            $minutes = (int)$delay * 60;
            $seconds_total = $minutes * 60;
        }
        if (strpos($delay, 'min') !== false) {
            $delay = substr($delay, 0, -4);
            $minutes = (int)$delay;
            $seconds_total = $minutes * 60;

        }
        $date_start->addMinutes($minutes);


        if ($date_start->greaterThan($date_now)) {
            $date_diff = $date_start->diff($date_now);
            $minutes = $date_diff->days * 24 * 60;
            $minutes += $date_diff->h * 60;
            $minutes += $date_diff->i;
            $seconds_remaining = $date_diff->s + ($minutes * 60);
            $arrayAllData = [
                "seconds_remaining" => $seconds_remaining,
                "seconds_total" => $seconds_total,
                "end" => false
            ];
            return $arrayAllData;

        } else {
            $arrayAllData = [
                "seconds" => 0,
                "end" => true
            ];
            return $arrayAllData;
        }
    }
}
