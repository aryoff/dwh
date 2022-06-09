<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

define('FAILED', 'failed');
define('AUTHORIZATION', 'Authorization');

class ApiController extends Controller
{
    public function ApiInputInteraction(Request $request)
    {
        $response = new \stdClass;
        //HACK logging temp
        $header = '';
        if ($request->hasHeader(AUTHORIZATION)) {
            $header = $request->header(AUTHORIZATION);
            $header = base64_decode(substr($header, 6, strlen($header) - 6));
        }
        $log_debug = (object) $request->all();
        if (!is_object($log_debug)) {
            $log_debug = new \stdClass;
        }
        $log_debug->ba_username = substr($header, 0, strpos($header, ':'));
        $log_debug->ba_password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
        $log_debug->source_ip = $request->ip();
        date_default_timezone_set('Asia/Jakarta');
        $log_debug->log_time = date('Y-m-d H:i:s');
        Storage::append('ApiInputInteraction.log', json_encode($log_debug));
        //HACK logging temp
        $response->status = 'success';
        try {
            $id = Crypt::decrypt($request->id);
            $ip = $request->ip();
            $header = '';
            if ($request->hasHeader(AUTHORIZATION)) {
                $header = $request->header(AUTHORIZATION);
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }
            $username = substr($header, 0, strpos($header, ':'));
            $password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
            $source = DB::select("SELECT parameter->'interaction' AS parameter FROM dwh_sources CROSS JOIN (SELECT :ip AS ip,:username AS username,:password AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip, 'username' => $username, 'password' => $password]); //ambil parameter dari table source sesuai dengan id
            if (count($source) === 1) {
                $parameter = json_decode($source[0]->parameter);
                try {
                    //find customer id
                    $customer_data = (object) $request->customer;
                    $nama_customer = $customer_data->nama_pelanggan;
                    $contact_filter = "";
                    foreach ($customer_data as $key => $value) {
                        if ($key != 'nama') {
                            $contact_filter .= "(dwh_customer_contact_types.name='" . strtolower($key) . "'AND dwh_customer_contacts.value='" . strtolower($value) . "')OR";
                        }
                    }
                    if ($contact_filter != '') {
                        $contact_filter = substr($contact_filter, 0, strlen($contact_filter) - 2);
                    } else {
                        $contact_filter = 'false';
                    }
                    $contact_type_list = "";
                    foreach ($customer_data as $key => $value) {
                        $contact_type_list .= "'" . strtolower($key) . "',";
                    }
                    if ($contact_type_list != '') {
                        $contact_type_list = substr($contact_type_list, 0, strlen($contact_type_list) - 1);
                    }
                    $contactTypeList = DB::select("SELECT id,name FROM dwh_customer_contact_types WHERE name IN ($contact_type_list)");
                    $possibleCustomerId = DB::select("SELECT DISTINCT dwh_customer_contacts.id FROM dwh_customer_contacts INNER JOIN dwh_customer_contact_types ON dwh_customer_contact_types.id=dwh_customer_contact_type_id WHERE $contact_filter ORDER BY priority ASC");
                    Storage::append('ApiInputInteraction.log', 'First customer contact query');
                    $customerId = 0;
                    switch (count($possibleCustomerId)) {
                        case 0:
                            # user tidak ditemukan
                            $first = true;
                            foreach ($contactTypeList as $value) {
                                if ($first) { //bikin data customer baru
                                    $customerId = DB::select("INSERT INTO dwh_customers(name) VALUES (?) RETURNING id", [$nama_customer])[0]->id;
                                    $first = false;
                                }
                                DB::insert("INSERT INTO dwh_customer_contacts(dwh_customer_id,dwh_customer_contact_type_id,value) VALUES ($customerId,:tid,:val)", ['tid' => $value->id, 'val' => $customer_data->{$value->name}]);
                            }
                            break;
                        case 1:
                            # user ditemukan
                            $customerId = $possibleCustomerId[0]->id;
                            foreach ($contactTypeList as $value) {
                                DB::insert("INSERT INTO dwh_customer_contacts(dwh_customer_id,dwh_customer_contact_type_id,value)VALUES ($customerId,:tid,:val)ON CONFLICT(dwh_customer_contact_type_id, value)DO NOTHING;", ['tid' => $value->id, 'val' => $customer_data->{$value->name}]);
                            }
                            break;
                        default:
                            # ada beberapa record user yg berbeda2
                            break;
                    }
                    Storage::append('ApiInputInteraction.log', 'Customer ID' . $customerId);

                    $insert_data = new \stdClass;
                    $insert_data->dwh_source_id = $id;
                    $callback_data = (object) $request->interaksi;
                    try { //masukkan data interaksi ke dalam tabel sesuai dengan field yg di deklarasikan
                        foreach ($parameter->field as $field) {
                            $insert_data->{$field->target} = $callback_data->{$field->source};
                        }
                        try {
                            DB::insert("INSERT INTO dwh_interactions(dwh_source_id,data) VALUES (:id,:data)", ['id' => $id, 'data' => json_encode($insert_data)]); //insert data interaksi
                        } catch (QueryException $qe) {
                            Storage::append('ApiFailedInputInteraction.log', json_encode($insert_data));
                        }
                    } catch (Exception $fieldMismatchErr) { //kalau field nya ada yg salah, maka akan masuk ke dump failed
                        $callback_data->dwh_source_id = $id;
                        Storage::append('ApiFailedInputInteraction.log', json_encode($callback_data));
                    }
                } catch (Exception $dataFormatErr) { //Interaction key not found on request body
                    $response->status = 'Wrong Data Format';
                }
            } else { //Source select failed
                $response->status = FAILED;
            }
        } catch (DecryptException $decryptErr) { //Decryption failed
            $response->status = FAILED;
        }
        return $response;
    }
    public function ApiInputCustomer(Request $request)
    {
        $response = new \stdClass;
        try {
            $header = '';
            if ($request->hasHeader(AUTHORIZATION)) {
                $header = $request->header(AUTHORIZATION);
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
            $response->status = FAILED;
        }

        return $response;
    }
}