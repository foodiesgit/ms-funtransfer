<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AES;
use App\Helpers\FCHelper;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use DB;
class FCController extends Controller
{
    //


    public function SampleEncrypt(Request $request){
        $data = $request->getContent();

        return array("AESENCRYPT"=>FCHelper::AESEncode($data),"SIGN"=>md5($request->getContent()));
    }
    public function SampleDecrypt(){
        $data = '7Jhu1hCXPmisYLWVGIKhulHfbIWwss8oNfXCdmzP3VPIxJf7ZgYvHBfVPhcec5eo';
        return FCHelper::AESDecode($data);
    }
    public function gameLaunch(Request $request){
        //return FCHelper::addMember(117,1);
        return FCHelper::loginGame(117,21003,1,'https://daddy.betrnk.games/');
    }
    public function transactionMake(Request $request){
        $datareq = FCHelper::AESDecode((string)$request->Params);
        $data = json_decode($datareq,TRUE);
        //return $data;
        $client_details = $this->_getClientDetails("player_id",json_decode($datareq,TRUE)["MemberAccount"]);
        if($client_details){
            if(Helper::getBalance($client_details) < round($data["Bet"],2)){ 
                $response =array(
                    "Result"=>203,
                    "ErrorText"=> "Your Cash Balance not enough."
                );
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            $this->_betGame($client_details,$data);
            return $this->_winGame($client_details,$data);
        }
    }
    private function _betGame($client_details,$data){
        if($client_details){
            $game_transaction = Helper::checkGameTransaction($data["BankID"]);
            $bet_amount = $game_transaction ? 0 : round($data["Bet"],2);
            $bet_amount = $bet_amount < 0 ? 0 :$bet_amount;
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            $requesttocient = [
                "access_token" => $client_details->client_access_token,
                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                "type" => "fundtransferrequest",
                "datetsent" => "",
                "gamedetails" => [
                  "gameid" => "",
                  "gamename" => ""
                ],
                "fundtransferrequest" => [
                      "playerinfo" => [
                      "client_player_id"=>$client_details->client_player_id,
                      "token" => $client_details->player_token
                  ],
                  "fundinfo" => [
                        "gamesessionid" => "",
                        "transactiontype" => "debit",
                        "transferid" => "",
                        "rollback" => "false",
                        "currencycode" => $client_details->currency,
                        "amount" => $bet_amount, #change data here
                  ]
                ]
                  ];
                $guzzle_response = $client->post($client_details->fund_transfer_url,
                ['body' => json_encode(
                        $requesttocient
                )],
                ['defaults' => [ 'exceptions' => false ]]
            );

            $client_response = json_decode($guzzle_response->getBody()->getContents());
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
            $json_data = array(
                "transid" => $data["BankID"],
                "amount" => round($data["Bet"],2),
                "roundid" => $data["RecordID"]
            );
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                
                $response =array(
                    "recordID"=>$data["RecordID"],
                    "balance" =>Helper::getBalance($client_details),
                );
                $game = Helper::getGameTransaction($client_details->player_token,$data["RecordID"]);
                if(!$game){
                    $gametransactionid=Helper::createGameTransaction('debit', $json_data, $game_details, $client_details); 
                }
                else{
                    $gametransactionid= $game->game_trans_id;
                }
                if(!$game_transaction){
                    FCHelper::createFCGameTransactionExt($gametransactionid,$data,$requesttocient,$response,$client_response,1);
                }  
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
        }
    }
    private function _winGame($client_details,$data){
        if($client_details){
            $game_transaction = Helper::checkGameTransaction($data["BankID"],$data["RecordID"],2);
            $win_amount = $game_transaction ? 0 : round($data["Win"],2);
            $win_amount = $win_amount < 0 ? 0 :$win_amount;
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            
             $requesttocient = [
                "access_token" => $client_details->client_access_token,
                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                "type" => "fundtransferrequest",
                "datetsent" => "",
                "gamedetails" => [
                  "gameid" => "",
                  "gamename" => ""
                ],
                "fundtransferrequest" => [
                      "playerinfo" => [
                      "client_player_id"=>$client_details->client_player_id,
                      "token" => $client_details->player_token
                  ],
                  "fundinfo" => [
                        "gamesessionid" => "",
                        "transactiontype" => "credit",
                        "transferid" => "",
                        "rollback" => "false",
                        "currencycode" => $client_details->currency,
                        "amount" => round($win_amount,2)
                  ]
                ]
                  ];
                $guzzle_response = $client->post($client_details->fund_transfer_url,
                ['body' => json_encode(
                        $requesttocient
                )],
                ['defaults' => [ 'exceptions' => false ]]
            );
            $win = $data["Win"] == 0 ? 0 : 1;
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
            $json_data = array(
                "transid" => $data["BankID"],
                "amount" => round($data["Win"],2),
                "roundid" => $data["RecordID"],
                "payout_reason" => null,
                "win" => $win,
            );
            $game = Helper::getGameTransaction($client_details->player_token,$data["RecordID"]);
            if(!$game){
                $gametransactionid=Helper::createGameTransaction('credit', $json_data, $game_details, $client_details); 
            }
            else{
                if($win == 0){
                    $gameupdate = Helper::updateGameTransaction($game,$json_data,"debit");
                }else{
                    $gameupdate = Helper::updateGameTransaction($game,$json_data,"credit");
                }
                $gametransactionid = $game->game_trans_id;
            }
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $response =array(
                    "Result"=>0,
                    "MainPoints" => $balance,
                );
                if(!$game_transaction){
                    FCHelper::createFCGameTransactionExt($gametransactionid,$data,$requesttocient,$response,$client_response,2);
                }  
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            else{
                return "something error with the client";
            }
        }
    }
    public function cancelBet(Request $request){
        $datareq = FCHelper::AESDecode((string)$request->Params);
        $data = json_decode($datareq,TRUE);
        //return $data;
        $client_details = $this->_getClientDetails("player_id",json_decode($datareq,TRUE)["MemberAccount"]);
        if($client_details){
            $rollbackchecker = Helper::checkGameTransaction($data["BankID"]);
            if(!$rollbackchecker){
                $response =array(
                    "Result"=>221,
                    "ErrorText" => "BankID does not exist.",
                );
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            $game_transaction = FCHelper::checkGameTransaction($data["BankID"]);
            $refund_amount = empty($game_transaction) ? 0 : $game_transaction->amount;
            $refund_amount = $refund_amount < 0 ? 0 :$refund_amount;
            
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            
             $requesttocient = [
                "access_token" => $client_details->client_access_token,
                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                "type" => "fundtransferrequest",
                "datetsent" => "",
                "gamedetails" => [
                  "gameid" => "",
                  "gamename" => ""
                ],
                "fundtransferrequest" => [
                      "playerinfo" => [
                      "client_player_id"=>$client_details->client_player_id,
                      "token" => $client_details->player_token
                  ],
                  "fundinfo" => [
                        "gamesessionid" => "",
                        "transactiontype" => "credit",
                        "transferid" => "",
                        "rollback" => "true",
                        "currencycode" => $client_details->currency,
                        "amount" => round($refund_amount,2)
                  ]
                ]
                  ];
                $guzzle_response = $client->post($client_details->fund_transfer_url,
                ['body' => json_encode(
                        $requesttocient
                )],
                ['defaults' => [ 'exceptions' => false ]]
            );
            $win = 0;
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
            $json_data = array(
                "transid" => $data["BankID"],
                "amount" => round($refund_amount,2),
                "roundid" => 0,
            );
            $game = FCHelper::getGameTransaction($client_details->player_token,$data["BankID"]);
            
            if(!$game){
                $gametransactionid=Helper::createGameTransaction('refund', $json_data, $game_details, $client_details); 
            }
            else{
                $gameupdate = Helper::updateGameTransaction($game,$json_data,"refund");
                $gametransactionid = $game->game_trans_id;

            }
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $response =array(
                    "Result"=>0,
                    "MainPoints" => $balance,
                );
                if(!empty($game_transaction)){
                    $data["RecordID"]= $game_transaction->round_id;
                    $data["Win"] = $refund_amount;
                    FCHelper::createFCGameTransactionExt($gametransactionid,$data,$requesttocient,$response,$client_response,3);
                }   
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
        }
        else{
            $response =array(
                "Result"=>500,
                "ErrorText" => "Player ID not exist.",
            ); 
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }
    }
    public function getBalance(Request $request){
        if($request->has("Params")){
            $datareq = FCHelper::AESDecode((string)$request->Params);
            $client_details = $this->_getClientDetails("player_id",json_decode($datareq,TRUE)["MemberAccount"]);
            if($client_details){
                $client = new Client([
                    'headers' => [ 
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$client_details->client_access_token
                    ]
                ]);
                $guzzle_response = $client->post($client_details->player_details_url,
                    ['body' => json_encode(
                            [
                                "access_token" => $client_details->client_access_token,
                                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                                "type" => "playerdetailsrequest",
                                "datesent" => "",
                                "gameid" => "",
                                "clientid" => $client_details->client_id,
                                "playerdetailsrequest" => [
                                    "client_player_id"=>$client_details->client_player_id,
                                    "token" => $client_details->player_token,
                                    "gamelaunch" => "true"
                                ]]
                    )]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                $msg = array(
                    "Result"=>0,
                    "MainPoints"=>(float)number_format($client_response->playerdetailsresponse->balance,2,'.', '')
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "Result"=>500,
                    "ErrorText"=>"Account does not exist.",
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "Result"=>500,
                "ErrorText"=>"Account does not exist.",
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    private function _getClientDetails($type = "", $value = "") {

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