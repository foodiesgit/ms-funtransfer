<?php
namespace App\Helpers;
use DB;
use GuzzleHttp\Client;
use App\Services\AES;
class EVGHelper
{
    public static function createEVGGameTransactionExt($gametransaction_id,$provider_request,$mw_request,$mw_response,$client_response,$game_transaction_type){
		$gametransactionext = array(
			"provider_trans_id" => $provider_request["transaction"]["id"],
			"game_trans_id" => $gametransaction_id,
			"round_id" =>$provider_request["transaction"]["refId"],
			"amount" =>$provider_request["transaction"]["amount"],
			"game_transaction_type"=>$game_transaction_type,
			"provider_request" =>json_encode($provider_request),
			"mw_request"=>json_encode($mw_request),
			"mw_response" =>json_encode($mw_response),
			"client_response" =>json_encode($client_response),
		);
		$gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
		return $gamestransaction_ext_ID;
    }
    public static function gameLaunch($token,$players_ip,$gamecode=null,$lang=null){
        $client_details = EVGHelper::_getClientDetails("token",$token);
        if($client_details){
            $data = array(
                "uuid" => $token,
                "player"=> array(
                            "id"=> (string)$client_details->player_id,
                            "update"=>false,
                            "country"=>"US",
                            "language"=>"en",
                            "currency"=> $client_details->default_currency,
                            "session" => array(
                                         "id"=>$token,
                                         "ip"=>$players_ip,

                            ),
                        ),
                "config"=> array(
                            "game" => array(
                                        "category"=>"rng-topcard",
                                        "table"=>array(
                                                "id"=>"rng-topcard00001"
                                        )
                            ),
                            "channel"=> array(
                                        "wrapped"=> false,
                                        "mobile"=> false
                            ),
                        ),
            );
            $client = new Client();
            $provider_response = $client->post(config('providerlinks.evolution.ua2AuthenticationUrl'),
                ['body' => json_encode($data),
                ]
            );
            return json_decode($provider_response->getBody(),TRUE);
        }
    }
    private static function _getClientDetails($type = "", $value = "") {

		$query = DB::table("clients AS c")
				 ->select('p.client_id', 'p.player_id', 'p.client_player_id','p.username', 'p.email', 'p.language', 'p.currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name','c.default_currency', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
				 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
				 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
				 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
				 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
				 
				if ($type == 'token') {
					$query->where([
				 		["pst.player_token", "=", $value],
				 		["pst.status_id", "=", 1]
				 	]);
				}

				if ($type == 'player_id') {
					$query->where([
				 		["p.player_id", "=", $value],
				 		["pst.status_id", "=", 1]
				 	])->orderBy('pst.token_id','desc')->limit(1);
				}

				 $result= $query->first();

		return $result;
    }

}