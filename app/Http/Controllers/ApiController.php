<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;


class ApiController extends Controller
{
    public function ApiInputInteraction(Request $request)
    {
        $response = new \stdClass;
        $callback_data = new \stdClass;
        try {
            $header = '';
            if ($request->hasHeader('Authorization')) {
                $header = $request->header('Authorization');
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }

            //HACK logging temp
            $log_debug = (object) $request->all();
            if (!is_object($log_debug)) {
                $log_debug = new \stdClass;
            }
            $log_debug->username = substr($header, 0, strpos($header, ':'));
            $log_debug->password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
            $log_debug->source_ip = $request->ip();
            date_default_timezone_set('Asia/Jakarta');
            $log_debug->log_time = date('Y-m-d H:i:s');
            Storage::append('ApiInputInteraction.log', json_encode($log_debug));
            $response->status = 'success';
            //HACK logging temp

            try {
                $id = Crypt::decryptString($request->id);
                $ip = $request->ip();
                $header = '';
                if ($request->hasHeader('Authorization')) {
                    $header = $request->header('Authorization');
                    $header = base64_decode(substr($header, 6, strlen($header) - 6));
                }
                $username = substr($header, 0, strpos($header, ':'));
                $password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
                $source = DB::select("SELECT name,parameter FROM dwh_sources CROSS JOIN (SELECT :ip AS ip,:username AS username,:password AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip, 'username' => $username, 'password' => $password]);
                if (count($source) === 1) {
                    //TODO Code
                } else {
                    $response->status = 'failed';
                }
            } catch (DecryptException $de) {
                $response->status = 'failed';
            }
        } catch (Exception $e) {
            $response->status = 'failed';
        }

        return $response;
    }
    public function ApiInputCustomer(Request $request)
    {
        $response = new \stdClass;
        $callback_data = new \stdClass;
        try {
            $header = '';
            if ($request->hasHeader('Authorization')) {
                $header = $request->header('Authorization');
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }

            //HACK logging temp
            $log_debug = (object) $request->all();
            if (!is_object($log_debug)) {
                $log_debug = new \stdClass;
            }
            $log_debug->username = substr($header, 0, strpos($header, ':'));
            $log_debug->password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
            $log_debug->source_ip = $request->ip();
            date_default_timezone_set('Asia/Jakarta');
            $log_debug->log_time = date('Y-m-d H:i:s');
            Storage::append('ApiInputCustomer.log', json_encode($log_debug));
            $response->status = 'success';
            //HACK logging temp

        } catch (Exception $e) {
            $response->status = 'failed';
        }

        return $response;
    }
}