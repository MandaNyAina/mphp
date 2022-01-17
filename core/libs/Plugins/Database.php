<?php

namespace Core\Lib\Plugins;
use Core\Interfaces\DatabaseInterface;
use PDO;
use Exception;
use Core\Lib\Main\MphpCore as MPHP;

/**
 * Create a database instance
 * @param string $driver, string $host, string $dbname, $port, $user, $password <p>
 * The database link information
 * </p>
 * @return string $database, and a new instance of database
 */
class Database implements DatabaseInterface {
    private static ?PDO $instance = null;

    public function __construct(string $driver, string $host, string $dbname, int $port, string $user, string $password) {
        try {
            if (self::$instance === null) {
                switch ($driver) {
                    case 'mysql':
                        self::$instance = new PDO("mysql:host=$host;dbname=$dbname;port=$port", "$user", "$password");
                        break;

                    case 'pg':
                        self::$instance = new PDO("pgsql:host=$host;dbname=$dbname;port=$port", "$user", "$password");
                        break;

                    default:
                        MPHP::show_error_server(500, "Database error => Unknown driver {@$driver}");
                        break;
                }
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (\PDOException $e) {
            MPHP::show_error_server(500, "Database connection error => $e");
        }
    }

    /**
     * Clean up the query before executing
     * @param string $query <p>
     * The input is a string
     * </p>
     * @return string, return a clean query for security reason
     */
    private function cleanQuerySecurity(string $query): string {
        $str = trim($query);
        $str = stripslashes($str);
        $str = strip_tags($str);
        return htmlspecialchars($str);
    }

    /**
     * Execute a query from a string value
     * @param string $query <p>
     * The input is a string
     * </p>
     * @return bool, return the result of the query
     */
    public function execute(string $query): bool {
        try {
            return self::$instance->exec($this->cleanQuerySecurity($query));
        } catch (Exception $e) {
            MPHP::show_error_server(500, "Query execution error => $e");
            return false;
        }
    }

    /**
     * Select on table
     * @param string $table, string $value = "*", string $cond = null
     * <p>
     * $table : the table name
     * </p>
     * <p>
     * $value : the selector, the default value is "*"
     * </p>
     * <p>
     * $cond : the condition of the select table
     * </p>
     * @return bool | array , return the result of the select query
     */
    public function select(string $table, string $value = "*", string $cond = null): array {
        $result = [];
        $args = "";
        if ($cond !== null) {
            $args = "WHERE $cond";
        }
        $query = $this->cleanQuerySecurity("SELECT $value FROM $table $args");
        try {
            $value = self::$instance->prepare($query);
            $value->execute();
            $result = $value->fetchAll(PDO::FETCH_CLASS);
            $value->closeCursor();
        } catch (Exception $e) {
            MPHP::show_error_server(500, "Select query execution error => $e");
        }
        return $result;
    }

    /**
     * Insert on table
     * @param string $table, array $data
     * <p>
     * $table : the table name
     * </p>
     * <p>
     * $data : this list of the element need to insert
     * </p>
     * @return bool , inserted data
     */
    public function insert(string $table, array $data): bool {
        if (count($data)) {
            $name = $value = '';
            $i = 0;
            foreach ($data as $k => $v) {
                if ($i > 0) {
                    $value .= ", ";
                    $name .= ", ";
                }
                $value .= ":$k";
                $name .= "$k";
                $i++;
            }
            $query = $this->cleanQuerySecurity("INSERT INTO $table ($name) VALUES ($value)");
            try {
                self::$instance->prepare($query)->execute($data);
                return true;
            } catch (Exception $e) {
                MPHP::show_error_server(500, "Insert query execution error => $e");
            }
        } else {
            MPHP::show_error_server(500, "Can not insert empty data");
        }
        return false;
    }

    /**
     * Update on table
     * @param string $table, array $data, string $cond
     * <p>
     * $table : the table name
     * </p>
     * <p>
     * $data : this list of the element need to insert
     * </p>
     * <p>
     * $cond : this is the condition of update query
     * </p>
     * @return bool , updated data
     */
    public function update(string $table, array $data, string $cond): bool {
        $value = '';
        $i = 0;
        foreach ($data as $k => $v) {
            if ($i > 0) {
                $value .= ", ";
            }
            $value .= "$k= :$k";
            $i++;
        }
        $query = $this->cleanQuerySecurity("UPDATE $table SET $value WHERE $cond");
        try {
            self::$instance->prepare($query)->execute($data);
            return true;
        } catch (Exception $e) {
            MPHP::show_error_server(500, "Update query execution error => $e");
        }
        return false;
    }

    /**
     * Deleted on table
     * @param string $table, string $cond = null
     * <p>
     * $table : the table name
     * </p>
     * <p>
     * $cond : this is the condition of delete query
     * </p>
     * @return bool , deleted data
     */
    public function delete(string $table, string $cond = null): bool {
        $args = '';
        if ($cond !== null) {
            $args = "WHERE $cond";
        }
        $query = $this->cleanQuerySecurity("DELETE FROM $table $args");
        try {
            self::$instance->exec($query);
            return true;
        } catch (Exception $e) {
            MPHP::show_error_server(500, "Delete query execution error => $e");
        }
        return false;
    }

    /**
     * Deleted on table
     * @param string $table, string $cond = null
     * <p>
     * $table : the table name
     * </p>
     * <p>
     * $value : the selector of get value
     * </p>
     * @return array , the result of the query
     */
    public function getLastRow(string $table, string $value = "*"): array {
        $query = $this->select($table, $value);
        try {
            return array_map(function($k, $v) { return [ $k => $v ]; }, $query);
        } catch (Exception $e) {
            MPHP::show_error_server(500, "Get last data query execution error => $e");
        }
        return [];
    }

}
