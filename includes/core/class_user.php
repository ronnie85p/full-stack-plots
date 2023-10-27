<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where

        $where = '';
        if ($user_id) $where = "user_id='$user_id'";
        else if ($phone) $where = "phone='$phone'";

        $where = empty($where) ? '' : "WHERE $where";
        if (!empty($where)) {
            $q = DB::query("SELECT * FROM `users` $where LIMIT 1;") or die (DB::error());
            if ($row = DB::fetch_row($q)) {
                return [
                    'id' => (int) $row['user_id'],
                    'plots' => '',
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'access' => (int) $row['access']
                ];
            }    
        }

        return [
            'id' => 0,
            'plots' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'access' => 0
        ];
    }

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];

        // where
        // if ($search) $where[] = "user_id LIKE '%".$search."%'";

        $where = [];
        if (!empty($search)) {
            $search = trim($d['search']);
            $search_by = empty($d['search_by']) ? 'first_name' : trim($d['search_by']);
            $where[] = "$search_by LIKE '%$search%'";
        }

        $where = $where ? "WHERE ".implode(" AND ", $where) : "";

        // info
        $table = '`users`';
        $columns = 'user_id, village_id, plot_id, first_name, last_name, email, phone, last_login';
        $q = DB::query("SELECT $columns FROM $table $where LIMIT $offset, $limit") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'village_id' => (int) $row['village_id'],
                'plot_id' => (int) $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'last_login' => empty($row['last_login']) ? 0 : date('d/m/Y H:i', $row['last_login'])
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM $table $where");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function prepare_input(&$d) {
        $d['offset'] = isset($d['offset']) ? (int) $d['offset'] : 0;
        $d['user_id'] = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $d['first_name'] = isset($d['first_name']) ? trim($d['first_name']) : '';
        $d['last_name'] = isset($d['last_name']) ? trim($d['last_name']) : '';
        $d['phone'] = isset($d['phone']) ? preg_replace('/\D+/', '', trim($d['phone'])) : '';
        $d['email'] = isset($d['email']) ? strtolower(trim($d['email'])) : '';
        $d['plots'] = empty($d['plots']) ? '' : preg_replace('/\s{1,}/', ' ', $d['plots']);
    }

    public static function validate_input($d, &$errors) {
        $validation = [
            'first_name:required',
            'last_name:required',
            'phone:required:phone',
            'email:required:email'
        ];

        if (!validate_data($validation, $d, $errors)) {
            return false;
        }

        if (!empty($d['plots']) && !self::check_plots($d['plots'], $errors)) {
            return false;
        }

        return true;
    }

    public static function check_plots($plots, &$errors) {
        $errors = $not_exists = [];

        foreach (explode(',', $plots) as $id) {
            $id = trim($id);
            if (!Plot::plot_exists($id)) {
                $not_exists[] = $id;
            }
        }

        if (!empty($not_exists)) {
            $message = 'Указанного участка ';
            if (count($not_exists) > 1) {
                $message = 'Указанных участков ';
            }

            $errors['plots'] = $message . '"' . implode(', ', $not_exists) . '" не существует';
        }

        return empty($errors);
    }   

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // prepare
        self::prepare_input($d);

        // validate
        if (!self::validate_input($d, $errors)) {
            return ['errors' => $errors];
        }

        $user_id = $d['user_id'];
        $offset = $d['offset'];
        
        // update
        if ($user_id) {
            $set = [];
            $set[] = "plot_id='{$d['plots']}'";
            $set[] = "first_name='{$d['first_name']}'";
            $set[] = "last_name='{$d['last_name']}'";
            $set[] = "phone='{$d['phone']}'";
            $set[] = "email='{$d['email']}'";
            $set[] = "updated='".Session::$ts."'";
            $set = implode(", ", $set);
            DB::query("UPDATE `users` SET $set WHERE user_id='$user_id' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO `users` (
                plot_id,
                first_name,
                last_name,
                phone,
                email,
                updated
            ) VALUES (
                '{$d['plots']}',
                '{$d['first_name']}',
                '{$d['last_name']}',
                '{$d['phone']}',
                '{$d['email']}',
                '".Session::$ts."'
            );") or die (DB::error());
        }

        // output
        return self::users_fetch(['offset' => $offset]);
    }

    public static function user_delete_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;

        HTML::assign('user_id', $user_id);
        return ['html' => HTML::fetch('./partials/user_delete.html')];
    }

    public static function user_delete($d = []) {
        $user_id = empty($d['user_id']) ? 0 : (int) $d['user_id'];
        if (!empty($user_id)) {
            DB::query("DELETE FROM `users` WHERE user_id=$user_id") or die (DB::error()) 
                or die(DB::error());
        }

        $offset = empty($d['offset']) ? 0 : (int)$d['offset'];
        return User::users_fetch(['offset' => $offset]);
    }
}
