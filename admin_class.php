<?php
session_start();
ini_set('display_errors', 1);
require_once 'api_client.php';

class Action {

    private function oldResponseCode($response, $duplicateCode = 2) {
        if (!empty($response['success'])) {
            return 1;
        }
        if (isset($response['status']) && $response['status'] == 409) {
            return $duplicateCode;
        }
        return 3;
    }

    function login(){
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $response = api_request('POST', '/auth/login', [
            'username' => $username,
            'password' => $password
        ], false);

        if (!empty($response['success']) && !empty($response['token']) && !empty($response['user'])) {
            $_SESSION['api_token'] = $response['token'];

            foreach ($response['user'] as $key => $value) {
                if ($key !== 'password') {
                    $_SESSION['login_' . $key] = $value;
                }
            }
            return 1;
        }

        if (isset($response['status']) && $response['status'] == 403) {
            return 2;
        }
        return 3;
    }

    function login2(){
        return $this->login();
    }

    function logout(){
        session_destroy();
        foreach ($_SESSION as $key => $value) {
            unset($_SESSION[$key]);
        }
        header('location:login.php');
    }

    function logout2(){
        return $this->logout();
    }

    function save_user(){
        $id = $_POST['id'] ?? '';
        $payload = [
            'name' => $_POST['name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'type' => $_POST['type'] ?? ''
        ];

        if (!empty($_POST['password'])) {
            $payload['password'] = $_POST['password'];
        }

        if (empty($id)) {
            $response = api_request('POST', '/users', $payload);
        } else {
            $response = api_request('PUT', '/users/' . $id, $payload);
        }

        return $this->oldResponseCode($response, 2);
    }

    function delete_user(){
        $id = $_POST['id'] ?? '';
        $response = api_request('DELETE', '/users/' . $id);
        return !empty($response['success']) ? 1 : 3;
    }

    function signup(){
        // This old template uses alumnus_bio, which does not belong to the house rental API.
        // For house rental, create users through the admin Users page instead.
        return 3;
    }

    function update_account(){
        $id = $_SESSION['login_id'] ?? '';
        if (empty($id)) return 3;

        $payload = [
            'name' => trim(($_POST['firstname'] ?? '') . ' ' . ($_POST['lastname'] ?? '')),
            'username' => $_POST['email'] ?? ($_SESSION['login_username'] ?? ''),
            'type' => $_SESSION['login_type'] ?? 2
        ];
        if (!empty($_POST['password'])) {
            $payload['password'] = $_POST['password'];
        }

        $response = api_request('PUT', '/users/' . $id, $payload);
        return $this->oldResponseCode($response, 2);
    }

    function save_settings(){
        $payload = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'contact' => $_POST['contact'] ?? '',
            'about_content' => $_POST['about'] ?? ($_POST['about_content'] ?? '')
        ];

        $response = api_request('PUT', '/settings', $payload);

        if (!empty($response['success'])) {
            $_SESSION['system'] = $response['data'] ?? $payload;
            return 1;
        }
        return 3;
    }

    function save_category(){
        $id = $_POST['id'] ?? '';
        $payload = ['name' => $_POST['name'] ?? ''];

        if (empty($id)) {
            $response = api_request('POST', '/categories', $payload);
        } else {
            $response = api_request('PUT', '/categories/' . $id, $payload);
        }

        return $this->oldResponseCode($response);
    }

    function delete_category(){
        $id = $_POST['id'] ?? '';
        $response = api_request('DELETE', '/categories/' . $id);
        return !empty($response['success']) ? 1 : 3;
    }

    function save_house(){
        $id = $_POST['id'] ?? '';
        $payload = [
            'house_no' => $_POST['house_no'] ?? '',
            'description' => $_POST['description'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'price' => $_POST['price'] ?? ''
        ];

        if (empty($id)) {
            $response = api_request('POST', '/houses', $payload);
        } else {
            $response = api_request('PUT', '/houses/' . $id, $payload);
        }

        return $this->oldResponseCode($response, 2);
    }

    function delete_house(){
        $id = $_POST['id'] ?? '';
        $response = api_request('DELETE', '/houses/' . $id);
        return !empty($response['success']) ? 1 : 3;
    }

    function save_tenant(){
        $id = $_POST['id'] ?? '';
        $payload = [
            'firstname' => $_POST['firstname'] ?? '',
            'lastname' => $_POST['lastname'] ?? '',
            'middlename' => $_POST['middlename'] ?? '',
            'email' => $_POST['email'] ?? '',
            'contact' => $_POST['contact'] ?? '',
            'house_id' => $_POST['house_id'] ?? '',
            'date_in' => $_POST['date_in'] ?? date('Y-m-d')
        ];

        if (empty($id)) {
            $response = api_request('POST', '/tenants', $payload);
        } else {
            $response = api_request('PUT', '/tenants/' . $id, $payload);
        }

        return $this->oldResponseCode($response, 2);
    }

    function rent_house(){
        return $this->save_tenant();
    }

    function delete_tenant(){
        $id = $_POST['id'] ?? '';
        $response = api_request('DELETE', '/tenants/' . $id);
        return !empty($response['success']) ? 1 : 3;
    }

    function get_tdetails(){
        $id = $_POST['id'] ?? '';
        $response = api_request('GET', '/tenants/' . $id);

        if (empty($response['success']) || empty($response['data'])) {
            return json_encode([]);
        }

        $t = $response['data'];
        $data = [];
        $data['months'] = $t['payable_months'] ?? 0;
        $data['payable'] = number_format((float)($t['payable_amount'] ?? 0), 2);
        $data['paid'] = number_format((float)($t['total_paid'] ?? 0), 2);
        $data['last_payment'] = !empty($t['last_payment']['date_created']) ? date('M d, Y', strtotime($t['last_payment']['date_created'])) : 'N/A';
        $data['outstanding'] = number_format((float)($t['outstanding_balance'] ?? 0), 2);
        $data['price'] = number_format((float)($t['monthly_rate'] ?? 0), 2);
        $data['name'] = ucwords($t['full_name'] ?? '');
        $data['rent_started'] = !empty($t['date_in']) ? date('M d, Y', strtotime($t['date_in'])) : 'N/A';

        return json_encode($data);
    }

    function save_payment(){
        $id = $_POST['id'] ?? '';
        $payload = [
            'tenant_id' => $_POST['tenant_id'] ?? '',
            'amount' => $_POST['amount'] ?? '',
            'invoice' => $_POST['invoice'] ?? ($_POST['ref_code'] ?? '')
        ];

        if (empty($id)) {
            $response = api_request('POST', '/payments', $payload);
        } else {
            $response = api_request('PUT', '/payments/' . $id, $payload);
        }

        return !empty($response['success']) ? 1 : 3;
    }

    function delete_payment(){
        $id = $_POST['id'] ?? '';
        $response = api_request('DELETE', '/payments/' . $id);
        return !empty($response['success']) ? 1 : 3;
    }
}
?>
