<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_get_agent_logs_for_current_actor($app_id) {
    global $wpdb;

    $app_id = sanitize_text_field((string) $app_id);
    if ($app_id === '') {
        return array();
    }

    $table_name = deepseek_get_agent_chat_logs_table_name();
    $user_id = get_current_user_id();

    if ($user_id > 0) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT message, response, session_id FROM {$table_name} WHERE user_id = %d AND app_id = %s ORDER BY created_at ASC",
            $user_id,
            $app_id
        ));
    }

    $guest_session_id = deepseek_get_guest_session_id();
    if ($guest_session_id === '') {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT message, response, session_id FROM {$table_name} WHERE user_id = 0 AND app_id = %s AND session_id = %s ORDER BY created_at ASC",
        $app_id,
        $guest_session_id
    ));
}

function deepseek_get_agent_conversation_history($session_id, $memory_limit = 0) {
    global $wpdb;

    $session_id = sanitize_text_field((string) $session_id);
    $memory_limit = intval($memory_limit);

    if ($session_id === '' || $memory_limit <= 0) {
        return array();
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT message, response FROM " . deepseek_get_agent_chat_logs_table_name() . " WHERE session_id = %s ORDER BY id DESC LIMIT %d",
        $session_id,
        $memory_limit
    ));

    return array_reverse(is_array($rows) ? $rows : array());
}

function deepseek_count_agent_conversations($search_user_id = 0) {
    global $wpdb;

    $table_name = deepseek_get_agent_chat_logs_table_name();
    $search_user_id = intval($search_user_id);

    if ($search_user_id > 0) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (SELECT 1 FROM {$table_name} WHERE user_id = %d AND message != '' GROUP BY user_id, app_id) grouped",
            $search_user_id
        ));
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT 1 FROM {$table_name} WHERE message != '' GROUP BY user_id, app_id) grouped");
}

function deepseek_get_admin_agent_conversations($search_user_id = 0, $limit = 20, $offset = 0) {
    global $wpdb;

    $table_name = deepseek_get_agent_chat_logs_table_name();
    $limit = max(1, intval($limit));
    $offset = max(0, intval($offset));
    $search_user_id = intval($search_user_id);

    if ($search_user_id > 0) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cl.user_id, cl.app_id, cl.message, cl.created_at, u.user_login
            FROM {$table_name} cl
            INNER JOIN (
                SELECT MAX(id) AS latest_id
                FROM {$table_name}
                WHERE user_id = %d AND message != ''
                GROUP BY user_id, app_id
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
        "SELECT cl.user_id, cl.app_id, cl.message, cl.created_at, u.user_login
        FROM {$table_name} cl
        INNER JOIN (
            SELECT MAX(id) AS latest_id
            FROM {$table_name}
            WHERE message != ''
            GROUP BY user_id, app_id
        ) latest ON latest.latest_id = cl.id
        LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID
        ORDER BY cl.created_at DESC
        LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));
}

function deepseek_delete_agent_conversation($user_id, $app_id) {
    global $wpdb;

    $user_id = intval($user_id);
    $app_id = sanitize_text_field((string) $app_id);

    if ($user_id < 0 || $app_id === '') {
        return false;
    }

    return $wpdb->delete(
        deepseek_get_agent_chat_logs_table_name(),
        array(
            'user_id' => $user_id,
            'app_id' => $app_id,
        ),
        array('%d', '%s')
    );
}

function deepseek_clear_current_agent_conversation($app_id) {
    global $wpdb;

    $app_id = sanitize_text_field((string) $app_id);
    if ($app_id === '') {
        return false;
    }

    $table_name = deepseek_get_agent_chat_logs_table_name();
    $user_id = get_current_user_id();

    if ($user_id > 0) {
        return $wpdb->delete(
            $table_name,
            array(
                'user_id' => $user_id,
                'app_id' => $app_id,
            ),
            array('%d', '%s')
        );
    }

    $guest_session_id = deepseek_get_guest_session_id();
    if ($guest_session_id === '') {
        return false;
    }

    return $wpdb->delete(
        $table_name,
        array(
            'user_id' => 0,
            'app_id' => $app_id,
            'session_id' => $guest_session_id,
        ),
        array('%d', '%s', '%s')
    );
}
