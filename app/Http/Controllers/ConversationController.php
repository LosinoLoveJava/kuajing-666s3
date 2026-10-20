<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\BusinessSetting;
use App\Models\Message;
use App\Models\Product;
use Auth;
use Mail;
use App\Mail\ConversationMailManager;
use App\Models\ProductQuery;
use Illuminate\Support\Facades\Notification;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
     
        public function check_new_msg()
    {
      
        $uid =  $pid = Auth::user()->id;
        if( $uid )
        {
            $have_no_read = Conversation::where( 'receiver_id',$uid )->where('is_tip',0 )->first();
           
            if( $have_no_read['id'] )
            {
                $c = Conversation::findOrFail( $have_no_read['id'] );
                $c->is_tip = 1;
                $c->save();
                echo json_encode( ['code'=>1, 'msg'=> 'Yes'] );exit;
            }
        }
         echo json_encode( ['code'=>0, 'msg'=> 'No'] );exit;
         
    }
    
    
      public function check_new_reply()
    {
       
        $uid =  $pid = Auth::user()->id;
        if( $uid )
        {
            $list = Conversation::where( 'sender_id',$uid )->get()->toArray();
            $ids= [0];
            foreach($list as $v)
            {
                array_push( $ids, $v['id']);
                
            }
            $message = Message::whereIn('conversation_id', $ids)->where('is_tip',0)->first();
            
             
            
            if( $message['id'] )
            {
                $c = Message::findOrFail( $message['id'] );
                $c->is_tip = 1;
                $c->save();
                echo json_encode( ['code'=>1, 'msg'=> 'Yes'] );exit;
            }
        }
         echo json_encode( ['code'=>0, 'msg'=> 'No'] );exit;
         
    }
    
    
    public function index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()->value == 1) {
            $conversations = Conversation::where('sender_id', Auth::user()->id)->orWhere('receiver_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(5);
            return view('frontend.user.conversations.index', compact('conversations'));
        }
        else {
            flash(translate('Conversation is disabled at this moment'))->warning();
            return back();
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function admin_index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()->value == 1) {
            $conversations = Conversation::orderBy('created_at', 'desc')->get();
            return view('backend.support.conversations.index', compact('conversations'));
        }
        else {
            flash(translate('Conversation is disabled at this moment'))->warning();
            return back();
        }
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
        $user_type = Product::findOrFail($request->product_id)->user->user_type;

        $conversation = new Conversation;
        $conversation->sender_id = Auth::user()->id;
        $conversation->receiver_id = Product::findOrFail($request->product_id)->user->id;
        $conversation->title = $request->title;

        if($conversation->save()) {
            $message = new Message;
            $message->conversation_id = $conversation->id;
            $message->user_id = Auth::user()->id;
            $message->message = $request->message;

            if ($message->save()) {
                $this->send_message_to_seller($conversation, $message, $user_type);
            }
        }

        flash(translate('Message has been sent to seller'))->success();
        return back();
    }

    public function admin_store(Request $request)
    {
        $order_notification = array();
        $order_notification['order_id'] = 0;
        $order_notification['order_code'] = $request->title;
        $order_notification['user_id'] = $request->receiver_id;
        $order_notification['seller_id'] = $request->receiver_id;
        $order_notification['status'] = 'confirmed';
        $users = User::findMany([$request->receiver_id]);
        Notification::send($users, new OrderNotification($order_notification));
        flash(translate('Message has been sent to seller'))->success();
        return back();
    }

    public function salesman_store(Request $request)
    {
        $order_notification = array();
        $order_notification['order_id'] = 0;
        $order_notification['order_code'] = $request->title;
        $order_notification['user_id'] = $request->receiver_id;
        $order_notification['seller_id'] = $request->receiver_id;
        $order_notification['status'] = 'confirmed';
        $users = User::findMany([$request->receiver_id]);
        Notification::send($users, new OrderNotification($order_notification));
        flash(translate('Message has been sent to seller'))->success();
        return back();
    }

    public function send_message_to_seller($conversation, $message, $user_type)
    {
        $array['view'] = 'emails.conversation';
        $array['subject'] = 'Sender:- '.Auth::user()->name;
        $array['from'] = env('MAIL_FROM_ADDRESS');
        $array['content'] = 'Hi! You recieved a message from '.Auth::user()->name.'.';
        $array['sender'] = Auth::user()->name;

        if($user_type == 'admin') {
            $array['link'] = route('conversations.admin_show', encrypt($conversation->id));
        } else {
            $array['link'] = route('conversations.show', encrypt($conversation->id));
        }

        $array['details'] = $message->message;

        try {
            Mail::to($conversation->receiver->email)->queue(new ConversationMailManager($array));
        } catch (\Exception $e) {
            //dd($e->getMessage());
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
        $conversation = Conversation::findOrFail(decrypt($id));
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
        }
        elseif($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('frontend.user.conversations.show', compact('conversation'));
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function refresh(Request $request)
    {
        $conversation = Conversation::findOrFail(decrypt($request->id));
        foreach($conversation->messages as $message) {
            if( $message->user_id != Auth::user()->id ) {
                $message->updated_at = date('Y-m-d H:i:s');
                $message->save();
            }
        }
        if($conversation->sender_id == Auth::user()->id){
            $conversation->sender_viewed = 1;
            $conversation->save();
        }
        else{
            $conversation->receiver_viewed = 1;
            $conversation->save();
        }
        return view('frontend.partials.messages', compact('conversation'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function admin_show($id)
    {
        $conversation = Conversation::findOrFail(decrypt($id));
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
        }
        elseif($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('backend.support.conversations.show', compact('conversation'));
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
        $conversation = Conversation::findOrFail(decrypt($id));
        foreach ($conversation->messages as $key => $message) {
            $message->delete();
        }
        if(Conversation::destroy(decrypt($id))){
            flash(translate('Conversation has been deleted successfully'))->success();
            return back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function message_count(Request $request){
        $conversations = \App\Models\Conversation::where('sender_id', Auth::user()->id)
            ->orWhere('receiver_id', Auth::user()->id)
            ->with('messages')
            ->get();
        $count = 0;
        foreach ($conversations as $k=>$v){
            if($v->sender_id === Auth::user()->id && $v->sender_viewed==0) $count++;
            if($v->receiver_id === Auth::user()->id  && $v->receiver_viewed==0) $count++;
            foreach ($v->messages as &$vv){
                if(strtotime($vv->created_at) === strtotime($vv->updated_at) && $vv->user_id != Auth::user()->id) {
                    $count++;
                }
            }
        }
        return response()->json([
            'result' => $count,
        ]);
    }


}
