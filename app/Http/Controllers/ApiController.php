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
define('EMPTY_OBJECT', new \stdClass);

class ApiController extends Controller
{
    public function ApiInputInteraction(Request $request)
    {
        $response = new \stdClass;
        $response->status = SUCCESS_FLAG;
        if ($request->has('dwh_source_id')) { //old verification
            $sourceId = $request->dwh_source_id;
        } elseif ($request->bearerToken() != '') { //bearer token verification
            $sourceId = $request->bearerToken();
        } else {
            Log::critical('No valid ID from ' . $request->ip());
            $response->status = FAILED;
            return $response;
        }
        try {
            $id = Crypt::decrypt($sourceId);
            $ip = $request->ip();
            $source = DB::select("SELECT parameter->'field' AS parameter,dwh_partner_id FROM dwh_sources CROSS JOIN (SELECT :ip AS ip) params WHERE id = :id AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip]); //ambil parameter dari table source sesuai dengan id
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
        $partner = $response->partner;
        $partnerData = $response->partnerData;
        $interactionData = $response->interactionData;
        $inputData = $response->inputData;
        $employee = $response->employee;
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
        $partnerDataId = null;
        foreach ($partner as $key => $value) {
            if ($key != IDENTITY) {
                $partnerProfiles .= "'$key',$$$value$$,";
            }
        }
        if ($partnerProfiles != '') {
            $partnerProfiles = "jsonb_build_object(" . substr($partnerProfiles, 0, strlen($partnerProfiles) - 1) . ")";
        } else {
            $partnerProfiles = "'{}'::jsonb";
        } //TODO bikin konversi json encode ke jsonb_build_object
        if (property_exists($partner, IDENTITY) && $partner->identity != '') {
            $partnerIdentityId = DB::select("INSERT INTO dwh_partner_identities(dwh_partner_id,identity,profile)VALUES(:pid,:identity::VARCHAR,$partnerProfiles) ON CONFLICT (dwh_partner_id,identity) DO UPDATE SET profile=dwh_partner_identities.profile||EXCLUDED.profile RETURNING id;", ['pid' => $partnerId, IDENTITY => $partner->identity])[0]->id;
            if (property_exists($partnerData, 'identity_id') && property_exists($partnerData, 'data_id')) { // insert partner_data
                $partnerDataId = DB::select("WITH partner AS(SELECT id FROM dwh_partner_identities WHERE identity=:iid) INSERT INTO dwh_partner_datas(dwh_partner_identity_id,dwh_partner_id,data_id,data) SELECT id,:pid,:did,:jdata::jsonb-'identity_id'-'data_id' FROM partner ON CONFLICT (dwh_partner_id, data_id) DO UPDATE SET data=dwh_partner_datas.data||EXCLUDED.data,updated_at=CURRENT_TIMESTAMP RETURNING id;", ['iid' => $partnerData->identity_id, 'pid' => $partnerId, 'did' => $partnerData->data_id, 'jdata' => json_encode($partnerData)])[0]->id;
            }
        } else {
            $partnerIdentityId = null;
        }
        if ($employee != EMPTY_OBJECT) {
            Log::info(json_encode($employee));
            $employeeQuery = DB::select("SELECT id FROM dwh_employees WHERE profile->'dwh_source' @> jsonb_build_object('" . $id . "','" . $employee->agent_id . "')");
            Log::info(json_encode($employeeQuery));
            // if (count($employeeQuery) > 0) {
            //     $employeeID = $employeeQuery[0]->id;
            // } else {
            //     $employeeID = DB::insert("INSERT INTO dwh_employees(name,profile) VALUES (:agent_name,jsonb_build_object('dwh_source',jsonb_build_object(:source_id::VARCHAR,:agent_id)))", ['agent_name' => $employee->agent_name, 'source_id' => $id, 'agent_id' => $employee->agent_id]);
            // }
            // $interactionData->agent_id = $employeeID;
        }
        try { //masukkan data interaksi ke dalam tabel sesuai dengan field yg di deklarasikan
            //interaksi bisa input kalau customer null, bisa input kalo partner data null, tapi ngga bisa input kalau 2 2 nya null
            $insertInteraction = false;
            if ($customerId != null || $partnerIdentityId != null) {
                $insertInteraction = DB::insert("INSERT INTO dwh_interactions(dwh_source_id,dwh_customer_id,dwh_partner_identity_id,dwh_partner_data_id,data) VALUES (:id,:cid,:pid,:pdid,:data);", ['id' => $id, 'cid' => $customerId, 'pid' => $partnerIdentityId, 'pdid' => $partnerDataId, 'data' => json_encode($interactionData)]);
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
    function checkAndAssign(object $container, object $parameter, string $category, array $successField, string $inputKey, $inputValue)
    {
        $temp = new \stdClass;
        $temp->successField = $successField;
        $temp->container = $container;
        if (property_exists($parameter, $category) && property_exists($parameter->{$category}, $inputKey)) {
            $temp->container->{$parameter->{$category}->{$inputKey}} = $inputValue;
            $temp->successField[] = $inputKey;
        }
        return $temp;
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
            $partner = new \stdClass;
            $partnerData = new \stdClass;
            $employee = new \stdClass;
            $errField = array();
            $successField = array();
            $successField[] = 'dwh_source_id';
            foreach ($inputData as $inputKey => $inputValue) {
                $tempContainer = $this->checkAndAssign($interactionData, $parameter, 'interaction', $successField, $inputKey, $inputValue);
                $interactionData = $tempContainer->container;
                $successField = $tempContainer->successField;
                $tempContainer = $this->checkAndAssign($customerData, $parameter, 'customer', $successField, $inputKey, $inputValue);
                $customerData = $tempContainer->container;
                $successField = $tempContainer->successField;
                $tempContainer = $this->checkAndAssign($partner, $parameter, 'partner', $successField, $inputKey, $inputValue);
                $partner = $tempContainer->container;
                $successField = $tempContainer->successField;
                $tempContainer = $this->checkAndAssign($partnerData, $parameter, 'partner_data', $successField, $inputKey, $inputValue);
                $partnerData = $tempContainer->container;
                $successField = $tempContainer->successField;
                $tempContainer = $this->checkAndAssign($employee, $parameter, 'employee', $successField, $inputKey, $inputValue);
                $employee = $tempContainer->container;
                $successField = $tempContainer->successField;
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
        $response->partner = $partner;
        $response->partnerData = $partnerData;
        $response->employee = $employee;
        $response->interactionData = $interactionData;
        $response->inputData = $inputData;
        $response->employee = $employee;
        return $response;
    }
    public function ApiInputPartnerData(Request $request)
    {
        $response = new \stdClass;
        $response->status = SUCCESS_FLAG;
        if ($request->bearerToken() != '') {
            try {
                $id = Crypt::decrypt($request->bearerToken());
                $ip = $request->ip();
                $source = DB::select("SELECT parameter->'field' AS parameter,dwh_partner_id FROM dwh_sources CROSS JOIN (SELECT :ip AS ip) params WHERE id = :id AND jsonb_exists(parameter->'allowed_ip', ip)", ['id' => $id, 'ip' => $ip]); //ambil parameter dari table source sesuai dengan id
                if (count($source) === 1) {
                    $parameter = json_decode($source[0]->parameter);
                    $response = $this->convertDataInputPartnerData($parameter, (object) $request->all());
                    if (property_exists($response, 'identity_id') && property_exists($response, 'data_id')) {
                        DB::insert("WITH partner AS(SELECT id FROM dwh_partner_identities WHERE identity=:iid) INSERT INTO dwh_partner_datas(dwh_partner_identity_id,dwh_partner_id,data_id,data) SELECT id,:pid,:did,:jdata::jsonb FROM partner ON CONFLICT (dwh_partner_id, data_id) DO UPDATE SET data=dwh_partner_datas.data||EXCLUDED.data,updated_at=CURRENT_TIMESTAMP;", ['iid' => $response->identity_id, 'pid' => $source[0]->dwh_partner_id, 'did' => $response->data_id, 'jdata' => json_encode($response->data)]);
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