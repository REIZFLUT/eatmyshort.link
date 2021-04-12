<?php

namespace Continy\Core;

use stdClass;

class Collection
{

    private $table;
    private $fields;
    private $modelName;

    private $result;

    public function __construct(string $modelName)
    {
        $fullModelName = 'App\\Models\\' . $modelName;
        $model = new $fullModelName(true);
        $this->modelName = $fullModelName;
        $this->table = $model->getTable();
        $this->fields = $model->getFields();
    }

    public function searchLikeOr(
        string $search_key,
        array $search_fields,
        bool $paginated = false,
        int $resultsPerPage = 10,
        string $pagination_key = 'p',
        string $inject = ''
    ) {

        $currentPage = (isset($_GET[$pagination_key])) ? intval($_GET[$pagination_key]) : 1;
        $searchTerm  = isset($_GET[$search_key]) ? '%' . filter_var($_GET[$search_key], FILTER_SANITIZE_STRING) . '%' : '%';

        $fields = implode(', ', $this->fields);
        $query = 'SELECT ' . $fields . ' FROM ' . $this->table . ' WHERE ';
        $cuery = 'SELECT COUNT(*) as n FROM ' . $this->table . ' WHERE ';
        $searchTerms = [];
        foreach ($search_fields as $sf) {
            $query .= $sf . ' LIKE ? OR ';
            $cuery .= $sf . ' LIKE ? OR ';
            $searchTerms[] = $searchTerm;
        }

        $query = substr($query, 0, -3);
        $query .= ' ' . $inject . ' ';
        $cuery = substr($cuery, 0, -3);
        $cuery .= ' ' . $inject . ' ';

        if ($paginated) {

            //COUNT ALL
            $c_result = MySQL::query($cuery, $searchTerms);

            if ($c_result !== false) {
                $n = $c_result[0]['n'];
            } else {
                $n = 0;
            }

            $currentPage = ($currentPage > 0) ? intval($currentPage) : 1;
            $offset = intval(($currentPage - 1) * $resultsPerPage);

            $query .= ' LIMIT ?,?';
            $searchTerms[] = $offset;
            $searchTerms[] = $resultsPerPage;

            $result = MySQL::query($query, $searchTerms, \PDO::FETCH_CLASS, $this->modelName);
        } else {
            $n = 0;
            $result = MySQL::query($query, [], \PDO::FETCH_CLASS, $this->modelName);
        }

        $max = ($n > 0) ? intval(ceil($n / $resultsPerPage)) : 1;

        $object = new stdClass;
        $object->result = $result;
        $object->pagination = ['max' => $max, 'query' => $pagination_key];
        $object->search = isset($_GET[$search_key]) ? filter_var($_GET[$search_key], FILTER_SANITIZE_STRING) : '';
        return $object;
    }

    public function all(bool $paginated = false, int $resultsPerPage = 10, $pagination_key = 'p', $inject = '')
    {

        $currentPage = (isset($_GET[$pagination_key])) ? intval($_GET[$pagination_key]) : 1;

        $fields = implode(', ', $this->fields);
        $query = 'SELECT ' . $fields . ' FROM ' . $this->table;
        $query .= ' ' . $inject . ' ';

        if ($paginated) {
            $currentPage = ($currentPage > 0) ? intval($currentPage) : 1;
            $offset = intval(($currentPage - 1) * $resultsPerPage);
            $query .= ' LIMIT ?,?';
            $result = MySQL::query($query, [$offset, $resultsPerPage], \PDO::FETCH_CLASS, $this->modelName);

            //COUNT ALL
            $c_query = 'SELECT COUNT(*) as n FROM ' . $this->table . ' ' . $inject;
            $c_result = MySQL::query($c_query);

            if ($c_result !== false) {
                $n = $c_result[0]['n'];
            } else {
                $n = 0;
            }
        } else {
            $n = 0;
            $result = MySQL::query($query, [], \PDO::FETCH_CLASS, $this->modelName);
        }

        $max = ($n > 0) ? intval(ceil($n / $resultsPerPage)) : 1;

        $this->result = $result;

        $object = new stdClass;
        $object->result = $result;
        $object->pagination = ['max' => $max, 'query' => $pagination_key];
        return $object;
    }

    public function allFromForeignId(string $field, int $id, bool $paginated = false, $resultsPerPage = 10, $url_query = 'p', $inject = '')
    {
        $id  = intval(filter_var($id, FILTER_SANITIZE_NUMBER_INT));
        $in  = 'WHERE ' . $field . ' = "' . $id . '" ' . $inject;
        $all = $this->all($paginated, $resultsPerPage, $url_query, $in);

        return $all;
    }

    public function getResult()
    {
        return $this->result;
    }
}
