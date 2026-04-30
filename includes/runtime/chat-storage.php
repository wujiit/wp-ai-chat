<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_save_chat_message($args) {
    global $wpdb;

    $defaults = array(
        'user_id' => get_current_user_id(),
        'conversation_id' => 0,
        'conversation_title' => '',
        'message' => '',
        'response' => '',
    );
    $data = wp_parse_args($args, $defaults);

    $response = $data['response'];
    if (is_array($response) || is_object($response)) {
        $response = wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $inserted = $wpdb->insert(
        deepseek_get_chat_logs_table_name(),
        array(
            'user_id' => intval($data['user_id']),
            'conversation_id' => absint($data['conversation_id']),
            'conversation_title' => sanitize_text_field($data['conversation_title']),
            'message' => sanitize_textarea_field($data['message']),
            'response' => (string) $response,
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );

    if (false === $inserted) {
        return array(
            'insert_id' => 0,
            'conversation_id' => 0,
        );
    }

    $insert_id = (int) $wpdb->insert_id;
    $conversation_id = absint($data['conversation_id']);

    if ($conversation_id <= 0) {
        $conversation_id = $insert_id;
        $wpdb->update(
            deepseek_get_chat_logs_table_name(),
            array('conversation_id' => $conversation_id),
            array('id' => $insert_id),
            array('%d'),
            array('%d')
        );
    }

    if (intval($data['user_id']) === 0) {
        deepseek_set_guest_conversation_owner($conversation_id);
    }

    if (function_exists('deepseek_maybe_create_conversation_meta')) {
        deepseek_maybe_create_conversation_meta(
            $conversation_id,
            sanitize_text_field($data['conversation_title'] ?: $data['message']),
            intval($data['user_id'])
        );
    }

    return array(
        'insert_id' => $insert_id,
        'conversation_id' => $conversation_id,
    );
}

function deepseek_get_chat_conversation_logs($conversation_id) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0 || !deepseek_current_user_can_access_chat_conversation($conversation_id)) {
        return array();
    }

    $current_user_id = get_current_user_id();
    $table_name = deepseek_get_chat_logs_table_name();

    if (current_user_can('manage_options')) {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE conversation_id = %d ORDER BY id ASC",
            $conversation_id
        );
    } elseif ($current_user_id > 0) {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE conversation_id = %d AND user_id = %d ORDER BY id ASC",
            $conversation_id,
            $current_user_id
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE conversation_id = %d AND user_id = 0 ORDER BY id ASC",
            $conversation_id
        );
    }

    return $wpdb->get_results($sql);
}

function deepseek_get_chat_conversation_history($conversation_id, $memory_limit = 0) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    $memory_limit = intval($memory_limit);

    if ($conversation_id <= 0 || $memory_limit <= 0 || !deepseek_current_user_can_access_chat_conversation($conversation_id)) {
        return array();
    }

    $current_user_id = get_current_user_id();
    $table_name = deepseek_get_chat_logs_table_name();

    if ($current_user_id > 0) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT message, response FROM {$table_name} WHERE conversation_id = %d AND user_id = %d ORDER BY id DESC LIMIT %d",
            $conversation_id,
            $current_user_id,
            $memory_limit
        ));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT message, response FROM {$table_name} WHERE conversation_id = %d AND user_id = 0 ORDER BY id DESC LIMIT %d",
            $conversation_id,
            $memory_limit
        ));
    }

    return array_reverse(is_array($rows) ? $rows : array());
}

function deepseek_get_user_chat_history_groups($user_id, $limit = 0, $offset = 0) {
    global $wpdb;

    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return array();
    }

    $table_name = deepseek_get_chat_logs_table_name();

    if (function_exists('deepseek_conversation_meta_table')) {
        $meta_table = deepseek_conversation_meta_table();
        $base_sql = "SELECT cl.*, cm.title AS meta_title, cm.is_pinned AS is_pinned FROM {$table_name} cl
            INNER JOIN (
                SELECT MAX(id) AS latest_id
                FROM {$table_name}
                WHERE user_id = %d
                GROUP BY conversation_id
            ) latest ON latest.latest_id = cl.id
            LEFT JOIN {$meta_table} cm ON cm.conversation_id = cl.conversation_id AND cm.user_id = %d
            ORDER BY COALESCE(cm.is_pinned, 0) DESC, cl.created_at DESC";

        if ($limit > 0) {
            return $wpdb->get_results($wpdb->prepare(
                $base_sql . ' LIMIT %d OFFSET %d',
                $user_id,
                $user_id,
                intval($limit),
                max(0, intval($offset))
            ));
        }

        return $wpdb->get_results($wpdb->prepare($base_sql, $user_id, $user_id));
    }

    $base_sql = "SELECT cl.* FROM {$table_name} cl
        INNER JOIN (
            SELECT MAX(id) AS latest_id
            FROM {$table_name}
            WHERE user_id = %d
            GROUP BY conversation_id
        ) latest ON latest.latest_id = cl.id
        ORDER BY cl.created_at DESC";

    if ($limit > 0) {
        return $wpdb->get_results($wpdb->prepare(
            $base_sql . ' LIMIT %d OFFSET %d',
            $user_id,
            intval($limit),
            max(0, intval($offset))
        ));
    }

    return $wpdb->get_results($wpdb->prepare($base_sql, $user_id));
}

function deepseek_find_chat_log_by_task_id($task_id) {
    global $wpdb;

    $task_id = sanitize_text_field((string) $task_id);
    if ($task_id === '') {
        return null;
    }

    $table_name = deepseek_get_chat_logs_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE response LIKE %s ORDER BY id DESC LIMIT 1",
        '%' . $wpdb->esc_like($task_id) . '%'
    ));

    if (!$row || !deepseek_current_user_can_access_chat_row($row)) {
        return null;
    }

    return $row;
}

function deepseek_update_chat_log_response($log_id, $response) {
    global $wpdb;

    $log_id = absint($log_id);
    if ($log_id <= 0) {
        return false;
    }

    if (is_array($response) || is_object($response)) {
        $response = wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $updated = $wpdb->update(
        deepseek_get_chat_logs_table_name(),
        array('response' => (string) $response),
        array('id' => $log_id),
        array('%s'),
        array('%d')
    );

    return false !== $updated;
}

function deepseek_delete_chat_conversation($conversation_id) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0 || !deepseek_current_user_can_access_chat_conversation($conversation_id)) {
        return false;
    }

    $table_name = deepseek_get_chat_logs_table_name();

    if (current_user_can('manage_options')) {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE conversation_id = %d",
            $conversation_id
        ));
    } elseif (is_user_logged_in()) {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE conversation_id = %d AND user_id = %d",
            $conversation_id,
            get_current_user_id()
        ));
    } else {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE conversation_id = %d AND user_id = 0",
            $conversation_id
        ));
        deepseek_clear_guest_conversation_owner($conversation_id);
    }

    if ($deleted && function_exists('deepseek_delete_conversation_meta_for_current_actor')) {
        deepseek_delete_conversation_meta_for_current_actor($conversation_id);
    }

    return $deleted;
}

function deepseek_count_chat_conversations($search_user_id = 0) {
    global $wpdb;

    $table_name = deepseek_get_chat_logs_table_name();
    $search_user_id = intval($search_user_id);

    if ($search_user_id > 0) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT conversation_id) FROM {$table_name} WHERE user_id = %d",
            $search_user_id
        ));
    }

    return (int) $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table_name}");
}

function deepseek_get_admin_chat_conversations($search_user_id = 0, $limit = 20, $offset = 0) {
    global $wpdb;

    $table_name = deepseek_get_chat_logs_table_name();
    $limit = max(1, intval($limit));
    $offset = max(0, intval($offset));
    $search_user_id = intval($search_user_id);

    if ($search_user_id > 0) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cl.*, u.user_login
            FROM {$table_name} cl
            INNER JOIN (
                SELECT MAX(id) AS latest_id
                FROM {$table_name}
                WHERE user_id = %d
                GROUP BY conversation_id
            ) latest ON latest.latest_id = cl.id
            LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
            ORDER BY cl.created_at DESC
            LIMIT %d OFFSET %d",
            $search_user_id,
            $limit,
            $offset
        ));
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT cl.*, u.user_login
        FROM {$table_name} cl
        INNER JOIN (
            SELECT MAX(id) AS latest_id
            FROM {$table_name}
            GROUP BY conversation_id
        ) latest ON latest.latest_id = cl.id
        LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
        ORDER BY cl.created_at DESC
        LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));
}
