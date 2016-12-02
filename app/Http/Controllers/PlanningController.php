<?php

namespace App\Http\Controllers;

use Auth, DateTime, DB, Session, Redirect, Parser;

use Illuminate\Http\Request;

use App\Http\Requests;

class PlanningController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $planningen = DB::table('plannings')->where('created_at', "!=", new DateTime('today'))->paginate(15);

        $todaysPlanning = DB::table('plannings')->where('created_at', '=', new DateTime('today'))->get();

        if(Auth::user()->rights == "planner") return view('planningen.index', ['planningen' => $planningen, 'todaysPlanning' => $todaysPlanning]);
        return back()->withInput();
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
        $iAmmountDevided = 0;
        $addressFrom = "Sterrenlaan 10, Eindhoven";

        $fullname =  Auth::user()->firstname . " " . Auth::user()->lastname;

        $date = date_format(new DateTime($request->date), 'Y-m-d'). " 00:00:00";
        $midnight = date_format(new DateTime($date), 'Y-m-d'). " 23:59:59";

        $orders = DB::table('orders')->where([['created_at', '>=', $date], ['created_at', '<=', $midnight]])->get();
        $planningId = DB::table('plannings')->max('id') + 1;

        
        $chauffeursCount = DB::table('users')->where('rights', '=', 'chauffeur')->count();
        $ordersForChauffeurs = array_chunk($orders, $chauffeursCount);

       if (count($orders) == 0) {
            Session::flash('message', 'Er zijn geen orders om te plannen.'); 
            Session::flash('alert-class', 'alert-danger');
            return back()->withInput();
        }

        foreach ($ordersForChauffeurs as $ordersForChauffeur) { // 3x
            foreach ($ordersForChauffeur as $orderForChauffeur) { // 3x , 3x, 2x
                foreach ($orders as $order) {
                    $xml = file_get_contents(public_path() . $order->file);
                    $parsedXML = Parser::xml($xml);

                    if ($order->status == "recieved") {
                        $addressTo = $parsedXML['afleveradres']['straat'] . " " . $parsedXML['afleveradres']['huisnr'] . ", " . $parsedXML['afleveradres']['plaats'];
                        $json = file_get_contents("http://maps.googleapis.com/maps/api/distancematrix/json?origins='" . urlencode($addressFrom)  . "'&destinations='" . urlencode($addressTo) . "'&language=en-EN&sensor=false");
                        $decoded = json_decode($json, true);
                        $distanceArray[] = [$order->id, $decoded['rows'][0]['elements'][0]['distance']['value']];
                    }
                }

                for ($i=0; $i < count($distanceArray); $i++) { 
                    $lowest[] = $distanceArray[$i][1];
                }

                foreach ($distanceArray as $distance) {
                    if (min($lowest) == $distance[1]) {
                        DB::table('orders')->where('id', '=', $distance[0])->update(['status' => 'planning']);
                        $iAmmountDevided++;
                        $aDevided[] = [$distance[0], $distance[1]];
                        foreach ($orders as $order) {
                            $xml = file_get_contents(public_path() . $order->file);
                            $parsedXML = Parser::xml($xml);
                            if($order->id == $distance[0]){
                               $addressFrom = $parsedXML['afleveradres']['straat'] . " " . $parsedXML['afleveradres']['huisnr'] . ", " . $parsedXML['afleveradres']['plaats']; 
                            }
                        }
                    }
                }
                unset($distanceArray);
            }
        }
        
        dd($ordersForChauffeurs);

        //checking if selected date exists
        $planningen = DB::table('plannings')->get();
        foreach ($planningen as $planning) {
            if (new DateTime($planning->created_at) == new DateTime($request->date)) {
                Session::flash('message', 'De gekozen datum heeft al een planning.'); 
                Session::flash('alert-class', 'alert-danger');
                return back()->withInput();
            }
        }

        //link all orders to the planning
        foreach ($orders as $order) {
            DB::table('planning_orders')->insert([
                'id' => null, 
                'planning_id' => $planningId, 
                'order_id' => $order->id, 
                'created_at' => new DateTime('now')
            ]);
        }

        DB::table('plannings')->insert([
            'id' => null,
            'created_by' => $fullname,
            'created_at' => new DateTime($request->date)
        ]);
        
        Session::flash('message', 'De planning is successvol aangemaakt.'); 
        Session::flash('alert-class', 'alert-success');

        return Redirect::route('planningen.index');
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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
    }
}
