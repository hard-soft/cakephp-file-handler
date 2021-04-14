<?php

namespace FileHandler\Controller\Component;

use Cake\Controller\Component;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Core\ConventionsTrait;

use FileHandler\Utils\Encoder;

class CsvImportComponent extends Component {
    use ConventionsTrait;

    private $_alias     = null;     //model alias to import
    private $_defaults  = [];       //default values for import entity

    private $_columns   = [];       //configured columns for the import
    
    private $_header    = [];       //all valid columns from the file
    private $_belongsTo = [];       //columns with mapping data
    private $_hasMany   = [];
    private $_belongsToMany = [];
    private $_colnames  = [];       //column names in the database
    private $_unknown   = [];       //all unknown columns read from the file
    private $_data      = [];       //read data from csv 

    private $_valid     = [];       //valid result data
    private $_invalid   = [];       //invalid result data

    public function reset () {
        $this->_header = [];
        $this->_belongsTo = [];
        $this->_hasMany = [];
        $this->_belongsToMany = [];
        $this->_colnames = [];
        $this->_unknown = [];
        $this->_data = [];
        $this->_valid = [];
        $this->_invalid = [];

        return $this;
    }

    public function create (string $alias, array $defaults = []) {
        $this->_alias = $alias;
        $this->_defaults = $defaults;
        return $this;
    }

    public function getDefaults () {
        return $this->_defaults;
    }

    public function setDefaults () {
        return $this->_defaults;
    }

    public function getColumn (string $name) {
        return $this->_columns[$name] ?? null;
    }

    public function setColumn (string $name, array $config = []) {
        if (!empty($config['model'])) {
            $config += [
                'type'      => 'belongsTo',
                'method'    => 'getList',
                'create'    => 'createFromImport'
            ];

            if (!empty($config['type'])) {
                if ($config['type'] == 'belongsToMany') {
                    if (empty($config['through'])) {
                        throw new BadRequestException('mapping model not defined on belongsToMany association');
                    }
                    $config += [
                        'foreignKey' => $this->_modelKey($this->getAlias()),
                        'associatedForeignkey' => $this->_modelKey($config['model']),
                    ];
                } elseif ($config['type'] == 'hasMany') {
                    $config += [
                        'foreignKey' => $this->_modelKey($this->getAlias()),
                    ];
                }
            }
        }
        if (!empty($config['options'])) {
            $config['type'] = 'belongsTo';
        }
        $this->_columns[$name] = $config;
        return $this;
    }

    public function getColumns () {
        return $this->_columns;
    }

    public function setColumns (array $config = []) {
        foreach ($config as $name => $conf) {
            $this->setColumn($name, $conf);
        }
        return $this;
    }

    public function getColumnNames () {
        return $this->_colnames;
    }

    public function setColumnNames (array $cols) {
        $this->_colnames = $cols;
        return $this;
    }

    public function getTranslatedColumns () {
        return array_combine(array_map(function ($i) { return $i['field']??false; }, $this->getColumns()), array_keys($this->getColumns()));
    }


    public function getBelongsTo () {
        return $this->_belongsTo;
    }

    public function setBelongsTo (array $cols) {
        return $this->_belongsTo = $cols;
    }

    public function getBelongsToMany () {
        return $this->_belongsToMany;
    }

    public function setBelongsToMany (array $cols) {
        return $this->_belongsToMany = $cols;
    }

    public function getHasMany () {
        return $this->_hasMany;
    }

    public function setHasMany (array $cols) {
        return $this->_hasMany = $cols;
    }

    public function getAssociationOptions () {
        $cols   = $this->getColumns();
        $hm     = array_map(function ($i) use ($cols) { return $cols[$i]['model']; }, $this->getHasMany());
        $btm    = array_map(function ($i) use ($cols) { return $cols[$i]['through']; }, $this->getBelongsToMany());
        return array_merge($hm, $btm);
    }

    /**
     * @param
     * $filepath[string] absolute filepath
     * $options[array] 
     * @return
     *  each row of the csv file as array
     */
    public function readfile ($filepath, $options = []) {
        $this->reset();
        
        if (($filedata = Encoder::getDecodedData($filepath)) !== false) {
            $lines = explode("\n", trim($filedata));

            $column_count       = null;
            $found_delimiter    = null;
            foreach ($lines as $str_line) {
                if ($column_count === null) {
                    foreach ([";", ","] as $delimiter)  {
                        $line = str_getcsv($str_line, $delimiter);
                        if (count($line) > 1) {
                            $found_delimiter = $delimiter;
                            break;
                        }
                    }

                    $column_count = count($line);
                    $this->setHeader($line);

                    $this->setUnknownColumns(array_diff($this->getHeader(), array_keys($this->getColumns())));
                    
                    //get associations
                    foreach (['setBelongsTo' => 'belongsTo', 'setBelongsToMany' => 'belongsToMany', 'setHasMany' => 'hasMany'] as $method => $type) {
                        $this->{$method}(array_intersect($this->getHeader(), array_keys(array_filter($this->getColumns(), function ($i) use ($type) { return (!empty($i['type']) && $i['type'] == $type); }))));
                    }
                } else {
                    $line = str_getcsv($str_line, $found_delimiter ?? ";");
                    if ($column_count != count($line)) {
                        throw new BadRequestException("column count does not match");
                    }
                    $this->addData($line);
                }
            }
            $this->unsetColumns(array_keys($this->_unknown));   //discard data of all unknown columns
            $columns            = $this->getColumns();
            $this->_colnames    = array_map(function ($i) use ($columns) { return $columns[$i]['field'] ?? 'fail'; }, $this->getHeader());
        }
        return $this;
    }

    public function getAlias () {
        return $this->_alias;
    }

    public function getHeader () {
        return $this->_header;
    }

    public function setHeader (array $cols) {
        $this->_header = $cols;
        return $this;
    }

    public function getUnkownColumns () {
        return $this->_unknown;
    }

    public function setUnknownColumns (array $cols) {
        $this->_unknown = $cols;
        return $this;
    }

    public function getData () {
        return $this->_data;
    }

    public function setData (array $data = []) {
        $this->_data = $data;
        return $this;
    }

    public function addData (array $data) {
        $this->_data[] = $data;
        return $this;
    }

    public function getValidData () {
        return $this->_valid;
    }

    public function setValidData (array $data = []) {
        $this->_valid = $data;
        return $this;
    }

    public function getInvalidData () {
        return $this->_invalid;
    }

    public function setInvalidData (array $data = []) {
        $this->_invalid = $data;
        return $this;
    }

    public function unsetColumns (array $cols) {
        if (!empty($cols)) {
            foreach ($this->_header as $h) {
                foreach ($cols as $c) {
                    unset($this->_header[$c]);
                }
            }
            foreach ($this->_data as $line => $row) {
                foreach ($cols as $c) {
                    unset($this->_data[$line][$c]);
                }
            }
        }
        return $this;
    }

    public function getEntity ($row = [], $header = []) {
        if (empty($header)) {
            $header = $this->_header;
        }
        if (count($row) != count($header)) {
            throw new BadRequestException("column count does not match");
        }
        return array_combine($header, $row);
    }

    /**
     * gets the assigned entity id or creates a new one
     */
    private function _getOrCreate (array &$map, string $value, array $options, bool $dryrun = false) {
        if (!empty($value)) {
            if (!empty($map)) {
                if (($key = array_search($value, $map)) !== false) {
                    return $key;
                }
            }
            if (!empty($options)) {
                if (!empty($options['model']) && !empty($options['create'])) {
                    if ($dryrun) {
                        return "create";
                    } else {
                        if ($id = $this->{$options['model']}->{$options['create']}($value)) {
                            $map[$id] = $value;
                            return $id;
                        }
                    }
                }
            }
        }
        return '';
    }

    private function _parse (bool $dryrun = false) {
        $valid      = [];
        $invalid    = [];
        $columns    = $this->getColumns();

        $this->{$this->getAlias()} = TableRegistry::getTableLocator()->get($this->getAlias());
        $assocication_options = $this->getAssociationOptions();
        foreach ($this->getData() as $row => $line) {
            $assocication_data = [];

            if (!empty($this->getBelongsTo()) || !empty($this->getBelongsToMany())) {
                foreach ($this->getBelongsTo() + $this->getBelongsToMany() as $col => $col_name) {
                    $varname    = $columns[$col_name]['varname'] ?? "map".md5($col_name);

                    if (!empty($columns[$col_name]['model'])) {
                        $model      = $columns[$col_name]['model'];
                        $method     = $columns[$col_name]['method'];

                        if (!isset($$varname)) {
                            $this->{$model} = TableRegistry::getTableLocator()->get($model);
                            $$varname = $this->{$model}->{$method}();
                            if (!is_array($$varname)) {
                                $$varname = $$varname->toArray();
                            }
                        }
                    } else {
                        if (!isset($$varname)) {
                            $$varname = $columns[$col_name]['options'];
                        }
                    }
                    if ($columns[$col_name]['type'] == 'belongsToMany') {
                        $parts = array_filter(array_map(function ($i) { return trim($i);}, explode(',', $line[$col])));
                        foreach ($parts as $p) {
                            $assocication_data[Inflector::underscore($columns[$col_name]['through'])][] = [
                                $columns[$col_name]['associatedForeignkey'] => $this->_getOrCreate($$varname, $p, $columns[$col_name], $dryrun),
                            ];
                        }
                        $line[$col] = null; //unset
                    } else {
                        $line[$col] = $this->_getOrCreate($$varname, $line[$col], $columns[$col_name], $dryrun);
                    }
                }
            }
            if (!empty($this->getHasMany())) {
                foreach ($this->getHasMany() as $col => $col_name) {
                    $parts = array_filter(array_map(function ($i) { return trim($i);}, explode(',', $line[$col])));
                    foreach ($parts as $p) {
                        $assocication_data[Inflector::underscore($columns[$col_name]['model'])][] = [
                            'importvalue' => $p
                        ];
                    }
                }
            }

            $parsed = $this->getEntity($line, $this->getColumnNames());
            $new = $this->{$this->getAlias()}->newEntity($this->getDefaults() + array_filter($parsed) + $assocication_data, [
                'associated' => $assocication_options
            ]);
            if ($new->getErrors()) {
                $this->_invalid[] = [
                    'row'       => $row + 2,  //header plus 1 (zero index)
                    'line'      => $parsed,
                    'errors'    => $new->getErrors()
                ];
            } else {
                $this->_valid[] = $new;
            }
        }
    }

    public function popBatch (int $batchSize = 0) {
        $popped_array = array_slice($this->getValidData(), -$batchSize);
        $residual     = array_slice($this->getValidData(), 0, -$batchSize);

        $this->setValidData($residual);

        return $popped_array;
    }

    public function dryrun () {
        $this->_parse(true); //nothing gets created only validation is tested
    }

    public function import (int $batchSize = 0) {
        $this->{$this->getAlias()} = TableRegistry::getTableLocator()->get($this->getAlias());
        $transaction = $this->{$this->getAlias()}->getConnection();
        $transaction->begin();
        try {
            $this->_parse();    //parse data and create belongsTo values
            $batch = $this->getValidData();
            if (!empty($batch)) {
                foreach ($batch as $new) {
                    $this->{$this->getAlias()}->save($new);
                }

                $transaction->commit();
                return true;
            }
        } catch (Exception $e) {
            $transaction->rollback();
        }
        return false;
    }
}

?>