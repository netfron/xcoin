<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Library\XCoinAPI;
use App\Transactions;
use App\XcoinPasswd;
use App\XcoinXrp;

class CoinController extends Controller
{
    private $api;

    public function __construct()
    {
        $this->api = new XCoinAPI(env('XCOIN_KEY'), env('XCOIN_SECRET_KEY'));
    }

    public function balance()
    {
        return $this->api->xcoinApiCall("/info/balance",['currency'=>'ALL']);
    }

    public function account()
    {
        $rgParams['order_currency'] = 'BTC';
        $rgParams['payment_currency'] = 'KRW';
        return $this->api->xcoinApiCall("/info/account",$rgParams);
    }

    private function cancel($order_id, $currency, $type)
    {
        $result = $this->api->xcoinApiCall("/trade/cancel", ['order_id'=>$order_id, 'currency'=>$currency, 'type'=>$type]);
        $this->insert_transaction($result);
        return $result;
    }

    private function buy($price, $units, $currency)
    {
        $result = $this->api->xcoinApiCall("/trade/place", ['units'=>floor($units), 'order_currency'=>$currency, 'price'=>$price, 'type'=>'bid']);
        $this->insert_transaction($result);
        return $result;
    }

    private function sell($price, $units, $currency)
    {
        $result = $this->api->xcoinApiCall("/trade/place", ['units'=>floor($units), 'order_currency'=>$currency, 'price'=>$price, 'type'=>'ask']);
        $this->insert_transaction($result);
        return $result;
    }

    private function market_buy($units, $currency)
    {
        $result = $this->api->xcoinApiCall("/trade/market_buy", ['units'=>floor($units), 'currency'=>$currency]);
        $this->insert_transaction($result);
        return $result;
    }

    private function market_sell($units, $currency)
    {
        $result = $this->api->xcoinApiCall("/trade/market_sell", ['units'=>floor($units), 'currency'=>$currency]);
        $this->insert_transaction($result);
        return $result;
    }

    public function index()
    {
        $timer = true;
        $result = $this->balance();
        $script = '';
        if($timer) $script = "<script>timeout = setTimeout(function () {location.href='/xcoin';}, 2000);</script>";

        $data = [
            'krw'=>[
                'total'=>$result->data->available_krw,
            ],
            'xrp'=>[
                'total'=>$result->data->available_xrp,
                'current'=>(int)$result->data->xcoin_last_xrp,
            ],
            'btg'=>[
                'total'=>$result->data->available_btg,
                'current'=>(int)$result->data->xcoin_last_btg,
            ],
            'qtum'=>[
                'total'=>$result->data->available_qtum,
                'current'=>(int)$result->data->xcoin_last_qtum,
            ],
            'eos'=>[
                'total'=>$result->data->available_eos,
                'current'=>(int)$result->data->xcoin_last_eos,
            ],
            'eth'=>[
                'total'=>$result->data->available_eth,
                'current'=>(int)$result->data->xcoin_last_eth,
            ],
            'etc'=>[
                'total'=>$result->data->available_etc,
                'current'=>(int)$result->data->xcoin_last_etc,
            ],
        ];

        #-----------------------------------------------------------


        #daemon
        $daemon = $this->daemon($data);
        if($daemon) return response()->json($daemon, 200);


        #-----------------------------------------------------------


        #direct buy
        $buy = [];

        $units = 8000;
        $currency = 'xrp';
        #$buy[$currency]['buy'] = $this->market_buy($units, strtoupper($currency));

        #direct buy result
        if($buy) return response()->json($buy, 200);

        $buy = [];

        $units = 103;
        $currency = 'qtum';
        #$buy[$currency]['buy'] = $this->market_buy($units, strtoupper($currency));

        #direct buy result
        if($buy) return response()->json($buy, 200);

        #-----------------------------------------------------------
        #direct sell

        $sell = [];

        #sell all
        $currency = 'xrp';
        #$sell[$currency]['sell_all'] = $this->market_sell($data[$currency]['total'], strtoupper($currency));

        $currency = 'xrp';
        if($data[$currency]['current'] <= 1300 && $data[$currency]['total'] >= 1){
          #$sell[$currency]['sell_all'] = $this->market_sell($data[$currency]['total'], strtoupper($currency));
        }
        if($sell) return response()->json($sell, 200);

        /*
        if($data[$currency]['current'] <= 1200 && $data[$currency]['total'] >= 1){
          $buy[$currency]['buy'] = $this->market_buy($units, strtoupper($currency));
        }
        if($sell) return response()->json($sell, 200);
        */

        #-----------------------------------------------------------

        #html
        print '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<title>'.$data['xrp']['current'].'</title>';
        echo $script;
        echo '</head>';
        echo '<body>';
        echo '<pre>'.json_encode($data).'</pre>';
        echo '</body>';
        echo '</html>';

    }

    private function daemon($balance)
    {
        $result = [];

        #sell
        $currency = 'xrp';
        $xrp_data = XcoinXrp::where('type', 'sell')->first();
        $min = $xrp_data->min;
        $max = $xrp_data->max;
        $result = $this->auto_sell($result, $balance, $currency, $min, $max);

        return $result;
    }

    private function auto_sell($result, $balance, $currency, $min, $max)
    {
        if((int)$balance[$currency]['total'] >= 1){
            if((int)$balance[$currency]['current'] <= $min && $min > 0){
                $result[$currency]['auto_sell_min'] = $this->market_sell($balance[$currency]['total'], strtoupper($currency));
            }
            if((int)$balance[$currency]['current'] >= $max && $max > 0){
                $result[$currency]['auto_sell_max'] = $this->market_sell($balance[$currency]['total'], strtoupper($currency));
            }
        }
        return $result;
    }

    public function sell_xrp($passwd)
    {
        $result = [];
        $balance = $this->balance();
        $currency = 'xrp';

        $pwd_data = XcoinPasswd::where('password', $passwd)->count();

        if($pwd_data > 0){
            if($balance->data->total_xrp >= 1){
                $result[$currency]['sell_all'] = $this->market_sell($balance->data->total_xrp, strtoupper($currency));
                $this->insert_transaction($result);
                return response()->json($result, 200);
            }
        }
    }

    public function buy_xrp($units, $passwd)
    {
        $result = [];
        $balance = $this->balance();
        $currency = 'xrp';

        $pwd_data = XcoinPasswd::where('password', $passwd)->count();

        if($pwd_data > 0){
            if($balance->data->total_krw >= 1){
                $result[$currency]['sell_all'] = $this->market_buy($units, strtoupper($currency));
                $this->insert_transaction($result);
                return response()->json($result, 200);
            }
        }
    }

    public function transactions()
    {
        $trans = Transactions::orderBy('id', 'desc')->get();

        $result = [];

        foreach ($trans as $tran) {
            $result[] = json_decode($tran->transaction_detail, false);
        }

        #$script = "<script>timeout = setTimeout(function () {location.href='/xcoin/transaction';}, 10000);</script>";

        #html
        echo '<html>';
        #echo "<head>".$script."</head>";
        echo '<body>';
        echo '<pre>'.json_encode($result).'</pre>';
        echo '</body>';
        echo '</html>';

    }

    private function insert_transaction($body)
    {
        $trans = new Transactions;
        $trans->transaction_detail = json_encode($body);
        $trans->save();
    }

}
