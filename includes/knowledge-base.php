<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_KB_SCHEMA_VERSION')) {
    define('DEEPSEEK_KB_SCHEMA_VERSION', '1.0.0');
}

function deepseek_kb_sources_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_kb_sources';
}

function deepseek_kb_chunks_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_kb_chunks';
}

function deepseek_kb_jobs_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_kb_jobs';
}

function deepseek_kb_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
}

function deepseek_kb_ensure_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $sources_table = deepseek_kb_sources_table();
    $chunks_table = deepseek_kb_chunks_table();
    $jobs_table = deepseek_kb_jobs_table();

    $sources_sql = "CREATE TABLE {$sources_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_type varchar(32) NOT NULL DEFAULT 'manual',
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        content_hash varchar(64) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'active',
        meta longtext NULL,
        last_indexed_at datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY type_object (source_type, object_id),
        KEY status_updated (status, updated_at),
        KEY content_hash (content_hash)
    ) {$charset_collate};";

    $chunks_sql = "CREATE TABLE {$chunks_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_id bigint(20) unsigned NOT NULL DEFAULT 0,
        chunk_index int(11) NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        content longtext NOT NULL,
        keywords text NULL,
        content_hash varchar(64) NOT NULL DEFAULT '',
        token_estimate int(11) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY source_chunk (source_id, chunk_index),
        KEY status_created (status, created_at),
        KEY content_hash (content_hash)
    ) {$charset_collate};";

    $jobs_sql = "CREATE TABLE {$jobs_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        job_type varchar(32) NOT NULL DEFAULT 'index',
        source_type varchar(32) NOT NULL DEFAULT '',
        source_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'pending',
        total_items int(11) NOT NULL DEFAULT 0,
        processed_items int(11) NOT NULL DEFAULT 0,
        message text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY status_created (status, created_at),
        KEY source_id (source_id)
    ) {$charset_collate};";

    dbDelta($sources_sql);
    dbDelta($chunks_sql);
    dbDelta($jobs_sql);
}

function deepseek_kb_bootstrap_schema($verify_tables = false) {
    static $bootstrapped = false;

    if ($bootstrapped && !$verify_tables) {
        return;
    }

    $bootstrapped = true;
    $stored_version = get_option('deepseek_kb_schema_version', '');
    $missing_table = false;

    if ($verify_tables) {
        foreach (deepseek_kb_schema_tables() as $table_name) {
            if (!deepseek_kb_table_exists($table_name)) {
                $missing_table = true;
                break;
            }
        }
    }

    if ($stored_version !== DEEPSEEK_KB_SCHEMA_VERSION || $missing_table) {
        deepseek_kb_ensure_schema();
        update_option('deepseek_kb_schema_version', DEEPSEEK_KB_SCHEMA_VERSION, false);
    }
}

function deepseek_kb_schema_tables() {
    return array(deepseek_kb_sources_table(), deepseek_kb_chunks_table(), deepseek_kb_jobs_table());
}

function deepseek_kb_activate_schema() {
    deepseek_kb_ensure_schema();
    update_option('deepseek_kb_schema_version', DEEPSEEK_KB_SCHEMA_VERSION, false);
}

function deepseek_kb_default_source_types() {
    return array('post', 'manual', 'file_record');
}

function deepseek_kb_get_enabled_source_types() {
    $source_types = deepseek_get_array_setting('deepseek_kb_source_types', deepseek_kb_default_source_types());
    $source_types = array_values(array_intersect(array_map('sanitize_key', $source_types), deepseek_kb_default_source_types()));
    return empty($source_types) ? deepseek_kb_default_source_types() : $source_types;
}

function deepseek_kb_get_post_types() {
    $post_types = deepseek_get_array_setting('deepseek_kb_post_types', array('post', 'page'));
    $post_types = array_map('sanitize_key', $post_types);
    $post_types = array_values(array_filter($post_types, 'post_type_exists'));
    return empty($post_types) ? array('post', 'page') : $post_types;
}

function deepseek_kb_get_interfaces() {
    $interfaces = deepseek_get_array_setting('deepseek_kb_interfaces', array());
    $interfaces = array_values(array_filter(array_map('sanitize_key', $interfaces)));
    return $interfaces;
}

function deepseek_kb_get_page_ids() {
    $raw_page_ids = deepseek_get_setting('deepseek_kb_page_ids', '');
    $page_ids = is_array($raw_page_ids) ? $raw_page_ids : explode(',', (string) $raw_page_ids);
    $page_ids = array_map('absint', $page_ids);
    return array_values(array_filter($page_ids));
}

function deepseek_kb_should_apply($interface = '', $page_id = 0) {
    if (deepseek_get_setting('deepseek_kb_enabled', '0') !== '1') {
        return false;
    }

    $interfaces = deepseek_kb_get_interfaces();
    if (!empty($interfaces) && !in_array(sanitize_key($interface), $interfaces, true)) {
        return false;
    }

    $page_ids = deepseek_kb_get_page_ids();
    if (!empty($page_ids) && !in_array(absint($page_id), $page_ids, true)) {
        return false;
    }

    return true;
}

function deepseek_kb_normalize_text($text) {
    $text = wp_strip_all_tags((string) $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function deepseek_kb_extract_keywords($text, $limit = 16) {
    $text = mb_strtolower(deepseek_kb_normalize_text($text), 'UTF-8');
    preg_match_all('/[\p{L}\p{N}_-]{2,}/u', $text, $matches);
    $words = isset($matches[0]) ? $matches[0] : array();
    $stop_words = array('the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'are', 'was', 'were', 'have', 'has');
    $counts = array();

    foreach ($words as $word) {
        if (in_array($word, $stop_words, true)) {
            continue;
        }
        $counts[$word] = isset($counts[$word]) ? $counts[$word] + 1 : 1;
    }

    arsort($counts);
    return implode(' ', array_slice(array_keys($counts), 0, $limit));
}

function deepseek_kb_split_text($text, $chunk_size = 900, $overlap = 120) {
    $text = deepseek_kb_normalize_text($text);
    $chunk_size = max(300, intval($chunk_size));
    $overlap = max(0, min(intval($overlap), intval($chunk_size / 2)));

    if ($text === '') {
        return array();
    }

    $length = mb_strlen($text, 'UTF-8');
    if ($length <= $chunk_size) {
        return array($text);
    }

    $chunks = array();
    $offset = 0;
    while ($offset < $length) {
        $chunk = mb_substr($text, $offset, $chunk_size, 'UTF-8');
        $chunk = trim($chunk);
        if ($chunk !== '') {
            $chunks[] = $chunk;
        }
        $offset += ($chunk_size - $overlap);
    }

    return $chunks;
}

function deepseek_kb_add_job($args) {
    global $wpdb;

    $defaults = array(
        'job_type' => 'index',
        'source_type' => '',
        'source_id' => 0,
        'status' => 'pending',
        'total_items' => 0,
        'processed_items' => 0,
        'message' => '',
    );
    $data = wp_parse_args($args, $defaults);

    $wpdb->insert(
        deepseek_kb_jobs_table(),
        array(
            'job_type' => sanitize_key($data['job_type']),
            'source_type' => sanitize_key($data['source_type']),
            'source_id' => absint($data['source_id']),
            'status' => sanitize_key($data['status']),
            'total_items' => max(0, intval($data['total_items'])),
            'processed_items' => max(0, intval($data['processed_items'])),
            'message' => sanitize_textarea_field($data['message']),
            'updated_at' => current_time('mysql'),
        ),
        array('%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
    );

    return intval($wpdb->insert_id);
}

function deepseek_kb_upsert_source($source_type, $object_id, $title, $content, $meta = array()) {
    global $wpdb;

    $source_type = sanitize_key($source_type);
    $object_id = absint($object_id);
    $title = sanitize_text_field($title);
    $content = deepseek_kb_normalize_text($content);
    $content_hash = hash('sha256', $source_type . '|' . $object_id . '|' . $title . '|' . $content);
    $meta_json = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $table_name = deepseek_kb_sources_table();

    $source_id = 0;
    if ($object_id > 0) {
        $source_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE source_type = %s AND object_id = %d LIMIT 1",
            $source_type,
            $object_id
        )));
    }

    $data = array(
        'source_type' => $source_type,
        'object_id' => $object_id,
        'title' => $title,
        'content_hash' => $content_hash,
        'status' => 'active',
        'meta' => $meta_json,
        'updated_at' => current_time('mysql'),
    );

    if ($source_id > 0) {
        $wpdb->update(
            $table_name,
            $data,
            array('id' => $source_id),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        return $source_id;
    }

    $data['created_at'] = current_time('mysql');
    $wpdb->insert(
        $table_name,
        $data,
        array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
    );

    return intval($wpdb->insert_id);
}

function deepseek_kb_index_source_content($source_id, $title, $content) {
    global $wpdb;

    $source_id = absint($source_id);
    $content = deepseek_kb_normalize_text($content);
    if (!$source_id || $content === '') {
        return 0;
    }

    $chunk_size = intval(deepseek_get_setting('deepseek_kb_chunk_size', 900));
    $chunks = deepseek_kb_split_text($content, $chunk_size, 120);
    $chunks_table = deepseek_kb_chunks_table();

    $wpdb->delete($chunks_table, array('source_id' => $source_id), array('%d'));

    $inserted = 0;
    foreach ($chunks as $index => $chunk) {
        $wpdb->insert(
            $chunks_table,
            array(
                'source_id' => $source_id,
                'chunk_index' => $index,
                'title' => sanitize_text_field($title),
                'content' => $chunk,
                'keywords' => deepseek_kb_extract_keywords($chunk),
                'content_hash' => hash('sha256', $chunk),
                'token_estimate' => max(1, intval(mb_strlen($chunk, 'UTF-8') / 2)),
                'status' => 'active',
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        if (!$wpdb->last_error) {
            $inserted++;
        }
    }

    $wpdb->update(
        deepseek_kb_sources_table(),
        array(
            'last_indexed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ),
        array('id' => $source_id),
        array('%s', '%s'),
        array('%d')
    );

    return $inserted;
}

function deepseek_kb_get_source_content($source) {
    if (!$source) {
        return '';
    }

    $meta = json_decode((string) $source->meta, true);
    $meta = is_array($meta) ? $meta : array();

    if ($source->source_type === 'manual') {
        return isset($meta['content']) ? (string) $meta['content'] : '';
    }

    if ($source->source_type === 'post') {
        $post = get_post((int) $source->object_id);
        if (!$post || $post->post_status !== 'publish') {
            return '';
        }
        return $post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post->post_content;
    }

    if ($source->source_type === 'file_record' && function_exists('deepseek_get_file_record')) {
        $record = deepseek_get_file_record((int) $source->object_id);
        return deepseek_kb_extract_file_record_content($record);
    }

    return '';
}

function deepseek_kb_extract_file_record_content($record) {
    if (!$record) {
        return '';
    }

    $parts = array();
    $parts[] = $record->original_filename;

    if (!empty($record->attachment_id)) {
        $attached_file = get_attached_file((int) $record->attachment_id);
        $ext = strtolower(pathinfo((string) $attached_file, PATHINFO_EXTENSION));
        if ($attached_file && file_exists($attached_file) && in_array($ext, array('txt', 'md', 'csv'), true)) {
            $content = file_get_contents($attached_file);
            if (is_string($content)) {
                $parts[] = $content;
            }
        }
    }

    $meta = json_decode((string) $record->meta, true);
    if (is_array($meta)) {
        if (!empty($meta['model'])) {
            $parts[] = 'Model: ' . $meta['model'];
        }
        if (!empty($meta['provider_response']) && is_array($meta['provider_response'])) {
            $parts[] = wp_json_encode($meta['provider_response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if (!empty($record->provider_file_id)) {
        $parts[] = 'Provider file id: ' . $record->provider_file_id;
    }

    return implode("\n\n", array_filter(array_map('trim', $parts)));
}

function deepseek_kb_index_manual_source($title, $content) {
    $content = deepseek_kb_normalize_text($content);
    if ($content === '') {
        return 0;
    }

    $source_id = deepseek_kb_upsert_source(
        'manual',
        0,
        $title ?: '手动知识',
        $content,
        array('content' => $content)
    );
    $chunks = deepseek_kb_index_source_content($source_id, $title ?: '手动知识', $content);
    deepseek_kb_add_job(array(
        'job_type' => 'index_manual',
        'source_type' => 'manual',
        'source_id' => $source_id,
        'status' => 'done',
        'total_items' => 1,
        'processed_items' => 1,
        'message' => '手动知识已入库，分段 ' . $chunks . ' 个。',
    ));

    return $source_id;
}

function deepseek_kb_index_post($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return 0;
    }

    $content = $post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post->post_content;
    $source_id = deepseek_kb_upsert_source(
        'post',
        $post->ID,
        get_the_title($post),
        $content,
        array(
            'post_type' => $post->post_type,
            'permalink' => get_permalink($post),
        )
    );
    deepseek_kb_index_source_content($source_id, get_the_title($post), $content);

    return $source_id;
}

function deepseek_kb_index_posts($post_types = array()) {
    $post_types = empty($post_types) ? deepseek_kb_get_post_types() : array_map('sanitize_key', (array) $post_types);
    $posts = get_posts(array(
        'post_type' => $post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $processed = 0;
    foreach ($posts as $post_id) {
        if (deepseek_kb_index_post($post_id)) {
            $processed++;
        }
    }

    deepseek_kb_add_job(array(
        'job_type' => 'index_posts',
        'source_type' => 'post',
        'status' => 'done',
        'total_items' => count($posts),
        'processed_items' => $processed,
        'message' => '已索引站内内容 ' . $processed . ' 条。',
    ));

    return $processed;
}

function deepseek_kb_maybe_index_saved_post($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!$post || !in_array($post->post_type, deepseek_kb_get_post_types(), true)) {
        return;
    }

    if ($post->post_status === 'publish') {
        deepseek_kb_index_post($post_id);
        return;
    }

    global $wpdb;
    $source_id = intval($wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . deepseek_kb_sources_table() . " WHERE source_type = %s AND object_id = %d LIMIT 1",
        'post',
        $post_id
    )));

    if ($source_id > 0) {
        $wpdb->update(
            deepseek_kb_sources_table(),
            array('status' => 'inactive', 'updated_at' => current_time('mysql')),
            array('id' => $source_id),
            array('%s', '%s'),
            array('%d')
        );
        $wpdb->update(
            deepseek_kb_chunks_table(),
            array('status' => 'inactive'),
            array('source_id' => $source_id),
            array('%s'),
            array('%d')
        );
    }
}

function deepseek_kb_index_file_records() {
    if (!function_exists('deepseek_get_file_records')) {
        return 0;
    }

    $records = deepseek_get_file_records(array(
        'status' => 'active',
        'limit' => 500,
    ));

    $processed = 0;
    foreach ($records as $record) {
        $content = deepseek_kb_extract_file_record_content($record);
        if (deepseek_kb_normalize_text($content) === '') {
            continue;
        }

        $title = $record->original_filename ?: ('文件记录 #' . $record->id);
        $source_id = deepseek_kb_upsert_source(
            'file_record',
            (int) $record->id,
            $title,
            $content,
            array(
                'provider' => $record->provider_name,
                'interface' => $record->interface_key,
                'storage_engine' => $record->storage_engine,
                'file_ext' => $record->file_ext,
            )
        );
        deepseek_kb_index_source_content($source_id, $title, $content);
        $processed++;
    }

    deepseek_kb_add_job(array(
        'job_type' => 'index_files',
        'source_type' => 'file_record',
        'status' => 'done',
        'total_items' => count($records),
        'processed_items' => $processed,
        'message' => '已索引文件记录 ' . $processed . ' 条。',
    ));

    return $processed;
}

function deepseek_kb_rebuild_all() {
    global $wpdb;

    $sources = $wpdb->get_results("SELECT * FROM " . deepseek_kb_sources_table() . " WHERE status = 'active' ORDER BY id ASC");
    $processed = 0;

    foreach ($sources as $source) {
        $content = deepseek_kb_get_source_content($source);
        if (deepseek_kb_normalize_text($content) === '') {
            continue;
        }

        deepseek_kb_index_source_content((int) $source->id, $source->title, $content);
        $processed++;
    }

    deepseek_kb_add_job(array(
        'job_type' => 'rebuild',
        'status' => 'done',
        'total_items' => count($sources),
        'processed_items' => $processed,
        'message' => '已重建知识库来源 ' . $processed . ' 条。',
    ));

    return $processed;
}

function deepseek_kb_search($query, $limit = 5) {
    global $wpdb;

    $query = deepseek_kb_normalize_text($query);
    if ($query === '') {
        return array();
    }

    $enabled_types = deepseek_kb_get_enabled_source_types();
    $terms = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
    $terms = array_values(array_filter($terms, static function ($term) {
        return mb_strlen($term, 'UTF-8') >= 2;
    }));

    if (count($terms) <= 1) {
        $compact_query = preg_replace('/\s+/u', '', mb_strtolower($query, 'UTF-8'));
        $query_length = mb_strlen($compact_query, 'UTF-8');
        for ($i = 0; $i < $query_length - 1 && count($terms) < 8; $i += 2) {
            $terms[] = mb_substr($compact_query, $i, 2, 'UTF-8');
        }
    }

    $terms = array_slice(array_values(array_unique($terms)), 0, 8);

    if (empty($terms)) {
        $terms = array($query);
    }

    $where = array('c.status = %s', 's.status = %s');
    $params = array('active', 'active');
    $type_placeholders = implode(',', array_fill(0, count($enabled_types), '%s'));
    $where[] = "s.source_type IN ({$type_placeholders})";
    $params = array_merge($params, $enabled_types);

    $like_clauses = array();
    foreach ($terms as $term) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $like_clauses[] = '(c.content LIKE %s OR c.title LIKE %s OR c.keywords LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where[] = '(' . implode(' OR ', $like_clauses) . ')';
    $params[] = max(1, intval($limit)) * 4;

    $sql = "SELECT c.*, s.source_type, s.object_id, s.title AS source_title, s.meta AS source_meta
        FROM " . deepseek_kb_chunks_table() . " c
        INNER JOIN " . deepseek_kb_sources_table() . " s ON s.id = c.source_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.id DESC
        LIMIT %d";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
    $ranked = array();

    foreach ($rows as $row) {
        $haystack = mb_strtolower($row->title . ' ' . $row->keywords . ' ' . $row->content, 'UTF-8');
        $score = 0;
        foreach ($terms as $term) {
            if ($term !== '' && mb_strpos($haystack, $term, 0, 'UTF-8') !== false) {
                $score += 2;
            }
        }
        if (mb_strpos($haystack, mb_strtolower($query, 'UTF-8'), 0, 'UTF-8') !== false) {
            $score += 5;
        }
        $row->kb_score = $score;
        $ranked[] = $row;
    }

    usort($ranked, static function ($a, $b) {
        if ($a->kb_score === $b->kb_score) {
            return intval($b->id) <=> intval($a->id);
        }
        return intval($b->kb_score) <=> intval($a->kb_score);
    });

    return array_slice($ranked, 0, max(1, intval($limit)));
}

function deepseek_kb_build_prompt_context($query, $interface = '', $page_id = 0) {
    if (!deepseek_kb_should_apply($interface, $page_id)) {
        return array('context' => '', 'sources' => array());
    }

    $limit = max(1, intval(deepseek_get_setting('deepseek_kb_result_limit', 5)));
    $rows = deepseek_kb_search($query, $limit);
    if (empty($rows)) {
        return array('context' => '', 'sources' => array());
    }

    $context_parts = array();
    $sources = array();
    foreach ($rows as $index => $row) {
        $ref = 'KB' . ($index + 1);
        $excerpt = mb_substr(deepseek_kb_normalize_text($row->content), 0, 900, 'UTF-8');
        $source_meta = json_decode((string) $row->source_meta, true);
        $source_meta = is_array($source_meta) ? $source_meta : array();
        $context_parts[] = "[{$ref}] " . $row->source_title . "\n" . $excerpt;
        $sources[] = array(
            'ref' => $ref,
            'title' => $row->source_title,
            'source_type' => $row->source_type,
            'object_id' => (int) $row->object_id,
            'url' => isset($source_meta['permalink']) ? esc_url_raw($source_meta['permalink']) : '',
        );
    }

    $prompt = "以下是站点知识库检索结果。回答用户时优先依据这些内容；如果使用了其中的信息，请在相关句子后标注 [KB1]、[KB2] 这样的引用；如果知识库没有答案，请直接说明未在知识库中找到明确依据。\n\n" . implode("\n\n", $context_parts);

    return array('context' => $prompt, 'sources' => $sources);
}

function deepseek_kb_get_stats() {
    global $wpdb;

    return array(
        'sources' => intval($wpdb->get_var("SELECT COUNT(*) FROM " . deepseek_kb_sources_table())),
        'active_sources' => intval($wpdb->get_var("SELECT COUNT(*) FROM " . deepseek_kb_sources_table() . " WHERE status = 'active'")),
        'chunks' => intval($wpdb->get_var("SELECT COUNT(*) FROM " . deepseek_kb_chunks_table())),
        'jobs' => intval($wpdb->get_var("SELECT COUNT(*) FROM " . deepseek_kb_jobs_table())),
    );
}

function deepseek_kb_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['deepseek_kb_action'])) {
        return;
    }

    check_admin_referer('deepseek_kb_admin');
    $action = sanitize_key(wp_unslash($_POST['deepseek_kb_action']));

    if ($action === 'save_settings') {
        deepseek_update_setting('deepseek_kb_enabled', isset($_POST['deepseek_kb_enabled']) ? '1' : '0', false);
        $source_types = isset($_POST['deepseek_kb_source_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['deepseek_kb_source_types'])) : array();
        $post_types = isset($_POST['deepseek_kb_post_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['deepseek_kb_post_types'])) : array();
        $interfaces = isset($_POST['deepseek_kb_interfaces']) ? array_map('sanitize_key', (array) wp_unslash($_POST['deepseek_kb_interfaces'])) : array();
        deepseek_update_setting('deepseek_kb_source_types', array_values(array_intersect($source_types, deepseek_kb_default_source_types())), false);
        deepseek_update_setting('deepseek_kb_post_types', array_values(array_filter($post_types, 'post_type_exists')), false);
        deepseek_update_setting('deepseek_kb_interfaces', array_values(array_intersect($interfaces, deepseek_get_enabled_chat_interfaces())), false);
        deepseek_update_setting('deepseek_kb_page_ids', isset($_POST['deepseek_kb_page_ids']) ? sanitize_text_field(wp_unslash($_POST['deepseek_kb_page_ids'])) : '', false);
        deepseek_update_setting('deepseek_kb_result_limit', max(1, intval($_POST['deepseek_kb_result_limit'] ?? 5)), false);
        deepseek_update_setting('deepseek_kb_chunk_size', max(300, intval($_POST['deepseek_kb_chunk_size'] ?? 900)), false);
        add_settings_error('deepseek_kb', 'settings_saved', '知识库设置已保存。', 'success');
    } elseif ($action === 'index_posts') {
        $processed = deepseek_kb_index_posts(deepseek_kb_get_post_types());
        add_settings_error('deepseek_kb', 'posts_indexed', sprintf('已索引站内内容 %d 条。', $processed), 'success');
    } elseif ($action === 'index_files') {
        $processed = deepseek_kb_index_file_records();
        add_settings_error('deepseek_kb', 'files_indexed', sprintf('已索引文件记录 %d 条。', $processed), 'success');
    } elseif ($action === 'add_manual') {
        $title = isset($_POST['manual_title']) ? sanitize_text_field(wp_unslash($_POST['manual_title'])) : '';
        $content = isset($_POST['manual_content']) ? wp_kses_post(wp_unslash($_POST['manual_content'])) : '';
        $source_id = deepseek_kb_index_manual_source($title, $content);
        if ($source_id) {
            add_settings_error('deepseek_kb', 'manual_added', '手动知识已入库。', 'success');
        } else {
            add_settings_error('deepseek_kb', 'manual_empty', '手动知识内容不能为空。', 'error');
        }
    } elseif ($action === 'rebuild_all') {
        $processed = deepseek_kb_rebuild_all();
        add_settings_error('deepseek_kb', 'rebuilt', sprintf('已重建知识库来源 %d 条。', $processed), 'success');
    }
}

function deepseek_kb_render_source_type_checks($name, $selected, $options) {
    foreach ($options as $value => $label) {
        echo '<label style="margin-right: 14px;"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($value) . '" ' . checked(in_array($value, $selected, true), true, false) . ' /> ' . esc_html($label) . '</label>';
    }
}

function deepseek_kb_render_admin_page() {
    global $wpdb;

    deepseek_kb_handle_admin_actions();
    $stats = deepseek_kb_get_stats();
    $enabled = deepseek_get_setting('deepseek_kb_enabled', '0');
    $source_types = deepseek_kb_get_enabled_source_types();
    $post_types = deepseek_kb_get_post_types();
    $kb_interfaces = deepseek_kb_get_interfaces();
    $enabled_chat_interfaces = deepseek_get_enabled_chat_interfaces();
    $public_post_types = get_post_types(array('public' => true), 'objects');
    $sources = $wpdb->get_results("SELECT * FROM " . deepseek_kb_sources_table() . " ORDER BY updated_at DESC LIMIT 20");
    $jobs = $wpdb->get_results("SELECT * FROM " . deepseek_kb_jobs_table() . " ORDER BY id DESC LIMIT 10");
    ?>
    <div class="wrap">
        <h1>知识库</h1>
        <?php settings_errors('deepseek_kb'); ?>

        <div style="display:flex; gap:16px; flex-wrap:wrap; margin:16px 0;">
            <div class="card"><strong><?php echo intval($stats['active_sources']); ?></strong><p>启用来源</p></div>
            <div class="card"><strong><?php echo intval($stats['chunks']); ?></strong><p>知识分段</p></div>
            <div class="card"><strong><?php echo intval($stats['jobs']); ?></strong><p>入库任务</p></div>
        </div>

        <h2>知识库设置</h2>
        <form method="post">
            <?php wp_nonce_field('deepseek_kb_admin'); ?>
            <input type="hidden" name="deepseek_kb_action" value="save_settings" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">对话启用知识库</th>
                    <td><label><input type="checkbox" name="deepseek_kb_enabled" value="1" <?php checked($enabled, '1'); ?> /> 启用检索增强问答</label></td>
                </tr>
                <tr>
                    <th scope="row">参与检索的来源</th>
                    <td><?php deepseek_kb_render_source_type_checks('deepseek_kb_source_types', $source_types, array('post' => '文章/页面', 'manual' => '手动知识', 'file_record' => '上传文件')); ?></td>
                </tr>
                <tr>
                    <th scope="row">站内内容类型</th>
                    <td>
                        <?php foreach ($public_post_types as $type => $object): ?>
                            <label style="margin-right:14px;"><input type="checkbox" name="deepseek_kb_post_types[]" value="<?php echo esc_attr($type); ?>" <?php checked(in_array($type, $post_types, true), true); ?> /> <?php echo esc_html($object->labels->singular_name); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">启用接口</th>
                    <td>
                        <?php foreach ($enabled_chat_interfaces as $interface): ?>
                            <label style="margin-right:14px;"><input type="checkbox" name="deepseek_kb_interfaces[]" value="<?php echo esc_attr($interface); ?>" <?php checked(empty($kb_interfaces) || in_array($interface, $kb_interfaces, true), true); ?> /> <?php echo esc_html($interface); ?></label>
                        <?php endforeach; ?>
                        <p class="description">全不选时默认对所有已启用接口生效。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">启用页面 ID</th>
                    <td>
                        <input type="text" name="deepseek_kb_page_ids" class="regular-text" value="<?php echo esc_attr(deepseek_get_setting('deepseek_kb_page_ids', '')); ?>" placeholder="留空表示所有聊天页，例如：12,18" />
                        <p class="description">只在指定页面的聊天框启用知识库；留空表示所有聊天页。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">检索结果数</th>
                    <td><input type="number" name="deepseek_kb_result_limit" value="<?php echo esc_attr(deepseek_get_setting('deepseek_kb_result_limit', 5)); ?>" min="1" max="10" /></td>
                </tr>
                <tr>
                    <th scope="row">分段长度</th>
                    <td><input type="number" name="deepseek_kb_chunk_size" value="<?php echo esc_attr(deepseek_get_setting('deepseek_kb_chunk_size', 900)); ?>" min="300" step="50" /></td>
                </tr>
            </table>
            <?php submit_button('保存知识库设置'); ?>
        </form>

        <hr />
        <h2>入库和重建</h2>
        <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
            <?php wp_nonce_field('deepseek_kb_admin'); ?>
            <button class="button button-primary" name="deepseek_kb_action" value="index_posts">索引站内文章/页面</button>
            <button class="button" name="deepseek_kb_action" value="index_files">索引上传文件</button>
            <button class="button" name="deepseek_kb_action" value="rebuild_all">重建全部来源</button>
        </form>

        <h2>新增手动知识</h2>
        <form method="post">
            <?php wp_nonce_field('deepseek_kb_admin'); ?>
            <input type="hidden" name="deepseek_kb_action" value="add_manual" />
            <p><input type="text" name="manual_title" class="regular-text" placeholder="标题" /></p>
            <p><textarea name="manual_content" rows="8" class="large-text" placeholder="输入要加入知识库的内容"></textarea></p>
            <?php submit_button('加入知识库'); ?>
        </form>

        <h2>最近来源</h2>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>类型</th><th>标题</th><th>状态</th><th>最后入库</th></tr></thead>
            <tbody>
                <?php if ($sources): foreach ($sources as $source): ?>
                    <tr>
                        <td><?php echo intval($source->id); ?></td>
                        <td><?php echo esc_html($source->source_type); ?></td>
                        <td><?php echo esc_html($source->title); ?></td>
                        <td><?php echo esc_html($source->status); ?></td>
                        <td><?php echo esc_html($source->last_indexed_at ?: '-'); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5">暂无知识来源。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>最近任务</h2>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>任务</th><th>状态</th><th>进度</th><th>说明</th><th>时间</th></tr></thead>
            <tbody>
                <?php if ($jobs): foreach ($jobs as $job): ?>
                    <tr>
                        <td><?php echo intval($job->id); ?></td>
                        <td><?php echo esc_html($job->job_type); ?></td>
                        <td><?php echo esc_html($job->status); ?></td>
                        <td><?php echo intval($job->processed_items); ?> / <?php echo intval($job->total_items); ?></td>
                        <td><?php echo esc_html($job->message); ?></td>
                        <td><?php echo esc_html($job->updated_at); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">暂无任务。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function deepseek_kb_add_admin_menu() {
    add_submenu_page(
        'deepseek',
        '知识库',
        '知识库',
        'manage_options',
        'deepseek-kb',
        'deepseek_kb_render_admin_page'
    );
}

function deepseek_kb_cleanup_schema() {
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_kb_chunks_table());
    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_kb_sources_table());
    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_kb_jobs_table());
    delete_option('deepseek_kb_schema_version');
}

deepseek_kb_bootstrap_schema();
add_action('admin_menu', 'deepseek_kb_add_admin_menu', 20);
add_action('save_post', 'deepseek_kb_maybe_index_saved_post', 20, 3);
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_kb_activate_schema');
