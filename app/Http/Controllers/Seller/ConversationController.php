<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Models\BusinessSetting;
use App\Models\Message;
use App\Models\ProductQuery;
use Auth;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Fileset\Collection;
use function response;
use function strtotime;
use function translate;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (BusinessSetting::where('type', 'conversation_system')->first()->value == 1) {
            $conversations = Conversation::where('sender_id', Auth::user()->id)->orWhere('receiver_id', Auth::user()->id)->orderBy('created_at', 'desc')->paginate(5);
            return view('seller.conversations.index', compact('conversations'));
        } else {
            flash(translate('Conversation is disabled at this moment'))->warning();
            return back();
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
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->receiver_viewed = 1;
        }
        $conversation->save();
        return view('seller.conversations.show', compact('conversation'));
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
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->sender_viewed = 1;
            $conversation->save();
        } else {
            $conversation->receiver_viewed = 1;
            $conversation->save();
        }
        return view('frontend.partials.messages', compact('conversation'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function message_store(Request $request)
    {
        $message = new Message;
        $message->conversation_id = $request->conversation_id;
        $message->user_id = Auth::user()->id;
        $message->message = $request->message;
        $message->save();
        $conversation = $message->conversation;
        if ($conversation->sender_id == Auth::user()->id) {
            $conversation->receiver_viewed = "1";
        } elseif ($conversation->receiver_id == Auth::user()->id) {
            $conversation->sender_viewed = "1";
        }
        $conversation->save();
        return back();
    }

    public function message_count(Request $request)
    {
        $sellerId = Auth::id();
        $count = Conversation::where('sender_id', $sellerId)->where('sender_viewed', 0)->count()
            + Conversation::where('receiver_id', $sellerId)->where('receiver_viewed', 0)->count();
        // Preserve the existing unread rule, including nullable legacy rows,
        // without loading every conversation/message into PHP on each poll.
        $count += DB::table('messages')->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where(function ($query) use ($sellerId) {
                $query->where('conversations.sender_id', $sellerId)->orWhere('conversations.receiver_id', $sellerId);
            })
            ->where(function ($query) use ($sellerId) {
                $query->where('messages.user_id', '<>', $sellerId)->orWhereNull('messages.user_id');
            })
            ->where(function ($query) {
                $query->whereColumn('messages.created_at', 'messages.updated_at')
                    ->orWhere(function ($query) {
                        $query->whereNull('messages.created_at')->whereNull('messages.updated_at');
                    });
            })->count();

        return response()->json(['result' => $count]);
    }

}
