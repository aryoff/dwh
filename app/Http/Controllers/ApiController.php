<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

define('FAILED', 'failed');
define('AUTHORIZATION', 'Authorization');
define('FAILEDINPUTINTERACTIONLOG', 'ApiFailedInputInteraction.log');
define('SUCCESS_FLAG', 'success');
define('IDENTITY', 'identity');

class ApiController extends Controller
{
    public function ApiInputInteraction(Request $request)
    {
        $response = new \stdClass;
        $response->status = SUCCESS_FLAG;
        try {
            $id = Crypt::decrypt($request->dwh_source_id);
            $ip = $request->ip();
            $header = '';
            if ($request->hasHeader(AUTHORIZATION)) {
                $header = $request->header(AUTHORIZATION);
                $header = base64_decode(substr($header, 6, strlen($header) - 6));
            }
            $username = substr($header, 0, strpos($header, ':'));
            $password = substr($header, strpos($header, ':') + 1, strlen($header) - strpos($header, ':') + 1);
            $source = DB::select("SELECT parameter->'field' AS parameter,dwh_partner_id FROM dwh_sources CROSS JOIN (SELECT :ip AS ip,:username AS username,:password AS password) params WHERE id = :id AND parameter @> jsonb_build_object('username',username) AND parameter @> jsonb_build_object('password',password) AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip, 'username' => $username, 'password' => $password]); //ambil parameter dari table source sesuai dengan id
            if (count($source) === 1) {
                $this->executeInputInteraction($source, (object) $request->all(), $id);
            } else { //Source select failed
                Log::critical('Failed to authenticate from ' . $request->ip() . ' ' . $request);
                $response->status = FAILED;
            }
        } catch (DecryptException $decryptErr) { //Decryption failed
            Log::critical('Failed to decrypt ID from ' . $request->ip());
            $response->status = FAILED;
        }
        return $response;
    }
    function executeInputInteraction($source, $request, $id)
    {
        $parameter = json_decode($source[0]->parameter);
        $partnerId = $source[0]->dwh_partner_id;
        //fields convertion
        $response = $this->convertDataInputInteraction($parameter, $request, $id);
        $customerData = $response->customerData;
        $partnerData = $response->partnerData;
        $interactionData = $response->interactionData;
        $inputData = $response->inputData;
        // //find customer id
        $contact_filter = "";
        foreach ($customerData as $key => $value) {
            if ($key != 'nama') {
                $contact_filter .= "(dwh_customer_contact_types.name='" . strtolower($key) . "'AND dwh_customer_contacts.value=$$" . strtolower($value) . "$$)OR";
            }
        }
        if ($contact_filter != '') {
            $contact_filter = substr($contact_filter, 0, strlen($contact_filter) - 2);
        } else {
            $contact_filter = 'false';
        }
        $contact_type_list = "";
        foreach ($customerData as $key => $value) {
            $contact_type_list .= "'" . strtolower($key) . "',";
        }
        if ($contact_type_list != '') {
            $contact_type_list = substr($contact_type_list, 0, strlen($contact_type_list) - 1);
        } else {
            $contact_type_list = "''";
        }
        $contactTypeList = DB::select("SELECT id,name FROM dwh_customer_contact_types WHERE name IN ($contact_type_list)");
        $possibleCustomerId = DB::select("SELECT DISTINCT dwh_customer_contacts.dwh_customer_id AS id FROM dwh_customer_contacts INNER JOIN dwh_customer_contact_types ON dwh_customer_contact_types.id=dwh_customer_contact_type_id WHERE $contact_filter");
        $customerId = null;
        switch (count($possibleCustomerId)) {
            case 0:
                # user tidak ditemukan
                $first = true;
                foreach ($contactTypeList as $value) {
                    if ($first) { //bikin data customer baru
                        $customerId = DB::select("INSERT INTO dwh_customers(name) VALUES (?) RETURNING id", [$customerData->nama])[0]->id;
                        $first = false;
                    }
                    DB::insert("INSERT INTO dwh_customer_contacts(dwh_customer_id,dwh_customer_contact_type_id,value) VALUES ($customerId,:tid,:val)", ['tid' => $value->id, 'val' => $customerData->{$value->name}]);
                }
                break;
            case 1:
                # user ditemukan
                $customerId = $possibleCustomerId[0]->id;
                foreach ($contactTypeList as $value) {
                    DB::insert("INSERT INTO dwh_customer_contacts(dwh_customer_id,dwh_customer_contact_type_id,value)VALUES ($customerId,:tid,:val)ON CONFLICT(dwh_customer_contact_type_id, value)DO NOTHING;", ['tid' => $value->id, 'val' => $customerData->{$value->name}]);
                }
                break;
            default:
                //TODO ada beberapa record user yg berbeda2
                $customerId = DB::select("SELECT dwh_customer_contacts.dwh_customer_id AS id,priority FROM dwh_customer_contacts INNER JOIN dwh_customer_contact_types ON dwh_customer_contact_types.id=dwh_customer_contact_type_id WHERE $contact_filter ORDER BY priority ASC")[0]->id; //ambil customer id yg paling prioritas
                break;
        }
        #find partner identity
        $partnerProfiles = '';
        foreach ($partnerData as $key => $value) {
            if ($key != IDENTITY) {
                $partnerProfiles .= "'$key','$value',";
            }
        }
        if ($partnerProfiles != '') {
            $partnerProfiles = "jsonb_build_object(" . substr($partnerProfiles, 0, strlen($partnerProfiles) - 1) . ")";
        } else {
            $partnerProfiles = "'{}'::jsonb";
        }
        if (property_exists($partnerData, IDENTITY) && $partnerData->identity != '') {
            $partnerIdentityId = DB::select("INSERT INTO dwh_partner_identities(dwh_partner_id,identity,profile)VALUES(:pid,:identity::VARCHAR,$partnerProfiles) ON CONFLICT (dwh_partner_id,identity) DO UPDATE SET profile=dwh_partner_identities.profile||EXCLUDED.profile RETURNING id;", ['pid' => $partnerId, IDENTITY => $partnerData->identity])[0]->id;
        } else {
            $partnerIdentityId = null;
        }
        try { //masukkan data interaksi ke dalam tabel sesuai dengan field yg di deklarasikan
            //interaksi bisa input kalau customer null, bisa input kalo partner data null, tapi ngga bisa input kalau 2 2 nya null
            $insertInteraction = false;
            if ($customerId != null || $partnerIdentityId != null) {
                $insertInteraction = DB::insert("INSERT INTO dwh_interactions(dwh_source_id,dwh_customer_id,dwh_partner_identity_id,data) VALUES (:id,:cid,:pid,:data);", ['id' => $id, 'cid' => $customerId, 'pid' => $partnerIdentityId, 'data' => json_encode($interactionData)]);
            }
            if ($insertInteraction && $customerId != null && $partnerIdentityId != null) { //insert data interaksi
                DB::insert("INSERT INTO dwh_customer_to_partner(dwh_customer_id,dwh_partner_identity_id) VALUES (:cid,:pid) ON CONFLICT (dwh_customer_id, dwh_partner_identity_id) DO NOTHING;", ['cid' => $customerId, 'pid' => $partnerIdentityId]);
            } elseif (!$insertInteraction && ($customerId != null || $partnerIdentityId != null)) {
                $inputData->dwh_source_id = $id;
                if ($customerId != null) {
                    $inputData->error = "User ID $customerId Failed";
                } elseif ($partnerIdentityId != null) {
                    $inputData->error = "Partner Identity ID $partnerIdentityId Failed";
                }
                Storage::append(FAILEDINPUTINTERACTIONLOG, json_encode($inputData));
            }
        } catch (QueryException $qe) {
            $inputData->dwh_source_id = $id;
            $inputData->error = 'User ID Failed ' . $customerId;
            Storage::append(FAILEDINPUTINTERACTIONLOG, json_encode($inputData));
        }
    }
    function convertDataInputInteraction($parameter, $request, $id)
    {
        $response = new \stdClass;
        $insertData = new \stdClass;
        $inputData = $request;
        $insertData->dwh_source_id = $id;
        try { //masukkan data interaksi ke dalam tabel sesuai dengan field yg di deklarasikan
            $interactionData = new \stdClass;
            $customerData = new \stdClass;
            $partnerData = new \stdClass;
            $errField = array();
            $successField = array();
            $successField[] = 'dwh_source_id';
            foreach ($inputData as $inputKey => $inputValue) {
                if (property_exists($parameter, 'interaction') && property_exists($parameter->interaction, $inputKey)) {
                    $interactionData->{$parameter->interaction->{$inputKey}} = $inputValue;
                    $successField[] = $inputKey;
                }
                if (property_exists($parameter, 'customer') && property_exists($parameter->customer, $inputKey)) {
                    $customerData->{$parameter->customer->{$inputKey}} = $inputValue;
                    $successField[] = $inputKey;
                }
                if (property_exists($parameter, 'partner') && property_exists($parameter->partner, $inputKey)) {
                    $partnerData->{$parameter->partner->{$inputKey}} = $inputValue;
                    $successField[] = $inputKey;
                }
                if (!in_array($inputKey, $successField)) {
                    $errField[] = $inputKey;
                }
            }
            if (property_exists($parameter, 'ignore')) {
                $errField = array_diff($errField, $parameter->ignore);
            }
            if (count($errField) > 0) {
                $inputData->dwh_source_id = $id;
                $inputData->err_field = $errField;
                Storage::append(FAILEDINPUTINTERACTIONLOG, json_encode($inputData));
            }
        } catch (Exception $fieldMismatchErr) { //kalau field nya ada yg salah, maka akan masuk ke dump failed
            $inputData->dwh_source_id = $id;
            Storage::append(FAILEDINPUTINTERACTIONLOG, json_encode($inputData));
        }
        $response->insertData = $insertData;
        $response->customerData = $customerData;
        $response->partnerData = $partnerData;
        $response->interactionData = $interactionData;
        $response->inputData = $inputData;
        return $response;
    }
    public function ApiInputPartnerData(Request $request)
    {
        $response = new \stdClass;
        $response->status = SUCCESS_FLAG;
        if ($request->bearerToken() !== '') {
            try {
                $id = Crypt::decrypt($request->bearerToken());
                $ip = $request->ip();
                $source = DB::select("SELECT parameter->'field' AS parameter,dwh_partner_id FROM dwh_sources CROSS JOIN (SELECT :ip AS ip) params WHERE id = :id AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip]); //ambil parameter dari table source sesuai dengan id
                if (count($source) === 1) {
                    $parameter = json_decode($source[0]->parameter);
                    $response = $this->convertDataInputPartnerData($parameter, (object) $request->all());
                    if (property_exists($response, 'identity_id') && property_exists($response, 'data_id')) {
                        DB::insert("WITH partner AS(SELECT id FROM dwh_partner_identities WHERE identity='" . $response->identity_id . "') INSERT INTO dwh_partner_datas(dwh_partner_identity_id,dwh_partner_id,data_id,data) SELECT id," . $source[0]->dwh_partner_id . "," . $response->data_id . ",'" . json_encode($response->data) . "'::jsonb FROM partner ON CONFLICT (dwh_partner_id, data_id) DO UPDATE SET data=dwh_partner_identities.data||EXCLUDED.data,updated_at=CURRENT_TIMESTAMP;");
                    } else {
                        Log::critical('Key data not found from ' . $request->ip() . ' ' . $request);
                        $response->status = FAILED;
                    }
                } else { //Source select failed
                    Log::critical('Failed to authenticate from ' . $request->ip() . ' ' . $request);
                    $response->status = FAILED;
                }
            } catch (DecryptException $decryptErr) { //Decryption failed
                Log::critical('Failed to decrypt ID from ' . $request->ip());
                $response->status = FAILED;
            }
        } else {
            Log::critical('Valid token not found from ' . $request->ip());
            $response->status = FAILED;
        }
        return $response;
    }
    function convertDataInputPartnerData($parameter, $request)
    {
        $response = new \stdClass;
        $data = new \stdClass;
        foreach ($request as $key => $value) {
            if (property_exists($parameter, 'partner_data') && property_exists($parameter->partner_data, $key)) {
                $response->{$parameter->partner_data->{$key}} = $value;
            } else {
                $data->{$key} = $value;
            }
        }
        $response->data = $data;
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
            $response->status = SUCCESS_FLAG;
            //HACK logging temp

        } catch (Exception $e) {
            $response->status = FAILED;
        }

        return $response;
    }
}