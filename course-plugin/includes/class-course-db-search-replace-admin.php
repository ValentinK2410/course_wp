<?php
/**
 * Админка: поиск и замена строки во всех текстовых полях таблиц БД WordPress.
 *
 * Учитывает сериализованные значения (распаковка → замена в строках и массивах → serialize).
 *
 * @copyright Copyright (c) 2024 Кузьменко Валентин (Valentink2410)
 */

if (! defined('ABSPATH')) {
    exit;
}

class Course_DB_Search_Replace_Admin {

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu() {
        add_management_page(
            __('Замена в базе данных', 'course-plugin'),
            __('Замена в БД', 'course-plugin'),
            'manage_options',
            'course-db-search-replace',
            array($this, 'render_page')
        );
    }

    /**
     * Страница инструмента.
     */
    public function render_page() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Недостаточно прав.', 'course-plugin'));
        }

        $report  = null;
        $message = '';

        if (isset($_POST['course_db_sr_do']) && check_admin_referer('course_db_search_replace', 'course_db_sr_nonce')) {
            $search      = isset($_POST['course_db_sr_search']) ? wp_unslash($_POST['course_db_sr_search']) : '';
            $replace     = isset($_POST['course_db_sr_replace']) ? wp_unslash($_POST['course_db_sr_replace']) : '';
            $dry         = ! empty($_POST['course_db_sr_dry']);
            $all_tables  = ! empty($_POST['course_db_sr_all_tables']);
            $batch       = isset($_POST['course_db_sr_batch']) ? max(50, min(2000, absint($_POST['course_db_sr_batch']))) : 500;

            if ($search === '') {
                $message = '<div class="notice notice-error"><p>' . esc_html__('Укажите строку для поиска.', 'course-plugin') . '</p></div>';
            } else {
                @set_time_limit(300);
                @ini_set('max_execution_time', '300');
                $report = $this->run_replace($search, $replace, $dry, $all_tables, $batch);
                if ($dry) {
                    $message = '<div class="notice notice-info"><p>' . esc_html__('Режим просмотра: изменения не записывались.', 'course-plugin') . '</p></div>';
                } else {
                    $message = '<div class="notice notice-success"><p>' . esc_html__('Замена выполнена.', 'course-plugin') . '</p></div>';
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Замена строк в базе данных', 'course-plugin'); ?></h1>

            <div class="notice notice-warning" style="margin-top:1rem;">
                <p><strong><?php esc_html_e('Перед запуском сделайте резервную копию базы данных.', 'course-plugin'); ?></strong></p>
                <p><?php esc_html_e('Инструмент обходит текстовые столбцы выбранных таблиц. Сериализованные массивы в wp_options и метаполях обрабатываются с пересборкой сериализации. Объекты внутри сериализованных данных не поддерживаются — такие ячейки пропускаются.', 'course-plugin'); ?></p>
            </div>

            <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed HTML notices ?>

            <form method="post" action="" style="max-width:920px;margin-top:1.5rem;">
                <?php wp_nonce_field('course_db_search_replace', 'course_db_sr_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="course_db_sr_search"><?php esc_html_e('Найти', 'course-plugin'); ?></label></th>
                        <td>
                            <textarea name="course_db_sr_search" id="course_db_sr_search" class="large-text code" rows="4" required/><?php echo isset($_POST['course_db_sr_search']) ? esc_textarea(wp_unslash($_POST['course_db_sr_search'])) : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('Точная подстрока (без регулярных выражений).', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_db_sr_replace"><?php esc_html_e('Заменить на', 'course-plugin'); ?></label></th>
                        <td>
                            <textarea name="course_db_sr_replace" id="course_db_sr_replace" class="large-text code" rows="3"><?php echo isset($_POST['course_db_sr_replace']) ? esc_textarea(wp_unslash($_POST['course_db_sr_replace'])) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Режим', 'course-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="course_db_sr_dry" value="1" <?php checked(! isset($_POST['course_db_sr_do']) || ! empty($_POST['course_db_sr_dry'])); ?> />
                                <?php esc_html_e('Только подсчёт совпадений (без записи в БД)', 'course-plugin'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Таблицы', 'course-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="course_db_sr_all_tables" value="1" <?php checked(! empty($_POST['course_db_sr_all_tables'])); ?> />
                                <?php esc_html_e('Все таблицы в базе (не только с префиксом WordPress)', 'course-plugin'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('По умолчанию обрабатываются только таблицы с текущим префиксом. Опасно на общей БД.', 'course-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="course_db_sr_batch"><?php esc_html_e('Размер пакета строк', 'course-plugin'); ?></label></th>
                        <td>
                            <input name="course_db_sr_batch" id="course_db_sr_batch" type="number" min="50" max="2000" step="50" value="<?php echo isset($_POST['course_db_sr_batch']) ? absint($_POST['course_db_sr_batch']) : 500; ?>" />
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="course_db_sr_do" class="button button-primary" value="1"><?php esc_html_e('Выполнить', 'course-plugin'); ?></button>
                </p>
            </form>

            <?php if ($report !== null && is_array($report)) : ?>
                <h2><?php esc_html_e('Отчёт', 'course-plugin'); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: 1: tables count, 2: rows scanned, 3: cells changed or matches */
                        esc_html__('Таблиц: %1$d. Ячеек обработано: %2$d. %3$s', 'course-plugin'),
                        (int) $report['tables'],
                        (int) $report['cells'],
                        ! empty($report['dry'])
                            ? sprintf(esc_html__('Совпадений: %d.', 'course-plugin'), (int) $report['matches'])
                            : sprintf(esc_html__('Ячеек с заменой: %d.', 'course-plugin'), (int) $report['updated'])
                    );
                    ?>
                </p>
                <?php if (! empty($report['details'])) : ?>
                    <table class="widefat striped" style="max-width:920px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Таблица', 'course-plugin'); ?></th>
                                <th><?php esc_html_e('Столбец', 'course-plugin'); ?></th>
                                <th><?php esc_html_e('Совпадений / обновлений', 'course-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['details'] as $row) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($row['table']); ?></code></td>
                                    <td><code><?php echo esc_html($row['column']); ?></code></td>
                                    <td><?php echo esc_html((string) $row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param string $search
     * @param string $replace
     * @param bool   $dry
     * @param bool   $all_tables
     * @param int    $batch
     * @return array{tables:int,cells:int,matches:int,updated:int,dry:bool,details:array<int,array{table:string,column:string,count:int}>}
     */
    private function run_replace($search, $replace, $dry, $all_tables, $batch) {
        global $wpdb;

        /** @var array<string> $skip */
        $skip = apply_filters('course_db_search_replace_skip_tables', array());
        $skip = is_array($skip) ? array_flip($skip) : array();

        if ($all_tables) {
            $tables = $wpdb->get_col('SHOW TABLES', 0);
        } else {
            $like   = $wpdb->esc_like($wpdb->prefix) . '%';
            $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like), 0);
        }

        if (! is_array($tables)) {
            $tables = array();
        }

        $tables = apply_filters('course_db_search_replace_tables', $tables, $search, $replace, $dry);
        if (! is_array($tables)) {
            $tables = array();
        }

        $report = array(
            'tables'  => 0,
            'cells'   => 0,
            'matches' => 0,
            'updated' => 0,
            'dry'     => $dry,
            'details' => array(),
        );

        $search_escaped = $wpdb->esc_like($search);
        $like_pattern   = '%' . $search_escaped . '%';

        foreach ($tables as $table) {
            if (isset($skip[ $table ])) {
                continue;
            }
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }

            $pk_cols = $this->get_primary_key_columns($table);
            if (empty($pk_cols)) {
                continue;
            }

            $text_cols = $this->get_text_columns($table);
            if (empty($text_cols)) {
                continue;
            }

            ++$report['tables'];

            foreach ($text_cols as $col) {
                if (! preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    continue;
                }

                if ($dry) {
                    $count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE %s",
                            $like_pattern
                        )
                    );
                    if ($count > 0) {
                        $report['details'][] = array(
                            'table'  => $table,
                            'column' => $col,
                            'count'  => $count,
                        );
                        $report['matches'] += $count;
                        $report['cells']  += $count;
                    }
                    continue;
                }

                $col_updates = 0;
                $cells_seen  = 0;

                while (true) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column and table names validated.
                    $pk_select = '`' . implode('`, `', $pk_cols) . '`';
                    $sql       = "SELECT {$pk_select}, `{$col}` AS __sr_col FROM `{$table}` WHERE `{$col}` LIKE %s LIMIT %d";
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $rows = $wpdb->get_results($wpdb->prepare($sql, $like_pattern, $batch), ARRAY_A);

                    if (empty($rows)) {
                        break;
                    }

                    foreach ($rows as $row) {
                        ++$cells_seen;
                        $old_val = isset($row['__sr_col']) ? (string) $row['__sr_col'] : '';
                        unset($row['__sr_col']);

                        if ($old_val === '' || strpos($old_val, $search) === false) {
                            continue;
                        }

                        $new_val = $this->smart_replace_in_string($old_val, $search, $replace);
                        if ($new_val === $old_val) {
                            continue;
                        }

                        $where = array();
                        foreach ($pk_cols as $pk) {
                            $where[ $pk ] = $row[ $pk ];
                        }

                        $where_format = array_fill(0, count($where), '%s');
                        $ok           = $wpdb->update(
                            $table,
                            array( $col => $new_val ),
                            $where,
                            array('%s'),
                            $where_format
                        );
                        if (false !== $ok) {
                            ++$col_updates;
                        }
                    }

                    if (count($rows) < $batch) {
                        break;
                    }
                }

                if ($cells_seen > 0) {
                    $report['details'][] = array(
                        'table'  => $table,
                        'column' => $col,
                        'count'  => $col_updates,
                    );
                    $report['cells']   += $cells_seen;
                    $report['updated'] += $col_updates;
                }
            }
        }

        return $report;
    }

    /**
     * @param string $table
     * @return string[]
     */
    private function get_primary_key_columns($table) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'", ARRAY_A);
        if (empty($rows) || ! is_array($rows)) {
            return array();
        }
        $cols = array();
        foreach ($rows as $r) {
            if (! empty($r['Column_name']) && preg_match('/^[a-zA-Z0-9_]+$/', $r['Column_name'])) {
                $cols[] = $r['Column_name'];
            }
        }

        return $cols;
    }

    /**
     * Текстовые столбцы, без бинарных BLOB.
     *
     * @param string $table
     * @return string[]
     */
    private function get_text_columns($table) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cols = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
        if (empty($cols) || ! is_array($cols)) {
            return array();
        }
        $out = array();
        foreach ($cols as $c) {
            if (empty($c['Field']) || ! preg_match('/^[a-zA-Z0-9_]+$/', $c['Field'])) {
                continue;
            }
            $type = isset($c['Type']) ? strtolower((string) $c['Type']) : '';
            if (preg_match('/^(varchar|char|text|mediumtext|longtext|tinytext|enum|set)\b/', $type)) {
                $out[] = $c['Field'];
            }
        }

        return $out;
    }

    /**
     * Замена с учётом сериализации PHP.
     *
     * @param string $value
     * @param string $search
     * @param string $replace
     * @return string
     */
    private function smart_replace_in_string($value, $search, $replace) {
        if (! is_string($value) || $value === '' || strpos($value, $search) === false) {
            return $value;
        }

        if (is_serialized($value)) {
            $data = @unserialize($value, array('allowed_classes' => false));
            if ($data === false && $value !== serialize(false)) {
                return str_replace($search, $replace, $value);
            }
            if (is_object($data)) {
                return $value;
            }
            $data  = $this->replace_in_mixed($data, $search, $replace);
            $again = serialize($data);
            return is_string($again) ? $again : $value;
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * @param mixed  $data
     * @param string $search
     * @param string $replace
     * @return mixed
     */
    private function replace_in_mixed($data, $search, $replace) {
        if (is_string($data)) {
            return str_replace($search, $replace, $data);
        }
        if (is_array($data)) {
            $out = array();
            foreach ($data as $k => $v) {
                $out[ $k ] = $this->replace_in_mixed($v, $search, $replace);
            }
            return $out;
        }

        return $data;
    }
}
