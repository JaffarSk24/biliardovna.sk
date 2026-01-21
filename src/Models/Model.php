<?php
namespace App\Models;

use PDO;
use App\Database\Database;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findBy(string $column, $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function all(array $conditions = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    // Handle IN clause for arrays
                    $placeholders = [];
                    foreach ($value as $i => $val) {
                        $placeholder = ":{$key}_{$i}";
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = $val;
                    }
                    $where[] = "{$key} IN (" . implode(', ', $placeholders) . ")";
                } else {
                    // Regular equality
                    $where[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn ($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "{$key} = :{$key}";
        }

        $sql = sprintf(
            "UPDATE {$this->table} SET %s WHERE {$this->primaryKey} = :id",
            implode(', ', $sets)
        );

        $data['id'] = $id;
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}