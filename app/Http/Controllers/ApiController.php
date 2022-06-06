<?php

namespace App\Http\Controllers;

use Exception;
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