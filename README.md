# Cakephp FileHandler-Plugin
## Description
This plugin is built on Cakephp 3.9 for import csv file. It also has a Encoder Util to fix uncoding errors on import.

## Usage:
The whole import is packed into a transcation. So keep in mind how many rows u want to import.


To use load the Component:
```php
    $this->CsvImport = $this->loadComponent('FileHandler.CsvImport');
```
### Basic Configuration:
- create instance with model alias
- default textcolumn

```php
    $this->CsvImport = $this->loadComponent('FileHandler.CsvImport');
    $import = $this->CsvImport->create('[ModalAlias]');
    $import->setColumns([
        '[HEADER_COLNAME_1]' => ['field' => 'db_fieldname_1'],
        '[HEADER_COLNAME_2]' => ['field' => 'db_fieldname_2'],
        '[HEADER_COLNAME_3]' => ['field' => 'db_fieldname_3']
    ]);
```
also possible to set each column on it's own...

```php
    $import->setColumn('[HEADER_COLNAME_4]', ['field' => 'db_fieldname_4']);
```

## Importmodes
### dryrun
here the whole process is run through including validation but no entities are created.

```php
    $import->dryrun();
```

### import
the actual import process packed inside a transction
```php
    $import->import();
```

## Basic example:
This is a minimalistic version of a csv import
```php
    $this->CsvImport = $this->loadComponent('FileHandler.CsvImport');
    $import = $this->CsvImport->create('Contacts');
    $import->setColumns([
        'Vorname'       => ['field' => 'firstname'],
        'Nachname'      => ['field' => 'lastname'],
        'Geburtsdatum'  => ['field' => 'birthdate']
    ]);
    $import->readfile('[FILEPATH]');    //here all data is read an parsed
    if ($import->import()) {
        return true;    //success
    }
    return false;   //failure
```


## Advanced Configuration
### Associations
You can also can assign associated data.

### belongsTo
minimum:
```php
    $import->setColumn('[HEADER_COLNAME_5]', ['field' => 'db_fieldname_5', 'model' => '[ModelAlias]']);
```
advanced:
```php
    $import->setColumn('[HEADER_COLNAME_6]', [
        'field'     => 'db_fieldname_6',
        'model'     => '[ModelAlias]',
        'type'      => 'belongsTo',
        'method'    => '[ModelMethodName]'      //default: getList()
    ]);
```
### belongsToMany
The value in this column gets exploded by comma (',') and filtered for empty values 
minimum: 
```php
    $import->setColumn('[HEADER_COLNAME_7]', [
        'model'     => '[ModelAlias]',          //TargetTable
        'through'   => '[ThroughModelAlias]',   //MappingTable
        'type'      => 'belongsToMany',                      
    ]);
```
advanced: 
```php
    $import->setColumn('[HEADER_COLNAME_8]', [
        'model'     => '[ModelAlias]',                  //TargetTable
        'through'   => '[ThroughModelAlias]',           //MappingTable
        'type'      => 'belongsToMany',         
        'method'    => '[ModelMethodName]'              //default: getList()
        'foreignKey' => '[current_table_id]',           //foreignKey for current Table (getAlias()) (default: cake-conventions)
        'associatedForeignkey' => '[foreign_table_id]', //foreignKey for targetTable (default: cake-conventions)
    ]);
```

### hasMany
The value in this column gets exploded by comma (',') and filtered for empty values
```php
    $import->setColumn('[HEADER_COLNAME_8]', [
        'model'     => '[ModelAlias]',          //TargetTable
        'foreignKey'=> '[current_table_id]',    //foreignKey for current Table (default: cake-conventions)
        'type'      => 'hasMany',                      
    ]);
```

## How are associations they mapped?
```php
    $colvalue   = $row[$col];
    $conf       = $columnConfig['[HEADER_COLNAME_6]'];

    //get all existing entities
    $list = {$conf['model']}->{$conf['method']}();
    
    //search for value in list
    $db_fieldname_5 = array_search($list, $colvalue);
```

## create-method for Associations
If an entry is not found in the list a new entity is created.
Default implementation is:
```php
    public function createFromImport ($value) {
        if (!empty($value)) {
            $new = $this->newEntity(['importvalue' => $value]);
            if ($this->save($new)) {
                return $new->id;
            }
        }
        return false;
    }
```
therefore you can parse the value on each entity by the setter method in the entity
```php
    class MyEntity extends AppEntity {
        ...

        protected function _setImportvalue ($value = null) {
            $this->title = $value;
            # other default settings on import
            // $this->active = 1
            // $this->info = "imported text"
        }
        
        ...
    }
```

renaming the method is possible by configuration on each column:
```php
    $import->setColumn('[HEADER_COLNAME_9]', [
        ...
        'create' => '[createMethodName]'    //creation method name on defined model by alias
    ]);
```
