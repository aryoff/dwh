<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;


class ApiController extends Controller
{
    public function ApiInputInteraction(Request $request)
    {
        $response = new \stdClass;
        try {
            //HACK logging temp
            $header = '';
            if ($request->hasHeader('Authorization')) {
                $header = $request->header('Authorization');
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }
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
            //HACK logging temp
            $response->status = 'success';

            try {
                $id = Crypt::decrypt($request->id);
                // $ip = $request->ip();
                $ip = "10.194.5.20";
                $header = '';
                if ($request->hasHeader('Authorization')) {
                    $header = $request->header('Authorization');
                    $header = base64_decode(substr($header, 6, strlen($header) - 6));
                }
                // $username = substr($header, 0, strpos($header, ':'));
                // $password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
                $username = 'syifa';
                $password = 'infomedia';
                $response->status = "SELECT parameter FROM dwh_sources CROSS JOIN (SELECT '$ip' AS ip,'$username' AS username,'$password' AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)";
                $source = DB::select("SELECT parameter FROM dwh_sources CROSS JOIN (SELECT :ip AS ip,:username AS username,:password AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip, 'username' => $username, 'password' => $password]);
                // if (count($source) === 1) {
                //     $parameter = json_decode($source[0]->parameter);
                //     try {
                //         $insert_data = new \stdClass;
                //         $insert_data->source_id = $id;
                //         $callback_data = $request->interaksi;
                //         try { //masukkan data interaksi ke dalam tabel sesuai dengan field yg di deklarasikan
                //             foreach ($parameter->field as $field) {
                //                 $insert_data->{$field->target} = $callback_data->{$field->source};
                //             }
                //             try {
                //                 DB::insert("INSERT INTO dwh_interactions(dwh_source_id,data) VALUES (:id,:data)", ['id' => $id, 'data' => json_encode($insert_data)]);
                //             } catch (QueryException $qe) {
                //                 Storage::append('ApiFailedInputInteraction.log', json_encode($insert_data));
                //             }
                //         } catch (Exception $e) { //kalau field nya ada yg salah, maka akan masuk ke dump failed
                //             $callback_data->source_id = $id;
                //             Storage::append('ApiFailedInputInteraction.log', json_encode($callback_data));
                //         }
                //     } catch (Exception $e) {
                //         $response->status = 'No Interaction Data';
                //     }
                // } else {
                //     $response->status = 'source select failed';
                // }
            } catch (DecryptException $de) {
                $response->status = 'decrypt failed';
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