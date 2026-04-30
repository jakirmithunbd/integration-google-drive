<?php

namespace CodeConfig\IGD\Models;

use Exception;
use WP_Error;

/**
 * Base Model Class for Integration Google Drive Plugin
 *
 * This abstract class provides common database operations and utilities
 * for all model classes in the Integration Google Drive plugin.
 *
 * Features:
 * - CRUD operations (Create, Read, Update, Delete)
 * - Database error handling with WP_Error
 * - Input validation and sanitization
 * - Pagination utilities
 * - Account validation
 * - Protection against cloning and serialization
 *
 * @package CodeConfig\IGD\Models
 * @since 1.0.0
 */
abstract class BaseModel
{
    public const MAX_ITEMS_PER_PAGE = 1000;
    /**
     * WordPress database object
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Table name for the model
     * @var string
     */
    protected $tableName;

    /**
     * Constructor to initialize the model with database access
     * @param string $tableSuffix Table suffix specific to the model
     */
    public function __construct($tableSuffix)
    {
        global $wpdb;
        $this->wpdb      = $wpdb;
        $this->tableName = "{$wpdb->prefix}$tableSuffix";
    }

    /**
     * Disallow cloning of this class
     *
     * @throws Exception
     */
    public function __clone()
    {
        throw new Exception(esc_html__('Clone is not allowed.', 'integration-google-drive'));
    }

    /**
     * Disallow serialization of this class
     *
     * @throws Exception
     */
    public function __sleep()
    {
        throw new Exception(esc_html__('Serialization is forbidden.', 'integration-google-drive'));
    }

    /**
     * Disallow deserialization of this class
     *
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception(esc_html__('Deserialization is forbidden.', 'integration-google-drive'));
    }

    /**
     * Returns the number of rows in the table
     *
     * @return int|WP_Error The number of rows in the table, or a WP_Error if a database error occurred
     */
    public function count($where = [])
    {
        if (empty($where)) {
            $sql    = $this->wpdb->prepare("SELECT COUNT(*) FROM %i", $this->tableName);
        } else {
            $sql    = $this->wpdb->prepare("SELECT COUNT(*) FROM %i WHERE 1=1", $this->tableName);

            foreach ($where as $field => $value) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                    return new WP_Error(
                        400,
                        __('Invalid field name provided.', 'integration-google-drive')
                    );
                }

                $sql      .= $this->wpdb->prepare(" AND %i = %s", $field, $value);
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->get_var($sql);

        if ($this->wpdb->last_error) {
            return new WP_Error(
                400,
                __('A database error occurred: ', 'integration-google-drive') . $this->wpdb->last_error
            );
        }

        return (int) $result;
    }

    /**
     * Insert a record into the database
     *
     * @param array $data The data to insert
     * @param array $format Array of formats to be mapped to each of the value in $data
     * @param string $output Output type: 'bool', ARRAY_A, ARRAY_N, or OBJECT
     * @return bool|array|object|WP_Error
     */
    protected function insert(array $data, array $format, $output = 'bool')
    {
        global $wpdb;
        if (empty($data)) {
            return new WP_Error(400, __('Data cannot be empty for insert operation.', 'integration-google-drive'));
        }

        $allowedOutputs = ['bool', ARRAY_A, ARRAY_N, OBJECT];
        if (!in_array($output, $allowedOutputs, true)) {
            $output = 'bool';
        }

        $inserted = $this->wpdb->insert($this->tableName, $data, $format);

        if ($this->wpdb->last_error) {
            return new WP_Error(400, __('A database error occurred: ', 'integration-google-drive') . $this->wpdb->last_error);
        }

        if (!$inserted) {
            return new WP_Error(500, __('Failed to insert data into database.', 'integration-google-drive'));
        }

        if ($output === 'bool') {
            return true;
        }

        $insertedId = $this->wpdb->insert_id;
        if (is_int($insertedId) && $insertedId > 0) {

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $this->tableName, $insertedId), $output);
        }

        return $inserted;
    }

    /**
     * Update a record in the database
     *
     * @param array $data The data to update
     * @param array $where Array of where conditions for the update
     * @param array $format Array of formats to be mapped to each of the value in $data
     * @param array $where_format Array of formats to be mapped to each of the values in $where
     * @param string $output Output type: 'bool', ARRAY_A, ARRAY_N, or OBJECT
     * @return bool|array|object|WP_Error
     */
    protected function update(array $data, array $where, array $format, array $where_format, $output = 'bool')
    {
        global $wpdb;
        if (empty($data)) {
            return new WP_Error(400, __('Data cannot be empty for update operation.', 'integration-google-drive'));
        }

        if (empty($where)) {
            return new WP_Error(400, __('Where conditions cannot be empty for update operation.', 'integration-google-drive'));
        }

        $allowedOutputs = ['bool', ARRAY_A, ARRAY_N, OBJECT];
        if (!in_array($output, $allowedOutputs, true)) {
            $output = 'bool';
        }

        $updated = $this->wpdb->update($this->tableName, $data, $where, $format, $where_format);

        if ($this->wpdb->last_error) {
            return new WP_Error(400, __('A database error occurred: ', 'integration-google-drive') . $this->wpdb->last_error);
        }

        if ($updated === false) {
            return new WP_Error(500, __('Failed to update data in database.', 'integration-google-drive'));
        }

        if ($output === 'bool') {
            return $updated > 0;
        }

        if ($updated > 0 && isset($where['id']) && is_int($where['id']) && $where['id'] > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id = %d", $this->tableName, $where['id']), $output);
        }

        return $updated;
    }

    /**
     * Delete records from the database.
     *
     * This method supports both associative arrays for conditions and raw SQL strings with placeholders.
     * The method will automatically determine which approach to use.
     *
     * @param array|string $where The conditions to delete. Can be an associative array or a raw SQL string with placeholders.
     * @param array $where_format The formats for the values in $where.
     * @param bool $allowAll Whether to allow deletion of all records without a WHERE clause. Defaults to false.
     * @return int|WP_Error The number of rows deleted if successful, or a WP_Error object on failure.
     */
    protected function delete($where = [], $where_format = [], $allowAll = false)
    {
        if (is_array($where) && !empty($where)) {

            foreach (array_keys($where) as $field) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                    return new WP_Error(
                        'invalid_field',
                        __('Invalid field name provided.', 'integration-google-drive')
                    );
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->delete($this->tableName, $where, $where_format);
        } elseif (empty($where) && $allowAll) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $this->wpdb->query($this->wpdb->prepare("DELETE FROM %i", $this->tableName));
        } else {
            return new WP_Error(
                'no_where_clause',
                __('Delete operation blocked: WHERE clause is required.', 'integration-google-drive')
            );
        }

        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        if ($result === false) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete data from database.', 'integration-google-drive')
            );
        }

        return (int) $result;
    }

    protected function deleteCustom($whereClause, $data = [])
    {
        if (empty($whereClause)) {
            return new WP_Error(
                'no_where_clause',
                __('WHERE clause is required.', 'integration-google-drive')
            );
        }

        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql    = $this->wpdb->prepare("DELETE FROM %i WHERE %s", $this->tableName, $whereClause);
        $params = array_merge([$this->tableName], $data);
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $this->wpdb->query($this->wpdb->prepare($sql, $params));

        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        if ($result === false) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete data from database.', 'integration-google-drive')
            );
        }

        return (int) $result;
    }

    /**
     * Fetch multiple records from the database
     *
     * @param string $sql The SQL query to execute
     * @param array $args Array of arguments for prepared statement
     * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N
     * @return array|WP_Error
     */
    protected function fetchAll($sql, array $args = [], $output = OBJECT)
    {
        $allowedOutputs = [OBJECT, ARRAY_A, ARRAY_N];
        if (!in_array($output, $allowedOutputs, true)) {
            $output = OBJECT;
        }

        if (empty($sql)) {
            return new WP_Error(
                'empty_sql',
                __('SQL query cannot be empty.', 'integration-google-drive')
            );
        }

        // Basic SQL validation
        if (preg_match('/;\s*\w+/i', $sql)) {
            return new WP_Error(
                'invalid_sql',
                __('Multiple SQL statements are not allowed.', 'integration-google-drive')
            );
        }

        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $this->wpdb->get_results($this->wpdb->prepare($sql, $args), $output);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $this->wpdb->get_results($sql, $output);
        }

        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        if ($result === null) {
            return new WP_Error(
                'query_failed',
                __('Failed to execute database query.', 'integration-google-drive')
            );
        }

        return $result;
    }

    protected function fetchWhere($where = [], $orderBy = '', $limit = null, $output = OBJECT)
    {
        $sql    = $this->wpdb->prepare("SELECT * FROM %i WHERE 1=1", $this->tableName);

        foreach ($where as $field => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                return new WP_Error(
                    'invalid_field',
                    __('Invalid field name provided.', 'integration-google-drive')
                );
            }

            $sql      .= $this->wpdb->prepare(" AND %i = %s", $field, $value);
        }

        if (!empty($orderBy)) {
            if (preg_match('/^[a-zA-Z0-9_]+(\s+(ASC|DESC))?$/i', $orderBy)) {
                $sql .= $this->wpdb->prepare(' ORDER BY %s', $orderBy);
            }
        }

        if ($limit !== null && is_int($limit) && $limit > 0) {
            $sql      .= $this->wpdb->prepare(" LIMIT %d", $limit);
        }

        return $this->fetchAll($sql, [], $output);
    }

    /**
     * Fetch a single record from the database
     *
     * @param string $sql The SQL query to execute
     * @param array $args Array of arguments for prepared statement
     * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N
     * @return object|array|null|WP_Error
     */
    protected function fetch($sql, array $args = [], $output = OBJECT)
    {
        $allowedOutputs = [OBJECT, ARRAY_A, ARRAY_N];

        if (!in_array($output, $allowedOutputs, true)) {
            $output = OBJECT;
        }

        if (empty($sql)) {
            return new WP_Error(
                'empty_sql',
                __('SQL query cannot be empty.', 'integration-google-drive')
            );
        }

        // Basic SQL validation - prevent multiple statements
        if (preg_match('/;\s*\w+/i', $sql)) {
            return new WP_Error(
                'invalid_sql',
                __('Multiple SQL statements are not allowed.', 'integration-google-drive')
            );
        }

        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $this->wpdb->get_row($this->wpdb->prepare($sql, $args), $output);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $this->wpdb->get_row($sql, $output);
        }

        // Check for database errors
        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        return $result;
    }

    /**
     * Check if an account exists and is valid (not lost)
     *
     * @param string $id The account ID to validate
     * @return bool True if account is valid, false otherwise
     */
    protected function isValidAccount($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        $exists = $this->exists([
            'id'   => $id,
            'lost' => 0
        ], $this->wpdb->prefix . 'integration_google_drive_accounts');

        if (is_wp_error($exists)) {
            return false;
        }

        return $exists;
    }

    /**
     * Get the table name for this model
     *
     * @return string The full table name including prefix
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Check if a record exists based on given conditions
     *
     * @param array $where Array of where conditions
     * @param string|null $tableName Optional table name to check against, defaults to the model's table
     * @return bool|WP_Error True if record exists, false if not, WP_Error on database error
     */
    protected function exists(array $where, $tableName = null)
    {
        if (empty($where)) {
            return new WP_Error(
                'empty_where',
                __('Where conditions cannot be empty for exists check.', 'integration-google-drive')
            );
        }

        $tableName = empty($tableName) ? $this->tableName : $tableName;

        $sql    = $this->wpdb->prepare("SELECT 1 FROM %i WHERE 1=1", $tableName);

        foreach ($where as $column => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                return new WP_Error(
                    'invalid_column',
                    __('Invalid column name provided.', 'integration-google-drive')
                );
            }

            $sql      .= $this->wpdb->prepare(" AND %i = %s", $column, $value);
        }

        $sql .= $this->wpdb->prepare(' LIMIT %d', 1);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $this->wpdb->get_var($sql);

        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        return !is_null($result);
    }

    /**
     * Get a single column value from the first matching record
     *
     * @param string $column The column to retrieve
     * @param array $where Array of where conditions
     * @return mixed|WP_Error The column value or WP_Error on error
     */
    protected function getColumn($column, array $where)
    {
        if (empty($column)) {
            return new WP_Error(
                'empty_column',
                __('Column name cannot be empty.', 'integration-google-drive')
            );
        }

        if (empty($where)) {
            return new WP_Error(
                'empty_where',
                __('Where conditions cannot be empty.', 'integration-google-drive')
            );
        }

        // Validate column name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return new WP_Error(
                'invalid_column',
                __('Invalid column name provided.', 'integration-google-drive')
            );
        }

        $sql    = $this->wpdb->prepare("SELECT %i FROM %i WHERE 1=1", $column, $this->tableName);

        foreach ($where as $col => $value) {
            // Validate column name
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                return new WP_Error(
                    'invalid_column',
                    __('Invalid column name provided.', 'integration-google-drive')
                );
            }

            $sql      .= $this->wpdb->prepare(" AND %i = %d", $col, $value);
        }

        $sql .= $this->wpdb->prepare(" LIMIT %d", 1);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $this->wpdb->get_var($sql);

        if ($this->wpdb->last_error) {
            return new WP_Error(
                'db_error',
                sprintf(
                    'A database error occurred: %s',
                    $this->wpdb->last_error
                )
            );
        }

        return $result;
    }

    /**
     * Sanitize and validate order direction
     *
     * @param string $order The order direction (ASC or DESC)
     * @return string Valid order direction
     */
    protected function sanitizeOrder($order)
    {
        $order = strtoupper(trim($order));

        return in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
    }

    /**
     * Sanitize and validate order by column
     *
     * @param string $orderBy The column to order by
     * @param array $allowedColumns Array of allowed column names
     * @return string Valid column name or default
     */
    protected function sanitizeOrderBy($orderBy, array $allowedColumns)
    {
        $orderBy = trim($orderBy);

        return in_array($orderBy, $allowedColumns, true) ? $orderBy : (isset($allowedColumns[0]) ? $allowedColumns[0] : 'id');
    }

    /**
     * Sanitize pagination parameters
     *
     * @param int $page The page number
     * @param int $perPage Items per page
     * @return array Sanitized pagination parameters
     */
    protected function sanitizePagination($page, $perPage)
    {
        $page    = max(1, (int) $page);
        $perPage = max(0, min(1000, (int) $perPage)); // Limit to prevent memory issues
        $offset  = ($page - 1) * $perPage;

        return [
            'page'    => $page,
            'perPage' => $perPage,
            'offset'  => $offset
        ];
    }

    /**
     * Batch insert multiple records
     *
     * @param array $data Array of arrays, each containing data for one record
     * @param array $format Array of formats for the data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    protected function batchInsert(array $data, array $format)
    {
        if (empty($data)) {
            return new WP_Error(400, __('Data cannot be empty for batch insert operation.', 'integration-google-drive'));
        }

        $success_count = 0;
        $total_count   = count($data);

        foreach ($data as $record) {
            if (!is_array($record)) {
                continue;
            }

            $result = $this->insert($record, $format);
            if (!is_wp_error($result) && $result) {
                $success_count++;
            }
        }

        if ($success_count === 0) {
            return new WP_Error(500, __('Failed to insert any records in batch operation.', 'integration-google-drive'));
        }

        return $success_count === $total_count;
    }
}
